"""Particiones deterministas del corpus.

La partición de prueba se reserva por revista para medir generalización a
revistas no vistas. Las contrapartes sintéticas heredan la partición de su
artículo humano, de modo que un mismo tema nunca aparece en dos particiones.
"""

from __future__ import annotations

import hashlib


def stable_fraction(value: str) -> float:
    digest = hashlib.sha256(value.encode("utf-8")).hexdigest()
    return int(digest[:8], 16) / 0xFFFFFFFF


def split_for(group: str, pair_id: str, *, seed: int) -> str:
    """Devuelve train, calib, threshold o test."""
    if stable_fraction(f"test:{seed}:{group}") < 0.2:
        return "test"

    draw = stable_fraction(f"dev:{seed}:{pair_id}")
    if draw < 0.70:
        return "train"
    if draw < 0.85:
        return "calib"
    return "threshold"
