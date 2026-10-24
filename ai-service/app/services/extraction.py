from __future__ import annotations

import hashlib
import io
import logging
import re
import unicodedata
from dataclasses import dataclass
from functools import lru_cache
from pathlib import Path

import pymupdf
import pytesseract
from docx import Document as DocxDocument
from docx.oxml.ns import qn
from docx.table import Table
from docx.text.paragraph import Paragraph
from PIL import Image
from pytesseract import TesseractError, TesseractNotFoundError

logger = logging.getLogger(__name__)

MAX_TITLE_CHARS = 300
WORD_RE = re.compile(r"\w+", re.UNICODE)

_SECTION_KEYWORDS: tuple[tuple[str, tuple[str, ...]], ...] = (
    ("abstract", ("resumen", "abstract")),
    ("introduction", ("introduccion", "introduction")),
    (
        "methods",
        (
            "metodologia",
            "metodo",
            "metodos",
            "materiales y metodos",
            "materials and methods",
            "method",
        ),
    ),
    ("results", ("resultados", "results")),
    ("discussion", ("discusion", "discussion")),
    ("conclusion", ("conclusion", "conclusiones", "conclusions")),
    ("references", ("referencias", "bibliografia", "references", "literatura citada")),
    ("appendix", ("anexo", "anexos", "apendice", "apendices", "appendix")),
)


@lru_cache(maxsize=1)
def ocr_available() -> bool:
    """Indica si el binario de Tesseract está instalado y es ejecutable."""
    try:
        pytesseract.get_tesseract_version()
    except (TesseractNotFoundError, TesseractError, OSError, FileNotFoundError):
        return False
    return True


class ExtractionError(Exception):
    """Error controlado de extracción con un código estable para Laravel."""

    def __init__(self, message: str, code: str = "extraction_failed", status_code: int = 422):
        super().__init__(message)
        self.message = message
        self.code = code
        self.status_code = status_code


@dataclass(slots=True)
class SectionDraft:
    position: int
    type: str
    title: str | None
    level: int
    char_start: int
    char_end: int


@dataclass(slots=True)
class SegmentDraft:
    position: int
    section_position: int | None
    kind: str
    text: str
    content_hash: str
    word_count: int
    char_start: int
    char_end: int
    page_start: int | None
    page_end: int | None
    is_excluded: bool
    exclusion_reason: str | None


@dataclass(slots=True)
class ExtractionResult:
    page_count: int | None
    word_count: int
    paragraph_count: int
    language: str | None
    sections: list[SectionDraft]
    segments: list[SegmentDraft]
    engine: str
    ocr_used: bool
    ocr_pages: list[int]
    warnings: list[str]


def extract_document(
    filename: str,
    content: bytes,
    *,
    max_segments: int = 10_000,
    ocr_enabled: bool = True,
    ocr_languages: str = "spa+eng",
    ocr_dpi: int = 200,
    min_text_chars_per_page: int = 20,
) -> ExtractionResult:
    """Extrae texto y lo segmenta a partir de un DOCX o PDF en memoria."""

    if not content:
        raise ExtractionError("El archivo está vacío.", "empty_document")

    extension = Path(filename).suffix.lower()

    if extension == ".docx":
        if not content.startswith(b"PK\x03\x04"):
            raise ExtractionError(
                "El archivo no es un DOCX válido.",
                "invalid_document",
            )
        return _extract_docx(content, max_segments=max_segments)

    if extension == ".pdf":
        if b"%PDF-" not in content[:1024]:
            raise ExtractionError(
                "El archivo no es un PDF válido.",
                "invalid_document",
            )
        return _extract_pdf(
            content,
            max_segments=max_segments,
            ocr_enabled=ocr_enabled,
            ocr_languages=ocr_languages,
            ocr_dpi=ocr_dpi,
            min_text_chars_per_page=min_text_chars_per_page,
        )

    raise ExtractionError(
        "Solo se admiten documentos DOCX o PDF.",
        "unsupported_type",
        415,
    )


