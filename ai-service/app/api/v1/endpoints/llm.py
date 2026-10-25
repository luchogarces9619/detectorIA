from fastapi import APIRouter, HTTPException, Request

from app.schemas.llm import LLMCapabilities, LLMRequest, LLMResponse
from app.services.llm.client import LLMClient, LLMError

router = APIRouter(tags=["internal"])


@router.get("/llm/capabilities", response_model=LLMCapabilities)
def llm_capabilities(request: Request) -> LLMCapabilities:
    settings = request.app.state.settings
    client = LLMClient(settings)
    return LLMCapabilities(
        enabled=client.enabled,
        provider=client.provider,
        model=client.model,
        available=client.enabled,
    )


@router.post("/llm/complete", response_model=LLMResponse)
async def llm_complete(request: Request, payload: LLMRequest) -> LLMResponse:
    settings = request.app.state.settings
    client = LLMClient(settings)
    try:
        result = await client.complete(
            prompt=payload.prompt,
            system_prompt=payload.system_prompt,
            temperature=payload.temperature,
            max_tokens=payload.max_tokens,
        )
        return LLMResponse(**result)
    except LLMError as e:
        raise HTTPException(status_code=503, detail=e.message) from e
    except Exception as exc:
        raise HTTPException(status_code=500, detail="Error en el servicio LLM") from exc
