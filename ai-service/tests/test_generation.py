from __future__ import annotations

import pytest

from training.generate_llamacpp import format_artifacts, trim_to_complete_sentence


def test_keeps_text_that_already_ends_with_a_sentence() -> None:
    text = "El estudio analiza la muestra. Los resultados son positivos."

    assert trim_to_complete_sentence(text) == text


def test_trims_a_truncated_word() -> None:
    text = "El estudio analiza la muestra. Los resultados son positi"

    assert trim_to_complete_sentence(text) == "El estudio analiza la muestra."


def test_trims_a_truncated_sentence_without_final_period() -> None:
    text = "Primera oración. Segunda oración que quedó incompleta"

    assert trim_to_complete_sentence(text) == "Primera oración."


def test_keeps_closing_quote_after_the_period() -> None:
    text = 'El autor concluye que "el efecto es significativo."'

    assert trim_to_complete_sentence(text) == text


def test_returns_empty_when_there_is_no_complete_sentence() -> None:
    assert trim_to_complete_sentence("texto sin puntuación final") == ""


@pytest.mark.parametrize("text", ["", "   "])
def test_handles_blank_text(text: str) -> None:
    assert trim_to_complete_sentence(text) == ""


def test_clean_academic_prose_has_no_artifacts() -> None:
    text = (
        "El estudio analiza una muestra de 240 estudiantes durante el semestre. "
        "Los resultados muestran una mejora sostenida en la comprensión lectora."
    )

    assert format_artifacts(text, "Comprensión lectora en educación superior") == []


@pytest.mark.parametrize(
    ("text", "reason"),
    [
        ("**Objetivo:** analizar el efecto.", "markdown"),
        ("Objetivo: analizar el efecto del programa.", "etiqueta"),
        ("Metodología: se aplicó una encuesta.", "etiqueta"),
        ("El resumen del artículo es el siguiente: se analiza.", "meta"),
        (
            "Comprensión lectora en educación superior: el estudio analiza el tema.",
            "titulo",
        ),
    ],
)
def test_format_artifacts_are_detected(text: str, reason: str) -> None:
    assert reason in format_artifacts(
        text, "Comprensión lectora en educación superior"
    )


def test_title_mentioned_mid_sentence_is_not_an_artifact() -> None:
    # Un resumen humano puede retomar el tema con las palabras del título sin
    # que sea una repetición tipo encabezado.
    text = "El estudio sobre comprensión lectora en educación superior analiza el tema."

    assert "titulo" not in format_artifacts(
        text, "Comprensión lectora en educación superior"
    )
