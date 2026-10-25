"""Cliente LLM para modelos gratuitos."""
from __future__ import annotations

import logging
from typing import Any

import httpx

from app.core.config import Settings

logger = logging.getLogger(__name__)


class LLMError(Exception):
    def __init__(self, message: str, code: str = "llm_error"):
        self.message = message
        self.code = code
        super().__init__(message)


class LLMClient:
    def __init__(self, settings: Settings):
        self.settings = settings
        self.enabled = settings.llm_enabled and settings.llm_provider != "none"
        self.provider = settings.llm_provider
        self.model = settings.llm_model
        self.base_url = settings.llm_base_url
        self.api_key = settings.llm_api_key.get_secret_value() if settings.llm_api_key else None
        self.timeout = settings.llm_timeout_seconds

    async def complete(
        self,
        prompt: str,
        system_prompt: str | None = None,
        temperature: float = 0.3,
        max_tokens: int = 1024,
    ) -> dict[str, Any]:
        if not self.enabled:
            raise LLMError("LLM no está habilitado", "llm_disabled")

        if self.provider == "openrouter":
            return await self._complete_openrouter(prompt, system_prompt, temperature, max_tokens)
        elif self.provider == "ollama":
            return await self._complete_ollama(prompt, system_prompt, temperature, max_tokens)
        elif self.provider == "groq":
            return await self._complete_groq(prompt, system_prompt, temperature, max_tokens)
        else:
            raise LLMError(f"Proveedor no soportado: {self.provider}", "llm_unsupported")

    async def _complete_openrouter(
        self, prompt: str, system_prompt: str | None, temperature: float, max_tokens: int
    ) -> dict[str, Any]:
        if not self.base_url:
            self.base_url = "https://openrouter.ai/api/v1/chat/completions"
        if not self.model:
            self.model = "google/gemma-2-9b-it:free"

        headers = {"Content-Type": "application/json"}
        if self.api_key:
            headers["Authorization"] = f"Bearer {self.api_key}"
        headers["HTTP-Referer"] = "http://localhost:8000"
        headers["X-Title"] = "SciTrace AI"

        messages = []
        if system_prompt:
            messages.append({"role": "system", "content": system_prompt})
        messages.append({"role": "user", "content": prompt})

        async with httpx.AsyncClient(timeout=self.timeout) as client:
            response = await client.post(
                self.base_url,
                headers=headers,
                json={
                    "model": self.model,
                    "messages": messages,
                    "temperature": temperature,
                    "max_tokens": max_tokens,
                },
            )
            response.raise_for_status()
            data = response.json()
            return {
                "text": data["choices"][0]["message"]["content"],
                "model": self.model,
                "provider": self.provider,
                "usage": data.get("usage"),
            }

    async def _complete_ollama(
        self, prompt: str, system_prompt: str | None, temperature: float, max_tokens: int
    ) -> dict[str, Any]:
        if not self.base_url:
            self.base_url = "http://localhost:11434/v1/chat/completions"
        if not self.model:
            self.model = "llama-3.2:3b"

        headers = {"Content-Type": "application/json"}
        messages = []
        if system_prompt:
            messages.append({"role": "system", "content": system_prompt})
        messages.append({"role": "user", "content": prompt})

        async with httpx.AsyncClient(timeout=self.timeout) as client:
            response = await client.post(
                self.base_url,
                headers=headers,
                json={
                    "model": self.model,
                    "messages": messages,
                    "temperature": temperature,
                    "max_tokens": max_tokens,
                    "stream": False,
                },
            )
            response.raise_for_status()
            data = response.json()
            return {
                "text": data["choices"][0]["message"]["content"],
                "model": self.model,
                "provider": self.provider,
                "usage": data.get("usage"),
            }

    async def _complete_groq(
        self, prompt: str, system_prompt: str | None, temperature: float, max_tokens: int
    ) -> dict[str, Any]:
        if not self.base_url:
            self.base_url = "https://api.groq.com/openai/v1/chat/completions"
        if not self.model:
            self.model = "llama-3.1-8b-instant"

        headers = {"Content-Type": "application/json"}
        if self.api_key:
            headers["Authorization"] = f"Bearer {self.api_key}"

        messages = []
        if system_prompt:
            messages.append({"role": "system", "content": system_prompt})
        messages.append({"role": "user", "content": prompt})

        async with httpx.AsyncClient(timeout=self.timeout) as client:
            response = await client.post(
                self.base_url,
                headers=headers,
                json={
                    "model": self.model,
                    "messages": messages,
                    "temperature": temperature,
                    "max_tokens": max_tokens,
                },
            )
            response.raise_for_status()
            data = response.json()
            return {
                "text": data["choices"][0]["message"]["content"],
                "model": self.model,
                "provider": self.provider,
                "usage": data.get("usage"),
            }
