from app.services.chunking import chunk_text
from app.services.normalize import normalize_text


def test_chunk_text_respects_minimum_size() -> None:
    text = "Frase corta. Otra frase corta."
    assert chunk_text(text) == []


def test_chunk_text_groups_sentences() -> None:
    sentence = "Esta oración contiene exactamente diez palabras para la prueba de fragmentos."
    text = " ".join([sentence] * 16)

    chunks = chunk_text(text)

    assert len(chunks) >= 2
    assert all(len(chunk.split()) >= 45 for chunk in chunks)
    assert all(len(chunk.split()) <= 140 for chunk in chunks)


def test_chunk_text_keeps_single_long_sentence() -> None:
    sentence = " ".join(["palabra"] * 150)

    chunks = chunk_text(sentence)

    assert len(chunks) == 1
    assert len(chunks[0].split()) == 150


def test_normalize_removes_markdown_and_labels() -> None:
    text = (
        "**Resumen** Objetivo: analizar el fenómeno. "
        "**Metodología:** encuesta. Resultados: positivos."
    )

    normalized = normalize_text(text)

    assert "**" not in normalized
    assert "Objetivo:" not in normalized
    assert "Metodología:" not in normalized
    assert normalized.startswith("analizar")


def test_normalize_removes_keywords_tail() -> None:
    text = "Texto del resumen con contenido suficiente. Palabras clave: educación, IA, ética."

    normalized = normalize_text(text)

    assert "Palabras clave" not in normalized
    assert normalized.endswith("suficiente.")
