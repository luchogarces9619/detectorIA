import secrets
import uuid
from typing import Annotated

from fastapi import Header, HTTPException, Request, status
from starlette.middleware.base import BaseHTTPMiddleware, RequestResponseEndpoint
from starlette.responses import Response


class RequestIdMiddleware(BaseHTTPMiddleware):
    """Propaga un identificador de petición para trazabilidad entre servicios."""

    async def dispatch(self, request: Request, call_next: RequestResponseEndpoint) -> Response:
        candidate = request.headers.get("X-Request-ID", "")
        request_id = candidate if _is_valid_request_id(candidate) else str(uuid.uuid4())
        request.state.request_id = request_id
        response = await call_next(request)
        response.headers["X-Request-ID"] = request_id
        return response


def _is_valid_request_id(value: str) -> bool:
    return 1 <= len(value) <= 128 and all(char.isalnum() or char in "._-" for char in value)


def verify_internal_token(
    request: Request,
    x_internal_token: Annotated[str | None, Header()] = None,
) -> None:
    """Valida el token compartido entre Laravel y FastAPI.

    La comparación es de tiempo constante. El servicio no se publica en
    Internet; el token es una segunda capa de defensa.
    """
    settings = request.app.state.settings
    if not x_internal_token or not secrets.compare_digest(
        x_internal_token.encode("utf-8"),
        settings.internal_token.get_secret_value().encode("utf-8"),
    ):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Credencial de servicio inválida.",
            headers={"WWW-Authenticate": "SciTrace-Internal"},
        )
