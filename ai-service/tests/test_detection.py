from __future__ import annotations

import hashlib
import json
from pathlib import Path

import joblib
import pytest
from fastapi.testclient import TestClient
from pydantic import SecretStr
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.isotonic import IsotonicRegression
from sklearn.linear_model import LogisticRegression
from sklearn.pipeline import Pipeline

from app.core.config import Settings
from app.main import create_app
from app.services.detection import (
    DetectorUnavailable,
    _segment_confidence,
    load_detector,
    reset_cache,
)

TEST_TOKEN = "test-token-" + ("x" * 40)

HUMAN_DOCS = [
    "El estudio analiza una muestra de estudiantes universitarios y describe los datos "
    "recogidos durante el semestre académico.",
    "La investigación revisa expedientes y compara los resultados con la literatura previa "
    "sobre el tema.",
    "Se aplicó una encuesta a docentes de secundaria y se registraron las respuestas en una "
    "base codificada.",
    "El artículo presenta un análisis documental de fuentes primarias consultadas en archivos "
    "locales.",
    "Los autores describen el procedimiento seguido para seleccionar la muestra y justifican "
    "cada decisión metodológica.",
    "La discusión contrasta los hallazgos con estudios anteriores y reconoce las limitaciones "
    "del diseño.",
]

AI_DOCS = [
    "En conclusión, es fundamental destacar la importancia de este tema para el desarrollo "
    "integral de la sociedad moderna.",
    "Es importante señalar que este fenómeno representa un desafío significativo en el contexto "
    "actual y futuro.",
    "Cabe mencionar que la implementación de estas estrategias contribuye a optimizar los "
    "procesos y mejorar los resultados.",
    "En este sentido, resulta crucial comprender la relevancia de este aspecto para alcanzar "
    "los objetivos propuestos.",
    "Por otro lado, es esencial considerar que la colaboración entre los actores involucrados "
    "potencia el impacto positivo.",
    "En definitiva, este enfoque integral permite abordar la problemática desde una perspectiva "
    "más amplia y sostenible.",
]

HUMAN_TEXT = " ".join(HUMAN_DOCS[:4])
AI_TEXT = " ".join(AI_DOCS[:4])


def build_tiny_model(
    directory: Path, thresholds: dict[str, object] | None = None
) -> Path:
    """Crea un artefacto mínimo con el mismo formato que el modelo real."""
    directory.mkdir(parents=True, exist_ok=True)
    texts = HUMAN_DOCS + AI_DOCS
    labels = [0] * len(HUMAN_DOCS) + [1] * len(AI_DOCS)

    model = Pipeline(
        steps=[
            ("features", TfidfVectorizer(ngram_range=(1, 2), min_df=1)),
            (
                "classifier",
                LogisticRegression(max_iter=1_000, solver="liblinear"),
            ),
        ]
    )
    model.fit(texts, labels)

    raw = model.predict_proba(texts)[:, 1]
    calibrator = IsotonicRegression(out_of_bounds="clip", y_min=0.0, y_max=1.0)
    calibrator.fit(raw, labels)

    bundle = {
        "name": "tiny-test-detector",
        "version": "test-1",
        "model": model,
        "calibrator": calibrator,
        "thresholds": thresholds or {"high": 0.65, "low": 0.35},
        "metrics": {},
        "dataset": {},
    }

    artifact = directory / "model.joblib"
    joblib.dump(bundle, artifact, compress=3)
    digest = hashlib.sha256(artifact.read_bytes()).hexdigest()

    (directory / "card.json").write_text(
        json.dumps(
            {
                "name": "tiny-test-detector",
                "version": "test-1",
                "artifact_sha256": digest,
            }
        ),
        encoding="utf-8",
    )

    return directory


def test_detector_labels_ai_and_human(tmp_path: Path) -> None:
    detector = load_detector(build_tiny_model(tmp_path / "model"))

    ai_score = detector.score_texts([AI_TEXT])[0]
    human_score = detector.score_texts([HUMAN_TEXT])[0]

    assert ai_score.label == "ai"
    assert ai_score.score >= detector.high
    assert human_score.label == "human"
    assert human_score.score <= detector.low


def test_detector_aggregates_documents(tmp_path: Path) -> None:
    detector = load_detector(build_tiny_model(tmp_path / "model"))

    ai_document = detector.score_document([AI_TEXT, AI_TEXT])
    human_document = detector.score_document([HUMAN_TEXT, HUMAN_TEXT])
    mixed_document = detector.score_document([AI_TEXT, HUMAN_TEXT])

    assert ai_document.conclusion == "review_suggested"
    assert human_document.conclusion == "no_signals"
    assert mixed_document.conclusion == "mixed_signals"
    assert ai_document.model_version == "test-1"


def test_detector_marks_short_text_as_insufficient(tmp_path: Path) -> None:
    detector = load_detector(build_tiny_model(tmp_path / "model"))

    result = detector.score_document(["Texto demasiado corto."])

    assert result.conclusion == "not_conclusive"
    assert result.confidence == "insufficient"
    assert result.aggregate_score is None


def test_detector_rejects_tampered_artifact(tmp_path: Path) -> None:
    model_dir = build_tiny_model(tmp_path / "model")
    with (model_dir / "model.joblib").open("ab") as handle:
        handle.write(b"tampered")

    with pytest.raises(DetectorUnavailable):
        load_detector(model_dir)


def test_detector_abstains_when_thresholds_are_invalid(tmp_path: Path) -> None:
    model_dir = build_tiny_model(
        tmp_path / "model",
        thresholds={"high": 0.65, "low": 0.35, "valid": False},
    )
    detector = load_detector(model_dir)

    result = detector.score_document([AI_TEXT, HUMAN_TEXT])

    assert result.conclusion == "not_conclusive"
    assert result.confidence == "insufficient"
    assert result.aggregate_score is None
    assert all(segment.label == "uncertain" for segment in result.segments)
    assert all(segment.confidence == "insufficient" for segment in result.segments)


def test_uncertain_segment_is_never_high_confidence() -> None:
    assert _segment_confidence(0.95, "uncertain", 100) == "low"
    assert _segment_confidence(0.95, "ai", 100) == "high"
    assert _segment_confidence(0.70, "ai", 100) == "medium"


def test_detect_endpoint_requires_available_model(
    client: TestClient, auth_headers: dict[str, str]
) -> None:
    response = client.post(
        "/internal/v1/detect",
        headers=auth_headers,
        json={"segments": [AI_TEXT]},
    )

    assert response.status_code == 503


def test_detect_endpoint_returns_calibrated_scores(tmp_path: Path) -> None:
    reset_cache()
    model_dir = build_tiny_model(tmp_path / "model")
    settings = Settings(
        environment="test",
        internal_token=SecretStr(TEST_TOKEN),
        detector_ready=True,
        model_dir=str(model_dir),
        _env_file=None,
    )

    with TestClient(create_app(settings)) as client:
        response = client.post(
            "/internal/v1/detect",
            headers={"X-Internal-Token": TEST_TOKEN},
            json={"segments": [AI_TEXT, HUMAN_TEXT]},
        )

    assert response.status_code == 200
    body = response.json()
    assert body["model"]["version"] == "test-1"
    assert body["model"]["high_threshold"] == 0.65
    assert body["document"]["conclusion"] == "mixed_signals"
    assert len(body["segments"]) == 2
    assert body["segments"][0]["label"] == "ai"
    assert body["segments"][1]["label"] == "human"
    assert "orientativo" in body["disclaimer"]
