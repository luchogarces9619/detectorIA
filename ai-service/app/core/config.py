from functools import lru_cache
from typing import Literal

from pydantic import Field, SecretStr, field_validator
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    """Configuración del microservicio.

    Todas las variables se leen con el prefijo AI_ (por ejemplo
    AI_INTERNAL_TOKEN). No se incluyen credenciales por defecto.
    """

    model_config = SettingsConfigDict(
        env_prefix="AI_",
        env_file=".env",
        env_file_encoding="utf-8",
        case_sensitive=False,
        extra="ignore",
    )

    app_name: str = "SciTrace AI Engine"
    app_version: str = "0.1.0"
    environment: Literal["local", "test", "production"] = "local"
    log_level: Literal["DEBUG", "INFO", "WARNING", "ERROR", "CRITICAL"] = "INFO"

    api_prefix: str = "/internal/v1"
    internal_token: SecretStr = Field(
        ...,
        description="Token compartido con Laravel. Mínimo 32 caracteres.",
    )

    detector_ready: bool = True
    detector_enabled: bool = True
    model_dir: str = "models/scitrace-es-v1"
    max_document_bytes: int = 25 * 1024 * 1024
    max_segments: int = 10_000

    ocr_enabled: bool = True
    ocr_languages: str = "spa+eng"
    ocr_dpi: int = Field(default=200, ge=100, le=400)
    min_text_chars_per_page: int = Field(default=20, ge=0, le=10_000)

    @field_validator("internal_token")
    @classmethod
    def token_must_be_strong(cls, value: SecretStr) -> SecretStr:
        if len(value.get_secret_value()) < 32:
            raise ValueError("AI_INTERNAL_TOKEN debe tener al menos 32 caracteres")
        return value


@lru_cache
def get_settings() -> Settings:
    """Devuelve una instancia cacheada de la configuración."""
    return Settings()  # type: ignore[call-arg]
