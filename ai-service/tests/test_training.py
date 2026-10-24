from __future__ import annotations

import numpy as np

from training.train import choose_thresholds, decision_metrics


def test_high_threshold_does_not_use_a_mid_tie_shortcut() -> None:
    # El grupo de 1.0 contiene 4 aciertos y 1 falso positivo: precisión 0.8.
    # Un umbral calculado a mitad del empate reportaría 1.0 de forma engañosa.
    labels = np.array([0, 1, 1, 1, 1, 0, 0, 0, 0, 0])
    probabilities = np.array([1.0, 1.0, 1.0, 1.0, 1.0, 0.4, 0.3, 0.2, 0.1, 0.0])

    thresholds = choose_thresholds(labels, probabilities, target_precision=0.9)

    assert thresholds["valid"] is False
    assert thresholds["high_decisions"] == 0


def test_high_threshold_finds_lower_threshold_with_ties() -> None:
    labels = np.array([0, 1, 1, 1, 1] + [1] * 10 + [0] * 5)
    probabilities = np.array([1.0] * 5 + [0.5] * 10 + [0.0] * 5)

    thresholds = choose_thresholds(labels, probabilities, target_precision=0.9)

    assert thresholds["high"] == 0.5
    assert thresholds["high_decisions"] == 15
    assert thresholds["high_precision"] == round(14 / 15, 4)
    assert thresholds["valid"] is True

    selected = probabilities >= thresholds["high"]
    assert selected.sum() == thresholds["high_decisions"]
    assert round(float(labels[selected].mean()), 4) == thresholds["high_precision"]


def test_low_threshold_uses_whole_tie_group() -> None:
    labels = np.array([0, 0, 0, 0, 1, 1, 1, 1])
    probabilities = np.array([0.0, 0.0, 0.0, 0.05, 0.1, 0.5, 0.9, 1.0])

    thresholds = choose_thresholds(labels, probabilities, target_precision=0.9)

    assert thresholds["low"] == 0.05
    assert thresholds["low_precision"] == 1.0
    assert thresholds["low_decisions"] == 4

    selected = probabilities <= thresholds["low"]
    assert selected.sum() == thresholds["low_decisions"]
    assert (1 - labels[selected]).mean() == thresholds["low_precision"]


def test_thresholds_are_invalid_when_target_is_unreachable() -> None:
    labels = np.array([0, 0, 1, 1])
    probabilities = np.array([1.0, 0.9, 0.1, 0.0])

    thresholds = choose_thresholds(labels, probabilities, target_precision=0.9)

    assert thresholds["valid"] is False
    assert thresholds["high_decisions"] == 0
    assert thresholds["low_decisions"] == 0


def test_decision_metrics_abstains_when_thresholds_are_invalid() -> None:
    labels = np.array([1, 0, 1, 0])
    probabilities = np.array([0.9, 0.1, 0.8, 0.2])

    metrics = decision_metrics(
        labels,
        probabilities,
        {"high": 0.65, "low": 0.35, "valid": False},
    )

    assert metrics["decided"] == 0
    assert metrics["abstained"] == 4
    assert metrics["coverage"] == 0.0
    assert metrics["precision"] is None


def test_decision_metrics_matches_threshold_rule() -> None:
    labels = np.array([1, 1, 0, 0, 1, 0])
    probabilities = np.array([0.95, 0.85, 0.6, 0.4, 0.2, 0.05])
    thresholds = {"high": 0.8, "low": 0.1, "valid": True}

    metrics = decision_metrics(labels, probabilities, thresholds)

    # 0.95 y 0.85 son IA; 0.05 es humano; 0.60, 0.40 y 0.20 se abstienen.
    assert metrics["decided"] == 3
    assert metrics["abstained"] == 3
    assert metrics["coverage"] == 0.5
    assert metrics["precision"] == 1.0
    assert metrics["recall"] == 1.0
    assert metrics["accuracy"] == 1.0


def test_crossing_thresholds_collapse_to_a_single_cut() -> None:
    # Con un modelo muy separado, el corte que alcanza la precisión objetivo
    # para IA y el de humano pueden solaparse. Eso no invalida el detector:
    # se usa un único corte y no queda banda de abstención.
    labels = np.array([1] * 100 + [1] * 5 + [0] * 5 + [0] * 100)
    probabilities = np.array([1.0] * 100 + [0.5] * 10 + [0.0] * 100)

    thresholds = choose_thresholds(labels, probabilities, target_precision=0.9)

    assert thresholds["valid"] is True
    assert thresholds["high"] == 0.5
    assert thresholds["low"] == 0.0
    assert thresholds["high_precision"] >= 0.9
    assert thresholds["low_precision"] >= 0.9

    metrics = decision_metrics(labels, probabilities, thresholds)

    assert metrics["coverage"] == 1.0
    assert metrics["abstained"] == 0
    assert metrics["precision"] == round(105 / 110, 4)
