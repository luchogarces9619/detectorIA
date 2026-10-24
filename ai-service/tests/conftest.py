from collections.abc import Iterator

import pytest
from fastapi.testclient import TestClient
from pydantic import SecretStr

from app.core.config import Settings
from app.main import create_app
from app.services.detection import reset_cache

TEST_TOKEN = "test-token-" + ("x" * 40)


@pytest.fixture(autouse=True)
def _clear_detector_cache() -> Iterator[None]:
    reset_cache()
    yield
    reset_cache()


@pytest.fixture
def settings() -> Settings:
    return Settings(
        environment="test",
        internal_token=SecretStr(TEST_TOKEN),
        detector_ready=False,
        _env_file=None,
    )


@pytest.fixture
def client(settings: Settings) -> Iterator[TestClient]:
    with TestClient(create_app(settings)) as test_client:
        yield test_client


@pytest.fixture
def auth_headers() -> dict[str, str]:
    return {"X-Internal-Token": TEST_TOKEN}
