from typing import Literal

from pydantic import BaseModel, Field


class PlagiarismRequest(BaseModel):
    text: str = Field(min_length=1)
    segments: list[str] | None = None


class Source(BaseModel):
    url: str
    title: str | None = None
    validated: bool = True


class Match(BaseModel):
    text: str
    full_text: str | None = None
    score: float = Field(ge=0.0, le=1.0)
    start_index: int | None = None
    end_index: int | None = None
    type: Literal["internal", "suspicious"] = "suspicious"
    length: int | None = None
    sources: list[Source] = []


class PlagiarismResponse(BaseModel):
    score: float = Field(ge=0.0, le=1.0)
    percentage: float | None = 0.0
    matches: list[Match]
    total_matches: int
    summary: str
    fragments_analyzed: int | None = 0
    fragments_matched: int | None = 0


class PlagiarismCapabilities(BaseModel):
    enabled: bool
    threshold: float
    min_length: int
