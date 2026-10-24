"""División de texto en fragmentos comparables a un párrafo.

Se usa tanto en el entrenamiento como en la inferencia para evitar que el
detector vea distribuciones de longitud distintas a las que aprendió.
"""

from __future__ import annotations

import re

SENTENCE_RE = re.compile(r"(?<=[.!?])\s+")
MIN_CHUNK_WORDS = 45
MAX_CHUNK_WORDS = 130
MIN_SCORABLE_WORDS = 25


def chunk_text(
    text: str,
    *,
    min_words: int = MIN_CHUNK_WORDS,
    max_words: int = MAX_CHUNK_WORDS,
) -> list[str]:
    """Agrupa oraciones hasta acercarse al tamaño objetivo de fragmento.

    Las oraciones que por sí solas superan ``max_words`` se conservan completas
    para no perder contenido.
    """
    sentences = [part.strip() for part in SENTENCE_RE.split(text) if part.strip()]
    chunks: list[str] = []
    current: list[str] = []
    count = 0

    for sentence in sentences:
        words = len(sentence.split())
        if count and count + words > max_words and count >= min_words:
            chunks.append(" ".join(current))
            current = []
            count = 0
        current.append(sentence)
        count += words

    if count >= min_words:
        chunks.append(" ".join(current))

    return chunks