def _extract_docx(content: bytes, *, max_segments: int) -> ExtractionResult:
    try:
        document = DocxDocument(io.BytesIO(content))
    except Exception as exc:
        raise ExtractionError(
            "El DOCX está dañado o no se puede leer.",
            "invalid_document",
        ) from exc

    builder = _Builder(max_segments=max_segments)

    for child in document.element.body.iterchildren():
        if child.tag == qn("w:p"):
            paragraph = Paragraph(child, document)
            text = _clean_text(paragraph.text)
            if not text:
                continue

            level = _docx_heading_level(paragraph)

            if level is not None:
                section_type = "title" if _is_docx_title(paragraph) else _classify_section(text)
                builder.start_section(section_type, text[:MAX_TITLE_CHARS], level)
                builder.add_segment(text, "heading")
                continue

            kind = "list_item" if _is_docx_list_item(paragraph) else "paragraph"
            builder.add_segment(text, kind)

        elif child.tag == qn("w:tbl"):
            table = Table(child, document)
            rows: list[str] = []
            for row in table.rows:
                cells = [_clean_text(cell.text) for cell in row.cells]
                if any(cells):
                    rows.append(" | ".join(cells))

            if rows:
                builder.add_segment(
                    " | ".join(rows),
                    "table",
                    excluded=True,
                    exclusion_reason="table",
                )

    if not builder.segments:
        raise ExtractionError(
            "El DOCX no contiene texto extraíble.",
            "empty_document",
        )

    paragraph_count = sum(
        1 for segment in builder.segments if segment.kind in {"paragraph", "list_item"}
    )

    return builder.result(
        page_count=None,
        paragraph_count=paragraph_count,
        engine="python-docx",
        ocr_pages=[],
        warnings=[],
    )


def _extract_pdf(
    content: bytes,
    *,
    max_segments: int,
    ocr_enabled: bool,
    ocr_languages: str,
    ocr_dpi: int,
    min_text_chars_per_page: int,
) -> ExtractionResult:
    try:
        document = pymupdf.open(stream=content, filetype="pdf")
    except Exception as exc:
        raise ExtractionError(
            "El PDF está dañado o no se puede leer.",
            "invalid_document",
        ) from exc

    if document.needs_pass:
        document.close()
        raise ExtractionError(
            "El PDF está protegido con contraseña.",
            "invalid_document",
        )

    page_count = document.page_count
    builder = _Builder(max_segments=max_segments)
    ocr_pages: list[int] = []
    warnings: list[str] = []
    pages_without_text: list[int] = []

    try:
        for page_index in range(document.page_count):
            page = document[page_index]
            page_number = page_index + 1
            blocks = _pdf_text_blocks(page)
            page_text = " ".join(str(block["text"]) for block in blocks).strip()

            if len(page_text) < min_text_chars_per_page and ocr_enabled:
                try:
                    ocr_text = _ocr_page(page, ocr_languages, ocr_dpi)
                except ExtractionError:
                    if not page_text:
                        pages_without_text.append(page_number)
                        warnings.append(f"ocr_unavailable_page_{page_number}")
                else:
                    if ocr_text.strip():
                        ocr_pages.append(page_number)
                        blocks = [
                            {"text": part, "size": 0.0}
                            for part in re.split(r"\n\s*\n", ocr_text)
                            if part.strip()
                        ]
                        page_text = ocr_text

            if not page_text.strip():
                if page_number not in pages_without_text:
                    pages_without_text.append(page_number)
                    warnings.append(f"no_text_page_{page_number}")
                continue

            body_size = _body_font_size(blocks)

            for block in blocks:
                text = _clean_text(str(block["text"]))
                if not text:
                    continue

                if _looks_like_pdf_heading(text, float(block.get("size", 0.0)), body_size):
                    section_type = _classify_section(text)
                    builder.start_section(section_type, text[:MAX_TITLE_CHARS], 1)
                    builder.add_segment(
                        text,
                        "heading",
                        page_start=page_number,
                        page_end=page_number,
                    )
                else:
                    builder.add_segment(
                        text,
                        "paragraph",
                        page_start=page_number,
                        page_end=page_number,
                    )
    finally:
        document.close()

    if not builder.segments:
        if pages_without_text:
            raise ExtractionError(
                "El PDF no contiene texto extraíble y OCR no está disponible.",
                "ocr_unavailable",
            )
        raise ExtractionError(
            "No se encontró texto en el documento.",
            "empty_document",
        )

    paragraph_count = sum(
        1 for segment in builder.segments if segment.kind in {"paragraph", "list_item"}
    )

    return builder.result(
        page_count=page_count,
        paragraph_count=paragraph_count,
        engine="pymupdf",
        ocr_pages=ocr_pages,
        warnings=warnings,
    )


