from document_samples import build_docx, build_text_pdf
from fastapi.testclient import TestClient

DOCX_MIME = "application/vnd.openxmlformats-officedocument.wordprocessingml.document"


def _docx_files() -> dict[str, tuple[str, bytes, str]]:
    return {"file": ("tesis.docx", build_docx(), DOCX_MIME)}


def test_extract_endpoint_returns_sections_and_segments(
    client: TestClient, auth_headers: dict[str, str]
) -> None:
    response = client.post(
        "/internal/v1/documents/extract",
        headers=auth_headers,
        files=_docx_files(),
    )

    assert response.status_code == 200
    body = response.json()

    assert body["original_name"] == "tesis.docx"
    assert body["extension"] == "docx"
    assert body["mime_type"] == DOCX_MIME
    assert body["size_bytes"] > 0
    assert len(body["sha256"]) == 64
    assert body["engine"] == "python-docx"
    assert body["ocr_used"] is False

    assert body["stats"]["section_count"] == len(body["sections"])
    assert body["stats"]["segment_count"] == len(body["segments"])
    assert body["stats"]["excluded_segment_count"] >= 2
    assert body["stats"]["word_count"] > 0

    section_types = [section["type"] for section in body["sections"]]
    assert "references" in section_types

    assert all(segment["is_excluded"] for segment in body["segments"] if segment["kind"] == "table")


def test_extract_endpoint_accepts_text_pdf(
    client: TestClient, auth_headers: dict[str, str]
) -> None:
    files = {"file": ("articulo.pdf", build_text_pdf(), "application/pdf")}

    response = client.post(
        "/internal/v1/documents/extract",
        headers=auth_headers,
        files=files,
    )

    assert response.status_code == 200
    body = response.json()
    assert body["extension"] == "pdf"
    assert body["stats"]["page_count"] == 1
    assert body["stats"]["segment_count"] > 0


def test_extract_endpoint_requires_internal_token(client: TestClient) -> None:
    response = client.post("/internal/v1/documents/extract", files=_docx_files())

    assert response.status_code == 401
    assert response.json()["error"]["code"] == "http_error"


def test_extract_endpoint_rejects_unsupported_type(
    client: TestClient, auth_headers: dict[str, str]
) -> None:
    files = {"file": ("notas.txt", b"contenido plano", "text/plain")}

    response = client.post(
        "/internal/v1/documents/extract",
        headers=auth_headers,
        files=files,
    )

    assert response.status_code == 415
    body = response.json()
    assert body["error"]["code"] == "unsupported_type"
    assert body["request_id"]


def test_extract_endpoint_rejects_mismatched_mime_type(
    client: TestClient, auth_headers: dict[str, str]
) -> None:
    files = {"file": ("tesis.docx", build_docx(), "text/html")}

    response = client.post(
        "/internal/v1/documents/extract",
        headers=auth_headers,
        files=files,
    )

    assert response.status_code == 415
    assert response.json()["error"]["code"] == "unsupported_type"


def test_extract_endpoint_rejects_oversized_file(
    client: TestClient, auth_headers: dict[str, str]
) -> None:
    client.app.state.settings.max_document_bytes = 128

    response = client.post(
        "/internal/v1/documents/extract",
        headers=auth_headers,
        files=_docx_files(),
    )

    assert response.status_code == 413
    assert response.json()["error"]["code"] == "document_too_large"


def test_extract_endpoint_rejects_empty_file(
    client: TestClient, auth_headers: dict[str, str]
) -> None:
    files = {"file": ("vacio.pdf", b"", "application/pdf")}

    response = client.post(
        "/internal/v1/documents/extract",
        headers=auth_headers,
        files=files,
    )

    assert response.status_code == 422
    assert response.json()["error"]["code"] == "empty_document"


def test_extract_endpoint_rejects_corrupt_docx(
    client: TestClient, auth_headers: dict[str, str]
) -> None:
    files = {"file": ("roto.docx", b"PK\x03\x04contenido-invalido", DOCX_MIME)}

    response = client.post(
        "/internal/v1/documents/extract",
        headers=auth_headers,
        files=files,
    )

    assert response.status_code == 422
    assert response.json()["error"]["code"] == "invalid_document"


def test_extract_endpoint_requires_file_field(
    client: TestClient, auth_headers: dict[str, str]
) -> None:
    response = client.post(
        "/internal/v1/documents/extract",
        headers=auth_headers,
        data={"otro": "valor"},
    )

    assert response.status_code == 422
    assert response.json()["error"]["code"] == "validation_error"
