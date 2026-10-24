from typing import Annotated, Literal

from pydantic import BaseModel, Field, StringConstraints

MAX_SEGMENTS = 500
MAX_SEGMENT_CHARS = 20_000
MAX_TOTAL_CHARS = 400_000

SegmentText = Annotated[str, StringConstraints(min_length=1, max_length=MAX_SEGMENT_CHARS)]


class DetectRequest(BaseModel):
    segments: list[SegmentText] = Field(min_length=1, max_length=MAX_SEGMENTS)
    document_id: str | None = Field(default=None, max_length=64)


class SegmentPrediction(BaseModel):
    index: int
    score: float = Field(ge=0.0, le=1.0)
    label: Literal["human", "ai", "uncertain"]
    confidence: Literal["insufficient", "low", "medium", "high"]
    requires_review: bool
    word_count: int
    chunk_count: int


class DocumentSummary(BaseModel):
    aggregate_score: float | None = Field(default=None, ge=0.0, le=1.0)
    conclusion: Literal["not_conclusive", "no_signals", "review_suggested", "mixed_signals"]
    confidence: Literal["insufficient", "low", "medium", "high"]
    coverage: float = Field(ge=0.0, le=1.0)


class ModelInfo(BaseModel):
    name: str
    version: str
    artifact_sha256: str
    high_threshold: float
    low_threshold: float


class DetectResponse(BaseModel):
    model: ModelInfo
    document: DocumentSummary
    segments: list[SegmentPrediction]
    disclaimer: str = (
        "Resultado orientativo para revisión humana. No demuestra autoría ni "
        "calcula un porcentaje de texto generado por IA."
    )
