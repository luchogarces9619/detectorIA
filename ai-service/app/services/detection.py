"""Inferencia del detector calibrado de SciTrace AI.

El artefacto se carga desde disco, se verifica su SHA-256 contra la tarjeta del
modelo y se mantiene en memoria. Si falta el artefacto o no coincide, el
servicio declara el detector como no disponible en lugar de inventar una
predicción.
"""

from __future__ import annotations

import hashlib
import json
import logging
import threading
from collections.abc import Sequence
from dataclasses import dataclass
from pathlib import Path
from typing import Any

import joblib
import numpy as np

from app.core.config import Settings
from app.services.chunking import MIN_SCORABLE_WORDS, chunk_text
from app.services.normalize import normalize_text

logger = logging.getLogger(__name__)

HUMAN_LABEL = "human"
AI_LABEL = "ai"
UNCERTAIN_LABEL = "uncertain"
CONCLUSIONS = ("not_conclusive", "no_signals", "review_suggested", "mixed_signals")


class DetectorUnavailable(RuntimeError):
    """El artefacto no existe, está corrupto o no supera la verificación."""


@dataclass(frozen=True)
class DetectorStatus:
    ready: bool
    name: str | None = None
    version: str | None = None
    reason: str | None = None


@dataclass(frozen=True)
class SegmentScore:
    index: int
    score: float
    label: str
    confidence: str
    requires_review: bool
    word_count: int
    chunk_count: int


@dataclass(frozen=True)
class DocumentScore:
    aggregate_score: float | None
    conclusion: str
    confidence: str
    coverage: float
    segments: list[SegmentScore]
    model_version: str | None


def _sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for block in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(block)
    return digest.hexdigest()


def _segment_label(score: float, high: float, low: float) -> str:
    if score >= high:
        return AI_LABEL
    if score <= low:
        return HUMAN_LABEL
    return UNCERTAIN_LABEL


def _segment_confidence(score: float, label: str, word_count: int) -> str:
    # Una abstención nunca puede presentarse como confianza alta: la etiqueta
    # ya indica que el modelo no distingue entre las dos clases.
    if label == UNCERTAIN_LABEL or word_count < MIN_SCORABLE_WORDS:
        return "low"
    if score >= 0.9 or score <= 0.1:
        return "high"
    return "medium"


class Detector:
    """Clasificador TF-IDF + regresión logística con calibración isotónica."""

    def __init__(
        self,
        *,
        model_dir: Path,
        bundle: dict[str, Any],
        artifact_sha256: str,
    ) -> None:
        self.model_dir = model_dir
        self.name = str(bundle.get("name") or "scitrace-es-academic-detector")
        self.version = str(bundle.get("version") or "0.0.0")
        self.artifact_sha256 = artifact_sha256
        self.model = bundle["model"]
        self.calibrator = bundle["calibrator"]
        self.thresholds = dict(bundle["thresholds"])
        self.metrics = dict(bundle.get("metrics") or {})
        self.dataset = dict(bundle.get("dataset") or {})
        self.high = float(self.thresholds["high"])
        self.low = float(self.thresholds["low"])
        # Si los umbrales no alcanzaron la precisión objetivo, el detector se
        # abstiene en lugar de decidir con valores arbitrarios.
        self.thresholds_valid = bool(self.thresholds.get("valid", True))

    def score_texts(self, texts: Sequence[str]) -> list[SegmentScore]:
        """Puntúa cada fragmento con una probabilidad calibrada de ser IA."""
        scores: list[SegmentScore] = []

        for index, text in enumerate(texts):
            words = len(text.split())
            chunks = chunk_text(normalize_text(text))

            if not chunks:
                scores.append(
                    SegmentScore(
                        index=index,
                        score=0.5,
                        label=UNCERTAIN_LABEL,
                        confidence="insufficient",
                        requires_review=True,
                        word_count=words,
                        chunk_count=0,
                    )
                )
                continue

            if not self.thresholds_valid:
                scores.append(
                    SegmentScore(
                        index=index,
                        score=0.5,
                        label=UNCERTAIN_LABEL,
                        confidence="insufficient",
                        requires_review=True,
                        word_count=words,
                        chunk_count=len(chunks),
                    )
                )
                continue

            raw = self.model.predict_proba(chunks)[:, 1]
            calibrated = np.clip(self.calibrator.predict(raw), 0.0, 1.0)
            score = float(np.mean(calibrated))
            label = _segment_label(score, self.high, self.low)

            scores.append(
                SegmentScore(
                    index=index,
                    score=round(score, 5),
                    label=label,
                    confidence=_segment_confidence(score, label, words),
                    requires_review=label == UNCERTAIN_LABEL,
                    word_count=words,
                    chunk_count=len(chunks),
                )
            )

        return scores

    def score_document(self, texts: Sequence[str]) -> DocumentScore:
        """Agrega los fragmentos en una conclusión orientativa del documento."""
        segments = self.score_texts(texts)
        scorable = [
            segment
            for segment in segments
            if segment.chunk_count > 0 and self.thresholds_valid
        ]

        if not scorable:
            return DocumentScore(
                aggregate_score=None,
                conclusion="not_conclusive",
                confidence="insufficient",
                coverage=0.0,
                segments=segments,
                model_version=self.version,
            )

        aggregate = float(np.mean([segment.score for segment in scorable]))
        decided = [segment for segment in scorable if segment.label != UNCERTAIN_LABEL]
        coverage = len(decided) / len(scorable)
        ai_share = sum(1 for s in scorable if s.label == AI_LABEL) / len(scorable)
        human_share = sum(1 for s in scorable if s.label == HUMAN_LABEL) / len(scorable)

        if ai_share >= 0.6 and aggregate >= self.high:
            conclusion = "review_suggested"
        elif human_share >= 0.8 and aggregate <= self.low:
            conclusion = "no_signals"
        elif ai_share > 0 and human_share > 0:
            conclusion = "mixed_signals"
        else:
            conclusion = "not_conclusive"

        if coverage == 0:
            confidence = "insufficient"
        elif coverage >= 0.8 and (aggregate >= 0.9 or aggregate <= 0.1):
            confidence = "high"
        elif coverage >= 0.6:
            confidence = "medium"
        else:
            confidence = "low"

        return DocumentScore(
            aggregate_score=round(aggregate, 5),
            conclusion=conclusion,
            confidence=confidence,
            coverage=round(coverage, 4),
            segments=segments,
            model_version=self.version,
        )


