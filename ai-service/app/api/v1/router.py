from fastapi import APIRouter

from app.api.v1.endpoints import capabilities, detection, documents, health, llm, plagiarism

router = APIRouter()
router.include_router(health.router)
router.include_router(capabilities.router)
router.include_router(documents.router)
router.include_router(detection.router)
router.include_router(llm.router)
router.include_router(plagiarism.router)