class _Builder:
    def __init__(self, *, max_segments: int) -> None:
        self.max_segments = max_segments
        self.sections: list[SectionDraft] = []
        self.segments: list[SegmentDraft] = []
        self.current_section: SectionDraft | None = None
        self.references_mode = False
        self.full_text = ""

    def start_section(self, section_type: str, title: str | None, level: int) -> None:
        section = SectionDraft(
            position=len(self.sections),
            type=section_type,
            title=title,
            level=max(1, min(level, 6)),
            char_start=len(self.full_text),
            char_end=len(self.full_text),
        )
        self.sections.append(section)
        self.current_section = section
        self.references_mode = section_type == "references"

    def add_segment(
        self,
        text: str,
        kind: str,
        *,
        page_start: int | None = None,
        page_end: int | None = None,
        excluded: bool = False,
        exclusion_reason: str | None = None,
    ) -> None:
        clean = _clean_text(text)
        if not clean:
            return

        if len(self.segments) >= self.max_segments:
            raise ExtractionError(
                "El documento genera demasiados fragmentos para procesarlo.",
                "document_too_large",
                413,
            )

        if self.current_section is None:
            self.start_section("other", None, 1)

        if self.references_mode:
            kind = "reference"
            excluded = True
            exclusion_reason = exclusion_reason or "references"

        char_start = len(self.full_text)
        self.full_text = f"{self.full_text}{clean}\n\n"
        char_end = char_start + len(clean)

        section_position = (
            self.current_section.position if self.current_section is not None else None
        )

        self.segments.append(
            SegmentDraft(
                position=len(self.segments),
                section_position=section_position,
                kind=kind,
                text=clean,
                content_hash=hashlib.sha256(clean.encode("utf-8")).hexdigest(),
                word_count=_word_count(clean),
                char_start=char_start,
                char_end=char_end,
                page_start=page_start,
                page_end=page_end,
                is_excluded=excluded,
                exclusion_reason=exclusion_reason,
            )
        )

        if self.current_section is not None:
            self.current_section.char_end = char_end

    def result(
        self,
        *,
        page_count: int | None,
        paragraph_count: int,
        engine: str,
        ocr_pages: list[int],
        warnings: list[str],
    ) -> ExtractionResult:
        return ExtractionResult(
            page_count=page_count,
            word_count=sum(segment.word_count for segment in self.segments),
            paragraph_count=paragraph_count,
            language=None,
            sections=self.sections,
            segments=self.segments,
            engine=engine,
            ocr_used=bool(ocr_pages),
            ocr_pages=ocr_pages,
            warnings=warnings,
        )


def _docx_heading_level(paragraph: Paragraph) -> int | None:
    style = (paragraph.style.name or "").strip().lower()

    if style in {"title", "título", "titulo"}:
        return 1

    match = re.match(r"(?:heading|t[íi]tulo)\s*(\d+)", style)
    if match:
        return max(1, min(int(match.group(1)), 6))

    if style.startswith(("heading", "título", "titulo")):
        return 1

    return None


def _is_docx_title(paragraph: Paragraph) -> bool:
    return (paragraph.style.name or "").strip().lower() in {"title", "título", "titulo"}


def _is_docx_list_item(paragraph: Paragraph) -> bool:
    style = (paragraph.style.name or "").strip().lower()
    if "list" in style or "lista" in style:
        return True

    properties = paragraph._p.pPr
    return properties is not None and properties.numPr is not None


