"""Normalización común para el entrenamiento y la inferencia.

El objetivo es eliminar señales de formato que no existen en el texto extraído
de un DOCX o PDF real (Markdown, etiquetas de sección, palabras clave). Si no
se normalizaran ambas clases, el detector aprendería a reconocer el formato de
generación en lugar del estilo.

La misma función se aplica al construir el corpus y al puntuar un documento,
para que el modelo vea exactamente la misma distribución en ambos momentos.
"""

from __future__ import annotations

import re

MARKDOWN_RE = re.compile(r"\*\*|__|`|~~|(?<!\w)\*(?=\S)|(?<=\S)\*(?!\w)")
HEADING_RE = re.compile(r"^\s{0,3}#{1,6}\s*", re.MULTILINE)
LABEL_RE = re.compile(
    r"^\s*(resumen|abstract|objetivo|objetivos|introducci[oó]n|"
    r"metodolog[ií]a|m[eé]todos|materiales y m[eé]todos|resultados|"
    r"discusi[oó]n|conclusiones?)\s*[:.\-–—]\s*",
    re.IGNORECASE,
)
INLINE_LABEL_RE = re.compile(
    r"\b(objetivo|objetivos|introducci[oó]n|metodolog[ií]a|m[eé]todos|"
    r"materiales y m[eé]todos|resultados|discusi[oó]n|conclusiones?)"
    r"\s*[:.]\s*",
    re.IGNORECASE,
)
LEADING_WORD_RE = re.compile(
    r"^\s*(resumen|abstract|resumo)\b\s*[:.\-–—]?\s*",
    re.IGNORECASE,
)
TRAILING_KEYWORDS_RE = re.compile(
    r"\s*(palabras\s+clave|palavras-chave|keywords)\s*[:.\-–—].*$",
    re.IGNORECASE | re.DOTALL,
)
SPACE_RE = re.compile(r"\s+")


def normalize_text(text: str) -> str:
    """Quita formato editorial y etiquetas repetidas al inicio del texto."""
    cleaned = MARKDOWN_RE.sub("", text)
    cleaned = HEADING_RE.sub("", cleaned)
    cleaned = TRAILING_KEYWORDS_RE.sub("", cleaned)
    cleaned = INLINE_LABEL_RE.sub("", cleaned)
    cleaned = LEADING_WORD_RE.sub("", cleaned)

    for _ in range(4):
        stripped = LABEL_RE.sub("", cleaned)
        if stripped == cleaned:
            break
        cleaned = stripped

    return SPACE_RE.sub(" ", cleaned).strip()
