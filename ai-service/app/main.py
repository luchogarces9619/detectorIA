from typing import Any

from fastapi import Depends, FastAPI, Request
from fastapi.exceptions import RequestValidationError
from fastapi.responses import JSONResponse
from starlette.exceptions import HTTPException as StarletteHTTPException

from app.api.v1.endpoints.health import build_health_response
from app.api.v1.router import router as internal_router
from app.core.config import Settings, get_settings
from app.core.logging import configure_logging
from app.core.security import RequestIdMiddleware, verify_internal_token
from app.schemas.health import HealthResponse
from app.services.extraction import ExtractionError


def _error_payload(request: Request, code: str, message: str) -> dict[str, Any]:
    return {
        "error": {"code": code, "message": message},
        "request_id": getattr(request.state, "request_id", None),
    }


def create_app(settings: Settings | None = None) -> FastAPI:
    settings = settings or get_settings()
    configure_logging(settings)

    docs_enabled = settings.environment != "production"
    app = FastAPI(
        title=settings.app_name,
        version=settings.app_version,
        description=(
            "API interna de SciTrace AI. No es una API pública. "
            "El resultado del futuro detector será orientativo y no una prueba de autoría."
        ),
        docs_url="/docs" if docs_enabled else None,
        redoc_url=None,
        openapi_url="/openapi.json" if docs_enabled else None,
    )
    app.state.settings = settings
    app.add_middleware(RequestIdMiddleware)

    app.include_router(
        internal_router,
        prefix=settings.api_prefix,
        dependencies=[Depends(verify_internal_token)],
    )

    @app.get("/health", response_model=HealthResponse, tags=["ops"])
    def public_health(request: Request) -> HealthResponse:
        """Sonda de disponibilidad para Docker. No expone secretos."""
        return build_health_response(request)

    @app.exception_handler(ExtractionError)
    async def extraction_exception_handler(
        request: Request, exc: ExtractionError
    ) -> JSONResponse:
        return JSONResponse(
            status_code=exc.status_code,
            content=_error_payload(request, exc.code, exc.message),
        )

    @app.exception_handler(StarletteHTTPException)
    async def http_exception_handler(
        request: Request, exc: StarletteHTTPException
    ) -> JSONResponse:
        message = exc.detail if isinstance(exc.detail, str) else "Error de la petición."
        return JSONResponse(
            status_code=exc.status_code,
            content=_error_payload(request, "http_error", message),
            headers=exc.headers,
        )

    @app.exception_handler(RequestValidationError)
    async def validation_exception_handler(
        request: Request, exc: RequestValidationError
    ) -> JSONResponse:
        return JSONResponse(
            status_code=422,
            content=_error_payload(request, "validation_error", "Datos de entrada inválidos."),
        )

    @app.exception_handler(Exception)
    async def unhandled_exception_handler(request: Request, exc: Exception) -> JSONResponse:
        # El detalle se registra en el servidor; nunca se devuelve al cliente.
        return JSONResponse(
            status_code=500,
            content=_error_payload(request, "internal_error", "Error interno del servicio."),
        )

    return app