def _pdf_text_blocks(page: pymupdf.Page) -> list[dict[str, object]]:
    data = page.get_text("dict")
    blocks: list[dict[str, object]] = []

    for block in data.get("blocks", []):
        if block.get("type") != 0:
            continue

        parts: list[str] = []
        sizes: list[float] = []

        for line in block.get("lines", []):
            for span in line.get("spans", []):
                text = str(span.get("text", ""))
                if text:
                    parts.append(text)
                    sizes.append(float(span.get("size", 0.0)))

        text = " ".join(parts)
        if not text.strip():
            continue

        bbox = block.get("bbox", (0.0, 0.0, 0.0, 0.0))
        blocks.append(
            {
                "text": text,
                "size": max(sizes) if sizes else 0.0,
                "x": float(bbox[0]),
                "y": float(bbox[1]),
            }
        )

    blocks.sort(key=lambda item: (float(item["y"]), float(item["x"])))
    return blocks


def _body_font_size(blocks: list[dict[str, object]]) -> float:
    weights: dict[float, int] = {}

    for block in blocks:
        size = round(float(block.get("size", 0.0)), 1)
        if size <= 0:
            continue
        weights[size] = weights.get(size, 0) + len(str(block["text"]))

    if not weights:
        return 10.0

    return max(weights, key=lambda size: weights[size])


def _looks_like_pdf_heading(text: str, size: float, body_size: float) -> bool:
    words = _word_count(text)

    if len(text) > 200 or words > 30:
        return False

    if size >= body_size * 1.12 and words <= 20:
        return True

    if (
        _classify_section(text) != "heading"
        and words <= 12
        and not text.endswith((".", ";", ","))
    ):
        return True

    return bool(re.match(r"^\d+(?:\.\d+)*\.?\s+\S", text)) and words <= 20


def _ocr_page(page: pymupdf.Page, languages: str, dpi: int) -> str:
    try:
        pytesseract.get_tesseract_version()
    except (TesseractNotFoundError, TesseractError, OSError, FileNotFoundError) as exc:
        raise ExtractionError(
            "OCR no está disponible en el servidor.",
            "ocr_unavailable",
        ) from exc

    try:
        pixmap = page.get_pixmap(dpi=dpi, alpha=False)
        image = Image.open(io.BytesIO(pixmap.tobytes("png")))
        return pytesseract.image_to_string(image, lang=languages)
    except TesseractError as exc:
        if languages != "eng":
            try:
                return pytesseract.image_to_string(image, lang="eng")
            except (
                TesseractError,
                TesseractNotFoundError,
                OSError,
                FileNotFoundError,
            ) as fallback_exc:
                raise ExtractionError(
                    "OCR no pudo procesar la página escaneada.",
                    "ocr_unavailable",
                ) from fallback_exc
        raise ExtractionError(
            "OCR no pudo procesar la página escaneada.",
            "ocr_unavailable",
        ) from exc
    except (TesseractNotFoundError, OSError, FileNotFoundError) as exc:
        raise ExtractionError(
            "OCR no está disponible en el servidor.",
            "ocr_unavailable",
        ) from exc


def _classify_section(title: str) -> str:
    normalized = _normalize_for_match(title)
    candidates = [normalized]

    if ":" in normalized:
        candidates.append(normalized.split(":", 1)[1].strip())

    for section_type, keywords in _SECTION_KEYWORDS:
        for keyword in keywords:
            if any(candidate.startswith(keyword) for candidate in candidates):
                return section_type

    return "heading"


def _normalize_for_match(text: str) -> str:
    decomposed = unicodedata.normalize("NFKD", text)
    without_accents = "".join(char for char in decomposed if not unicodedata.combining(char))
    lowered = without_accents.lower().strip()
    return re.sub(r"^[\s\d.)\-–—]+", "", lowered)


def _clean_text(text: str) -> str:
    text = text.replace("\u00ad", "").replace("\ufeff", "")
    text = re.sub(r"[ \t]+", " ", text)
    text = re.sub(r"\s*\n\s*", " ", text)
    return text.strip()


def _word_count(text: str) -> int:
    return len(WORD_RE.findall(text))
