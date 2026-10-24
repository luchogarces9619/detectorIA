from datetime import UTC, datetime

from fastapi import APIRouter, Request

from app.core.config import Settings
from app.schemas.health import HealthResponse
from app.services.detection import detector_status

router = APIRouter(tags=["internal"])


def active_model_label(settings: Settings) -> str | None:
    """Etiqueta legible del modelo activo, solo si el artefacto es válido."""
    status = detector_status(settings)
    if not status.ready:
        return None
    return f"{status.name} v{status.version}"


def build_health_response(request: Request) -> HealthResponse:
    settings = request.app.state.settings
    model = active_model_label(settings)
    return HealthResponse(
        service=settings.app_name,
        version=settings.app_version,
        environment=settings.environment,
        detector_ready=model is not None,
        active_model=model,
        timestamp=datetime.now(UTC),
    )


@router.get("/health", response_model=HealthResponse, summary="Estado interno del motor")
def internal_health(request: Request) -> HealthResponse:
    return build_health_response(request)