_cache_lock = threading.Lock()
_cache: dict[str, tuple[float, Detector]] = {}


def load_detector(model_dir: Path) -> Detector:
    """Carga y verifica el artefacto. No usa caché."""
    model_dir = model_dir.resolve()
    artifact = model_dir / "model.joblib"
    card_path = model_dir / "card.json"

    if not artifact.is_file():
        raise DetectorUnavailable(f"No existe el artefacto en {artifact}.")
    if not card_path.is_file():
        raise DetectorUnavailable(f"No existe la tarjeta del modelo en {card_path}.")

    card = json.loads(card_path.read_text(encoding="utf-8"))
    expected = str(card.get("artifact_sha256", ""))
    actual = _sha256(artifact)

    if not expected or actual != expected:
        raise DetectorUnavailable("El SHA-256 del artefacto no coincide con la tarjeta.")

    bundle = joblib.load(artifact)
    return Detector(model_dir=model_dir, bundle=bundle, artifact_sha256=actual)


def get_detector(model_dir: Path) -> Detector:
    """Devuelve una instancia cacheada; recarga si cambia el artefacto."""
    resolved = model_dir.resolve()
    key = str(resolved)
    artifact = resolved / "model.joblib"
    mtime = artifact.stat().st_mtime if artifact.is_file() else 0.0

    with _cache_lock:
        cached = _cache.get(key)
        if cached is not None and cached[0] == mtime:
            return cached[1]

        detector = load_detector(resolved)
        _cache[key] = (mtime, detector)
        logger.info(
            "Detector cargado: %s v%s (%s)",
            detector.name,
            detector.version,
            artifact.name,
        )
        return detector


def reset_cache() -> None:
    """Vacía la caché de artefactos (útil en pruebas y recargas)."""
    with _cache_lock:
        _cache.clear()


def detector_status(settings: Settings) -> DetectorStatus:
    """Estado real del detector según el artefacto disponible."""
    if not settings.detector_enabled or not settings.detector_ready:
        return DetectorStatus(False, reason="El detector está deshabilitado por configuración.")

    try:
        detector = get_detector(Path(settings.model_dir))
    except DetectorUnavailable as exc:
        return DetectorStatus(False, reason=str(exc))
    except Exception as exc:  # noqa: BLE001 - frontera: un artefacto corrupto no debe tumbar /health
        logger.warning("No se pudo cargar el detector: %s", exc)
        return DetectorStatus(False, reason="El artefacto del detector no es válido.")

    return DetectorStatus(True, name=detector.name, version=detector.version)
