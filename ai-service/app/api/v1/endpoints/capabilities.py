from fastapi import APIRouter, Request

from app.schemas.health import CapabilitiesResponse, Capability
from app.services.detection import detector_status
from app.services.extraction import ocr_available

router = APIRouter(tags=["internal"])


@router.get(
    "/capabilities",
    response_model=CapabilitiesResponse,
    summary="Capacidades reales declaradas por el motor",
)
def capabilities(request: Request) -> CapabilitiesResponse:
    """Devuelve lo que el servicio puede hacer hoy, sin promesas de futuro."""
    settings = request.app.state.settings
    ocr_ready = settings.ocr_enabled and ocr_available()
    model = detector_status(settings)
    detector_ready = settings.detector_ready and model.ready

    if detector_ready:
        detection_status = "available"
        detection_description = (
            f"Detección orientativa calibrada por fragmentos con {model.name} "
            f"v{model.version}."
        )
    else:
        detection_status = "disabled"
        detection_description = (
            f"Detector no disponible: {model.reason}"
            if model.reason
            else "Detector no disponible."
        )

    limitations = [
        "El resultado del detector es orientativo y no demuestra autoría.",
        "El modelo se entrenó con resúmenes académicos en español y no cubre "
        "todos los géneros ni todos los generadores.",
        "Fuera de su dominio de entrenamiento el sistema debe devolver "
        "not_conclusive.",
        "La extracción no garantiza que todo PDF escaneado pueda procesarse sin OCR.",
    ]

    return CapabilitiesResponse(
        phase="3-detector",
        service=settings.app_name,
        version=settings.app_version,
        detector_ready=detector_ready,
        active_model=(
            f"{model.name} v{model.version}" if detector_ready else None
        ),
        capabilities=[
            Capability(
                name="service_auth",
                status="available",
                description="Autenticación entre servicios mediante token compartido.",
            ),
            Capability(
                name="health",
                status="available",
                description="Diagnóstico de disponibilidad y versión del microservicio.",
            ),
            Capability(
                name="document_extraction",
                status="available",
                description=(
                    "Extracción de DOCX/PDF y segmentación con exclusión de tablas "
                    "y referencias."
                ),
            ),
            Capability(
                name="ocr",
                status="available" if ocr_ready else "disabled",
                description=(
                    "OCR de PDF escaneado con Tesseract."
                    if ocr_ready
                    else "OCR deshabilitado o Tesseract no está instalado en el servidor."
                ),
            ),
            Capability(
                name="ai_detection",
                status=detection_status,
                description=detection_description,
            ),
        ],
        limitations=limitations,
    )
