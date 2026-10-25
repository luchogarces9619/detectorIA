from fastapi import APIRouter, HTTPException, Request

from app.schemas.plagiarism import PlagiarismCapabilities, PlagiarismRequest, PlagiarismResponse
from app.services.plagiarism import PlagiarismDetector

router = APIRouter(tags=["internal"])


@router.get("/plagiarism/capabilities", response_model=PlagiarismCapabilities)
def plagiarism_capabilities(request: Request) -> PlagiarismCapabilities:
    settings = request.app.state.settings
    return PlagiarismCapabilities(
        enabled=settings.plagiarism_enabled,
        threshold=settings.plagiarism_threshold,
        min_length=settings.plagiarism_min_length,
    )


@router.post("/plagiarism/detect", response_model=PlagiarismResponse)
def plagiarism_detect(request: Request, payload: PlagiarismRequest) -> PlagiarismResponse:
    settings = request.app.state.settings
    if not settings.plagiarism_enabled:
        raise HTTPException(status_code=503, detail="Detección de plagio deshabilitada")

    detector = PlagiarismDetector(
        threshold=settings.plagiarism_threshold,
        min_length=settings.plagiarism_min_length,
    )
    result = detector.detect(payload.text, payload.segments)
    return PlagiarismResponse(**result)
