import io
import re

import pytest
from document_samples import build_docx, build_scanned_pdf, build_text_pdf
from docx import Document as DocxDocument

from app.services import extraction
from app.services.extraction import ExtractionError, extract_document


def test_docx_extraction_detects_sections_and_excludes_tables_and_references() -> None:
    result = extract_document("tesis.docx", build_docx())

    assert result.engine == "python-docx"
    assert result.page_count is None
    assert result.ocr_used is False
    assert result.word_count > 0

    section_types = [section.type for section in result.sections]
    assert "abstract" in section_types
    assert "introduction" in section_types
    assert "references" in section_types

    assert any(section.title == "Resumen" for section in result.sections)

    table_segments = [segment for segment in result.segments if segment.kind == "table"]
    assert table_segments
    assert all(segment.is_excluded for segment in table_segments)
    assert all(segment.exclusion_reason == "table" for segment in table_segments)

    reference_segments = [
        segment for segment in result.segments if segment.kind == "reference"
    ]
    assert reference_segments
    assert all(segment.is_excluded for segment in reference_segments)
    assert all(segment.exclusion_reason == "references" for segment in reference_segments)

    assert all(re.fullmatch(r"[0-9a-f]{64}", segment.content_hash) for segment in result.segments)
    assert [segment.position for segment in result.segments] == list(
        range(len(result.segments))
    )


def test_docx_without_heading_still_creates_other_section() -> None:
    document = DocxDocument()
    document.add_paragraph("Un párrafo suelto sin encabezados académicos.")

    buffer = io.BytesIO()
    document.save(buffer)

    result = extract_document("suelto.docx", buffer.getvalue())

    assert result.sections[0].type == "other"
    assert result.sections[0].title is None
    assert result.segments[0].kind == "paragraph"


def test_text_pdf_extraction_reports_pages_and_sections() -> None:
    result = extract_document("articulo.pdf", build_text_pdf())

    assert result.engine == "pymupdf"
    assert result.page_count == 1
    assert result.ocr_used is False
    assert result.ocr_pages == []

    section_types = [section.type for section in result.sections]
    assert "introduction" in section_types

    paragraphs = [segment for segment in result.segments if segment.kind == "paragraph"]
    assert paragraphs
    assert all(segment.page_start == 1 for segment in paragraphs)


def test_empty_document_is_rejected() -> None:
    with pytest.raises(ExtractionError) as error:
        extract_document("vacio.pdf", b"")

    assert error.value.code == "empty_document"
    assert error.value.status_code == 422


def test_unsupported_extension_is_rejected() -> None:
    with pytest.raises(ExtractionError) as error:
        extract_document("notas.txt", b"contenido")

    assert error.value.code == "unsupported_type"
    assert error.value.status_code == 415


def test_corrupt_docx_is_rejected() -> None:
    with pytest.raises(ExtractionError) as error:
        extract_document("roto.docx", b"PK\x03\x04contenido-invalido")

    assert error.value.code == "invalid_document"
    assert error.value.status_code == 422


def test_corrupt_pdf_is_rejected() -> None:
    with pytest.raises(ExtractionError) as error:
        extract_document("roto.pdf", b"%PDF-1.7 contenido invalido")

    assert error.value.code == "invalid_document"


def test_scanned_pdf_reports_ocr_unavailable(monkeypatch: pytest.MonkeyPatch) -> None:
    def unavailable(*args: object, **kwargs: object) -> str:
        raise ExtractionError("OCR no está disponible en el servidor.", "ocr_unavailable")

    monkeypatch.setattr(extraction, "_ocr_page", unavailable)

    with pytest.raises(ExtractionError) as error:
        extract_document("escaneado.pdf", build_scanned_pdf())

    assert error.value.code == "ocr_unavailable"


def test_document_exceeding_segment_limit_is_rejected() -> None:
    with pytest.raises(ExtractionError) as error:
        extract_document("tesis.docx", build_docx(), max_segments=1)

    assert error.value.code == "document_too_large"
    assert error.value.status_code == 413


@pytest.mark.skipif(
    not extraction.ocr_available(),
    reason="Tesseract no está instalado en este entorno.",
)
def test_scanned_pdf_uses_ocr_when_available() -> None:
    result = extract_document("escaneado.pdf", build_scanned_pdf())

    assert result.ocr_used is True
    assert result.ocr_pages == [1]
    assert result.word_count > 0
