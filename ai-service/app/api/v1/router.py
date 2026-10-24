from fastapi import APIRouter

from app.api.v1.endpoints import capabilities, detection, documents, health

router = APIRouter()
router.include_router(health.router)
router.include_router(capabilities.router)
router.include_router(documents.router)
router.include_router(detection.router)
