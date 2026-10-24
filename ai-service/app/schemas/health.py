from datetime import datetime
from typing import Literal

from pydantic import BaseModel, Field


class HealthResponse(BaseModel):
    status: Literal["ok"] = "ok"
    service: str
    version: str
    environment: str
    detector_ready: bool
    active_model: str | None = None
    timestamp: datetime


class Capability(BaseModel):
    name: str
    status: Literal["available", "planned", "disabled"]
    description: str = Field(max_length=300)


class CapabilitiesResponse(BaseModel):
    phase: str
    service: str
    version: str
    detector_ready: bool
    active_model: str | None = None
    capabilities: list[Capability]
    limitations: list[str]
