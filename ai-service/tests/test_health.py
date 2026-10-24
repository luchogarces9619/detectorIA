from fastapi.testclient import TestClient


def test_public_health_is_available_without_token(client: TestClient) -> None:
    response = client.get("/health")

    assert response.status_code == 200
    body = response.json()
    assert body["status"] == "ok"
    assert body["detector_ready"] is False
    assert body["active_model"] is None
    assert body["environment"] == "test"


def test_public_health_does_not_expose_token(client: TestClient) -> None:
    response = client.get("/health")

    assert "token" not in response.text.lower()


def test_request_id_is_echoed_when_valid(client: TestClient) -> None:
    response = client.get("/health", headers={"X-Request-ID": "req-123.abc"})

    assert response.headers["X-Request-ID"] == "req-123.abc"


def test_invalid_request_id_is_replaced(client: TestClient) -> None:
    response = client.get("/health", headers={"X-Request-ID": "bad id with spaces"})

    assert response.headers["X-Request-ID"] != "bad id with spaces"
    assert len(response.headers["X-Request-ID"]) >= 32
