from fastapi.testclient import TestClient


def test_internal_health_requires_token(client: TestClient) -> None:
    response = client.get("/internal/v1/health")

    assert response.status_code == 401
    assert response.json()["error"]["code"] == "http_error"


def test_internal_health_rejects_wrong_token(client: TestClient) -> None:
    response = client.get(
        "/internal/v1/health",
        headers={"X-Internal-Token": "wrong-token-" + ("y" * 40)},
    )

    assert response.status_code == 401


def test_internal_health_accepts_valid_token(
    client: TestClient, auth_headers: dict[str, str]
) -> None:
    response = client.get("/internal/v1/health", headers=auth_headers)

    assert response.status_code == 200
    assert response.json()["status"] == "ok"


def test_unknown_internal_route_uses_error_envelope(
    client: TestClient, auth_headers: dict[str, str]
) -> None:
    response = client.get("/internal/v1/does-not-exist", headers=auth_headers)

    assert response.status_code == 404
    assert response.json()["error"]["code"] == "http_error"
