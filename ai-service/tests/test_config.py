from pydantic import SecretStr, ValidationError

from app.core.config import Settings


def test_short_internal_token_is_rejected() -> None:
    try:
        Settings(internal_token=SecretStr("demasiado-corto"), _env_file=None)
    except ValidationError as exc:
        assert "AI_INTERNAL_TOKEN" in str(exc) or "32" in str(exc)
    else:
        raise AssertionError("Se esperaba un error de validación para un token corto")


def test_production_disables_api_docs() -> None:
    from app.main import create_app

    production = Settings(
        environment="production",
        internal_token=SecretStr("z" * 48),
        _env_file=None,
    )
    production_app = create_app(production)

    assert production_app.docs_url is None
    assert production_app.openapi_url is None
