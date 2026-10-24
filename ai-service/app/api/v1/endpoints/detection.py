from pathlib import Path

from fastapi import APIRouter, HTTPException, Request

from app.schemas.detection import (
    MAX_TOTAL_CHARS,
    DetectRequest,
    DetectResponse,
    DocumentSummary,
    ModelInfo,
    SegmentPrediction,
)
from app.services.detection import detector_status, get_detector

router = APIRouter(tags=["internal"])


@router.post(
    "/detect",
    response_model=DetectResponse,
    summary="Puntúa fragmentos con el detector calibrado",
)
def detect(request: Request, payload: DetectRequest) -> DetectResponse:
    """Devuelve probabilidades calibradas por fragmento y una conclusión global.

    No calcula porcentajes de autoría. Si el detector no está disponible
    responde 503 en lugar de devolver una predicción sin modelo.
    """
    settings = request.app.state.settings
    status = detector_status(settings)

    if not status.ready:
        raise HTTPException(
            status_code=503,
            detail=status.reason or "El detector no está disponible.",
        )

    total_chars = sum(len(segment) for segment in payload.segments)
    if total_chars > MAX_TOTAL_CHARS:
        raise HTTPException(
            status_code=422,
            detail="El texto supera el límite total permitido para un análisis.",
        )

    detector = get_detector(Path(settings.model_dir))
    result = detector.score_document(payload.segments)

    return DetectResponse(
        model=ModelInfo(
            name=detector.name,
            version=detector.version,
            artifact_sha256=detector.artifact_sha256,
            high_threshold=detector.high,
            low_threshold=detector.low,
        ),
        document=DocumentSummary(
            aggregate_score=result.aggregate_score,
            conclusion=result.conclusion,
            confidence=result.confidence,
            coverage=result.coverage,
        ),
        segments=[
            SegmentPrediction(
                index=segment.index,
                score=segment.score,
                label=segment.label,
                confidence=segment.confidence,
                requires_review=segment.requires_review,
                word_count=segment.word_count,
                chunk_count=segment.chunk_count,
            )
            for segment in result.segments
        ],
    )
