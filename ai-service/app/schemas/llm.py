
from pydantic import BaseModel, Field


class LLMRequest(BaseModel):
    prompt: str = Field(min_length=1, max_length=10000)
    system_prompt: str | None = Field(default=None, max_length=2000)
    temperature: float = Field(default=0.3, ge=0.0, le=1.0)
    max_tokens: int = Field(default=1024, ge=1, le=4096)


class LLMResponse(BaseModel):
    text: str
    model: str
    provider: str
    usage: dict | None = None


class LLMCapabilities(BaseModel):
    enabled: bool
    provider: str
    model: str | None
    available: bool
