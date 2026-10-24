from fastapi.testclient import TestClient


def test_capabilities_are_explicitly_not_ready(
    client: TestClient, auth_headers: dict[str, str]
) -> None:
    response = client.get("/internal/v1/capabilities", headers=auth_headers)

    assert response.status_code == 200
    body = response.json()
    assert body["phase"] == "3-detector"
    assert body["detector_ready"] is False
    assert body["active_model"] is None

    capabilities = {item["name"]: item["status"] for item in body["capabilities"]}
    assert capabilities["health"] == "available"
    assert capabilities["service_auth"] == "available"
    assert capabilities["document_extraction"] == "available"
    assert capabilities["ai_detection"] == "disabled"
    assert capabilities["ocr"] in {"available", "disabled"}

    assert any("orientativo" in limitation.lower() for limitation in body["limitations"])
