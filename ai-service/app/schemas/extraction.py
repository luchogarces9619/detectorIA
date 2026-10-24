from pydantic import BaseModel, Field


class SectionOut(BaseModel):
    position: int = Field(ge=0)
    type: str
    title: str | None = None
    level: int = Field(ge=1, le=6)
    char_start: int = Field(ge=0)
    char_end: int = Field(ge=0)


class SegmentOut(BaseModel):
    position: int = Field(ge=0)
    section_position: int | None = None
    kind: str
    text: str
    content_hash: str = Field(min_length=64, max_length=64)
    word_count: int = Field(ge=0)
    char_start: int = Field(ge=0)
    char_end: int = Field(ge=0)
    page_start: int | None = Field(default=None, ge=1)
    page_end: int | None = Field(default=None, ge=1)
    is_excluded: bool
    exclusion_reason: str | None = None


class ExtractionStats(BaseModel):
    page_count: int | None = Field(default=None, ge=1)
    word_count: int = Field(ge=0)
    paragraph_count: int = Field(ge=0)
    section_count: int = Field(ge=0)
    segment_count: int = Field(ge=0)
    excluded_segment_count: int = Field(ge=0)


class ExtractionResponse(BaseModel):
    original_name: str
    extension: str
    mime_type: str
    size_bytes: int = Field(ge=1)
    sha256: str = Field(min_length=64, max_length=64)
    engine: str
    ocr_used: bool
    ocr_pages: list[int]
    language: str | None = None
    warnings: list[str]
    stats: ExtractionStats
    sections: list[SectionOut]
    segments: list[SegmentOut]
