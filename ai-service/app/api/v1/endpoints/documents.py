import hashlib
from pathlib import Path
from typing import Annotated

from fastapi import APIRouter, File, Request, UploadFile

from app.schemas.extraction import (
    ExtractionResponse,
    ExtractionStats,
    SectionOut,
    SegmentOut,
)
from app.services.extraction import ExtractionError, extract_document

router = APIRouter(tags=["internal"])

ALLOWED_EXTENSIONS = {".docx", ".pdf"}
ALLOWED_CONTENT_TYPES = {
    "application/pdf",
    "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
    "application/octet-stream",
}
DEFAULT_CONTENT_TYPES = {
    ".pdf": "application/pdf",
    ".docx": "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
}


@router.post(
    "/documents/extract",
    response_model=ExtractionResponse,
    summary="Extrae y segmenta un DOCX o PDF",
)
async def extract_document_endpoint(
    request: Request,
    file: Annotated[UploadFile, File(description="Documento DOCX o PDF")],
) -> ExtractionResponse:
    """Extrae texto, secciones y fragmentos de un documento privado.

    El archivo se procesa en memoria y no se persiste en este servicio.
    Laravel es el único responsable del almacenamiento y de los permisos.
    """
    settings = request.app.state.settings
    original_name = Path(file.filename or "").name
    extension = Path(original_name).suffix.lower()

    if extension not in ALLOWED_EXTENSIONS:
        raise ExtractionError(
            "Solo se admiten documentos DOCX o PDF.",
            "unsupported_type",
            415,
        )

    declared_type = (file.content_type or "").split(";", 1)[0].strip().lower()
    if declared_type and declared_type not in ALLOWED_CONTENT_TYPES:
        raise ExtractionError(
            "El tipo MIME declarado no corresponde a un DOCX o PDF.",
            "unsupported_type",
            415,
        )

    content = await file.read(settings.max_document_bytes + 1)
    await file.close()

    if len(content) > settings.max_document_bytes:
        raise ExtractionError(
            "El archivo supera el tamaño máximo permitido.",
            "document_too_large",
            413,
        )

    if not content:
        raise ExtractionError("El archivo está vacío.", "empty_document", 422)

    result = extract_document(
        original_name,
        content,
        max_segments=settings.max_segments,
        ocr_enabled=settings.ocr_enabled,
        ocr_languages=settings.ocr_languages,
        ocr_dpi=settings.ocr_dpi,
        min_text_chars_per_page=settings.min_text_chars_per_page,
    )

    return ExtractionResponse(
        original_name=original_name,
        extension=extension.lstrip("."),
        mime_type=declared_type or DEFAULT_CONTENT_TYPES[extension],
        size_bytes=len(content),
        sha256=hashlib.sha256(content).hexdigest(),
        engine=result.engine,
        ocr_used=result.ocr_used,
        ocr_pages=result.ocr_pages,
        language=result.language,
        warnings=result.warnings,
        stats=ExtractionStats(
            page_count=result.page_count,
            word_count=result.word_count,
            paragraph_count=result.paragraph_count,
            section_count=len(result.sections),
            segment_count=len(result.segments),
            excluded_segment_count=sum(
                1 for segment in result.segments if segment.is_excluded
            ),
        ),
        sections=[
            SectionOut(
                position=section.position,
                type=section.type,
                title=section.title,
                level=section.level,
                char_start=section.char_start,
                char_end=section.char_end,
            )
            for section in result.sections
        ],
        segments=[
            SegmentOut(
                position=segment.position,
                section_position=segment.section_position,
                kind=segment.kind,
                text=segment.text,
                content_hash=segment.content_hash,
                word_count=segment.word_count,
                char_start=segment.char_start,
                char_end=segment.char_end,
                page_start=segment.page_start,
                page_end=segment.page_end,
                is_excluded=segment.is_excluded,
                exclusion_reason=segment.exclusion_reason,
            )
            for segment in result.segments
        ],
    )
