"""Entrena, calibra y evalúa el detector de texto generado de SciTrace AI.

El flujo es deliberadamente conservador:

1. Selección de hiperparámetros con validación cruzada agrupada por revista.
2. Ajuste final sobre ``train``.
3. Calibración isotónica sobre ``calib``.
4. Elección de umbrales de decisión y abstención sobre ``threshold``.
5. Evaluación única sobre ``test`` (revistas no vistas).

Ninguna métrica se escribe a mano: todas provienen de esta ejecución.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import platform
import random
from collections import Counter
from datetime import UTC, datetime
from pathlib import Path
from typing import Any

import joblib
import numpy as np
import sklearn
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.isotonic import IsotonicRegression
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import (
    accuracy_score,
    average_precision_score,
    brier_score_loss,
    f1_score,
    log_loss,
    precision_recall_fscore_support,
    roc_auc_score,
)
from sklearn.model_selection import GroupKFold
from sklearn.pipeline import FeatureUnion, Pipeline

from app.services.chunking import MAX_CHUNK_WORDS, MIN_CHUNK_WORDS, chunk_text
from app.services.normalize import normalize_text
from training.splits import split_for

DEFAULT_SEED = 20261023


def read_jsonl(path: Path) -> list[dict[str, Any]]:
    with path.open("r", encoding="utf-8") as handle:
        return [json.loads(line) for line in handle if line.strip()]


def file_sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for block in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(block)
    return digest.hexdigest()


def build_pairs(
    human_records: list[dict[str, Any]],
    ai_records: list[dict[str, Any]],
    *,
    seed: int,
) -> list[dict[str, Any]]:
    """Empareja cada texto humano con su contraparte sintética."""
    ai_by_pair: dict[str, list[dict[str, Any]]] = {}
    for record in ai_records:
        ai_by_pair.setdefault(record["pair_id"], []).append(record)

    pairs: list[dict[str, Any]] = []
    for human in human_records:
        candidates = ai_by_pair.get(human["id"], [])
        if not candidates:
            continue

        split = split_for(str(human["group"]), human["id"], seed=seed)
        pairs.append(
            {
                "pair_id": human["id"],
                "group": human["group"],
                "split": split,
                "human": human,
                "ai": candidates[0],
            }
        )

    return pairs


def to_chunks(pairs: list[dict[str, Any]], split: str) -> tuple[list[str], np.ndarray, list[str]]:
    texts: list[str] = []
    labels: list[int] = []
    groups: list[str] = []

    for pair in pairs:
        if pair["split"] != split:
            continue
        for label, key in ((0, "human"), (1, "ai")):
            for chunk in chunk_text(normalize_text(pair[key]["text"])):
                texts.append(chunk)
                labels.append(label)
                groups.append(pair["group"])

    return texts, np.asarray(labels, dtype=np.int64), groups


def build_pipeline(c_value: float) -> Pipeline:
    return Pipeline(
        steps=[
            (
                "features",
                FeatureUnion(
                    transformer_list=[
                        (
                            "word",
                            TfidfVectorizer(
                                analyzer="word",
                                ngram_range=(1, 2),
                                min_df=2,
                                max_df=0.9,
                                sublinear_tf=True,
                                strip_accents="unicode",
                                max_features=80_000,
                            ),
                        ),
                        (
                            "char",
                            TfidfVectorizer(
                                analyzer="char_wb",
                                ngram_range=(3, 5),
                                min_df=3,
                                sublinear_tf=True,
                                strip_accents="unicode",
                                max_features=120_000,
                            ),
                        ),
                    ]
                ),
            ),
            (
                "classifier",
                LogisticRegression(
                    C=c_value,
                    max_iter=3_000,
                    solver="liblinear",
                    class_weight="balanced",
                    random_state=DEFAULT_SEED,
                ),
            ),
        ]
    )


def select_c(
    texts: list[str],
    labels: np.ndarray,
    groups: list[str],
    candidates: list[float],
) -> tuple[float, dict[str, float]]:
    """Elige C por log-loss medio en validación cruzada agrupada."""
    unique_groups = sorted(set(groups))
    n_splits = min(3, len(unique_groups))
    if n_splits < 2:
        return candidates[0], {str(candidates[0]): float("nan")}

    splitter = GroupKFold(n_splits=n_splits)
    scores: dict[str, float] = {}

    for candidate in candidates:
        fold_scores: list[float] = []
        for train_index, valid_index in splitter.split(texts, labels, groups):
            model = build_pipeline(candidate)
            model.fit([texts[i] for i in train_index], labels[train_index])
            probabilities = model.predict_proba([texts[i] for i in valid_index])[:, 1]
            fold_scores.append(log_loss(labels[valid_index], probabilities, labels=[0, 1]))
        scores[str(candidate)] = float(np.mean(fold_scores))

    best = min(candidates, key=lambda value: scores[str(value)])
    return best, scores


def calibration_report(
    labels: np.ndarray,
    probabilities: np.ndarray,
    bins: int = 10,
) -> dict[str, Any]:
    """Métricas de calibración y discriminación."""
    clipped = np.clip(probabilities, 1e-6, 1 - 1e-6)
    edges = np.linspace(0.0, 1.0, bins + 1)
    ece = 0.0
    rows: list[dict[str, Any]] = []

    for index in range(bins):
        low, high = edges[index], edges[index + 1]
        mask = (probabilities >= low) & (
            probabilities < high if index < bins - 1 else probabilities <= high
        )
        if not mask.any():
            continue
        confidence = float(probabilities[mask].mean())
        accuracy = float(labels[mask].mean())
        weight = float(mask.mean())
        ece += weight * abs(accuracy - confidence)
        rows.append(
            {
                "bin": [round(low, 2), round(high, 2)],
                "count": int(mask.sum()),
                "mean_score": round(confidence, 4),
                "observed_ai_rate": round(accuracy, 4),
            }
        )

    return {
        "roc_auc": round(float(roc_auc_score(labels, probabilities)), 4),
        "pr_auc": round(float(average_precision_score(labels, probabilities)), 4),
        "brier": round(float(brier_score_loss(labels, probabilities)), 4),
        "log_loss": round(float(log_loss(labels, clipped, labels=[0, 1])), 4),
        "ece_10": round(float(ece), 4),
        "accuracy_at_050": round(float(accuracy_score(labels, probabilities >= 0.5)), 4),
        "f1_at_050": round(float(f1_score(labels, probabilities >= 0.5)), 4),
        "count": int(len(labels)),
        "ai_rate": round(float(labels.mean()), 4),
        "reliability": rows,
    }


def choose_thresholds(
    labels: np.ndarray,
    probabilities: np.ndarray,
    *,
    target_precision: float = 0.9,
) -> dict[str, Any]:
    """Busca umbrales que alcancen la precisión objetivo con la mayor cobertura.

    Las probabilidades calibradas contienen muchos empates (0.0 y 1.0), así que
    cada umbral se evalúa solo al final de un grupo de valores iguales. De este
    modo la precisión reportada coincide exactamente con la regla de decisión
    ``score >= high`` / ``score <= low``.
    """
    total = len(labels)
    positives = int(labels.sum())
    negatives = total - positives

    ascending = np.argsort(probabilities, kind="mergesort")
    sorted_probabilities = probabilities[ascending]
    sorted_labels = labels[ascending]

    cumulative_positive = np.cumsum(sorted_labels)
    cumulative_negative = np.cumsum(1 - sorted_labels)

    high_threshold = 1.0
    high_precision = 0.0
    high_recall = 0.0
    high_selected = 0

    for index in range(total - 1, -1, -1):
        if index > 0 and sorted_probabilities[index] == sorted_probabilities[index - 1]:
            continue
        selected = total - index
        found = int(
            cumulative_positive[-1]
            - (cumulative_positive[index - 1] if index > 0 else 0)
        )
        if found == 0:
            continue
        precision = found / selected
        if precision >= target_precision:
            high_threshold = float(sorted_probabilities[index])
            high_precision = precision
            high_recall = found / positives if positives else 0.0
            high_selected = selected

    low_threshold = 0.0
    low_precision = 0.0
    low_recall = 0.0
    low_selected = 0

    for index in range(total):
        if index + 1 < total and sorted_probabilities[index] == sorted_probabilities[index + 1]:
            continue
        selected = index + 1
        found = int(cumulative_negative[index])
        if found == 0:
            continue
        precision = found / selected
        if precision >= target_precision:
            low_threshold = float(sorted_probabilities[index])
            low_precision = precision
            low_recall = found / negatives if negatives else 0.0
            low_selected = selected

    if high_selected > 0 and low_selected > 0 and high_threshold <= low_threshold:
        # Los dos umbrales se cruzaron: cada clase alcanza la precisión objetivo
        # con un corte que se solapa con el de la otra. Eso no invalida el
        # modelo; significa que no hace falta banda de abstención. Se colapsa a
        # un único corte en ``high`` y se baja ``low`` al valor inmediatamente
        # inferior, de modo que toda puntuación quede decidida sin solapar
        # reglas. La precisión del lado humano se recalcula con el corte final.
        below = sorted_probabilities[sorted_probabilities < high_threshold]
        low_threshold = float(below[-1]) if below.size else 0.0
        low_selected = int((probabilities <= low_threshold).sum())
        found = int((labels[probabilities <= low_threshold] == 0).sum())
        low_precision = found / low_selected if low_selected else 0.0
        low_recall = found / negatives if negatives else 0.0

    if high_threshold <= low_threshold:
        # Sin umbrales utilizables: el detector debe abstenerse siempre.
        high_threshold = 0.65
        low_threshold = 0.35
        high_precision = low_precision = 0.0
        high_recall = low_recall = 0.0
        high_selected = low_selected = 0

    valid = (
        high_selected > 0
        and low_selected > 0
        and high_threshold > low_threshold
    )

    return {
        "high": round(high_threshold, 4),
        "low": round(low_threshold, 4),
        "valid": valid,
        "target_precision": target_precision,
        "high_precision": round(high_precision, 4),
        "high_recall": round(high_recall, 4),
        "high_decisions": high_selected,
        "low_precision": round(low_precision, 4),
        "low_recall": round(low_recall, 4),
        "low_decisions": low_selected,
    }


def decision_metrics(
    labels: np.ndarray,
    probabilities: np.ndarray,
    thresholds: dict[str, Any],
) -> dict[str, Any]:
    if not thresholds.get("valid", True):
        # Sin umbrales válidos el detector se abstiene siempre; las métricas
        # deben reflejar ese comportamiento y no decidir con valores de reserva.
        return {
            "coverage": 0.0,
            "abstention_rate": 1.0,
            "abstained": int(len(labels)),
            "decided": 0,
            "accuracy": None,
            "precision": None,
            "recall": None,
            "f1": None,
        }

    decisions = np.where(
        probabilities >= thresholds["high"],
        1,
        np.where(probabilities <= thresholds["low"], 0, -1),
    )
    decided = decisions >= 0
    abstained = int((~decided).sum())

    result: dict[str, Any] = {
        "coverage": round(float(decided.mean()), 4),
        "abstention_rate": round(float(1 - decided.mean()), 4),
        "abstained": abstained,
        "decided": int(decided.sum()),
    }

    if decided.any():
        precision, recall, f1, _ = precision_recall_fscore_support(
            labels[decided], decisions[decided], average="binary", zero_division=0
        )
        result.update(
            {
                "accuracy": round(float(accuracy_score(labels[decided], decisions[decided])), 4),
                "precision": round(float(precision), 4),
                "recall": round(float(recall), 4),
                "f1": round(float(f1), 4),
            }
        )
    else:
        result.update({"accuracy": None, "precision": None, "recall": None, "f1": None})

    return result


def aggregate_documents(
    pairs: list[dict[str, Any]],
    split: str,
    model: Any,
    calibrator: IsotonicRegression,
) -> tuple[np.ndarray, np.ndarray]:
    labels: list[int] = []
    scores: list[float] = []

    for pair in pairs:
        if pair["split"] != split:
            continue
        for label, key in ((0, "human"), (1, "ai")):
            chunks = chunk_text(normalize_text(pair[key]["text"]))
            if not chunks:
                continue
            raw = model.predict_proba(chunks)[:, 1]
            calibrated = calibrator.predict(raw)
            labels.append(label)
            scores.append(float(np.mean(calibrated)))

    return np.asarray(labels, dtype=np.int64), np.asarray(scores, dtype=np.float64)


def evaluate_split(
    pairs: list[dict[str, Any]],
    split: str,
    model: Any,
    calibrator: IsotonicRegression,
    thresholds: dict[str, Any],
) -> dict[str, Any]:
    texts, labels, _ = to_chunks(pairs, split)
    raw = model.predict_proba(texts)[:, 1]
    calibrated = calibrator.predict(raw)

    doc_labels, doc_scores = aggregate_documents(pairs, split, model, calibrator)

    return {
        "chunk_level": calibration_report(labels, calibrated),
        "chunk_decisions": decision_metrics(labels, calibrated, thresholds),
        "document_level": calibration_report(doc_labels, doc_scores),
        "document_decisions": decision_metrics(doc_labels, doc_scores, thresholds),
    }


def main() -> None:
    parser = argparse.ArgumentParser(description="Entrena el detector de SciTrace AI.")
    parser.add_argument("--human", type=Path, default=Path("data/human.jsonl"))
    parser.add_argument("--ai", type=Path, default=Path("data/ai.jsonl"))
    parser.add_argument("--ood-ai", type=Path, default=None)
    parser.add_argument("--output", type=Path, default=Path("models/scitrace-es-v1"))
    parser.add_argument("--version", default="1.0.0")
    parser.add_argument("--seed", type=int, default=DEFAULT_SEED)
    args = parser.parse_args()

    random.seed(args.seed)
    np.random.seed(args.seed)

    human_records = read_jsonl(args.human)
    ai_records = read_jsonl(args.ai)
    pairs = build_pairs(human_records, ai_records, seed=args.seed)

    if not pairs:
        raise SystemExit("No hay pares humano/IA. Genera primero data/ai.jsonl.")

    split_counts = Counter(pair["split"] for pair in pairs)
    print(f"Pares: {len(pairs)} · {dict(split_counts)}")

    train_texts, train_labels, train_groups = to_chunks(pairs, "train")
    if len(set(train_labels)) < 2:
        raise SystemExit("El conjunto de entrenamiento necesita las dos clases.")

    for required_split in ("calib", "threshold", "test"):
        split_texts, split_labels, _ = to_chunks(pairs, required_split)
        if not split_texts or len(set(split_labels.tolist())) < 2:
            raise SystemExit(
                f"La partición '{required_split}' necesita fragmentos de ambas clases."
            )

    candidates = [0.5, 1.0, 2.0, 4.0, 8.0, 16.0, 32.0]
    best_c, cv_scores = select_c(train_texts, train_labels, train_groups, candidates)
    print(f"C elegido: {best_c} · log-loss CV: {cv_scores}")

    model = build_pipeline(best_c)
    model.fit(train_texts, train_labels)

    calib_texts, calib_labels, _ = to_chunks(pairs, "calib")
    calib_raw = model.predict_proba(calib_texts)[:, 1]
    calibrator = IsotonicRegression(out_of_bounds="clip", y_min=0.0, y_max=1.0)
    calibrator.fit(calib_raw, calib_labels)

    threshold_texts, threshold_labels, _ = to_chunks(pairs, "threshold")
    threshold_probabilities = calibrator.predict(model.predict_proba(threshold_texts)[:, 1])
    thresholds = choose_thresholds(threshold_labels, threshold_probabilities)
    print(f"Umbrales: {thresholds}")

    metrics: dict[str, Any] = {
        "calib": evaluate_split(pairs, "calib", model, calibrator, thresholds),
        "threshold": evaluate_split(pairs, "threshold", model, calibrator, thresholds),
        "test": evaluate_split(pairs, "test", model, calibrator, thresholds),
    }

    if args.ood_ai is not None:
        ood_records = read_jsonl(args.ood_ai)
        ood_by_pair: dict[str, dict[str, Any]] = {}
        for record in ood_records:
            ood_by_pair.setdefault(record["pair_id"], record)

        ood_pairs = []
        for pair in pairs:
            if pair["split"] != "test" or pair["pair_id"] not in ood_by_pair:
                continue
            ood_pairs.append(
                {
                    "pair_id": pair["pair_id"],
                    "group": pair["group"],
                    "split": "test",
                    "human": pair["human"],
                    "ai": ood_by_pair[pair["pair_id"]],
                }
            )

        if ood_pairs:
            metrics["ood"] = {
                "generator": ood_pairs[0]["ai"]["generator"]["model"],
                "pairs": len(ood_pairs),
                **evaluate_split(ood_pairs, "test", model, calibrator, thresholds),
            }

    args.output.mkdir(parents=True, exist_ok=True)

    config = {
        "model_family": "tfidf-logreg",
        "word_ngram_range": [1, 2],
        "char_ngram_range": [3, 5],
        "C": best_c,
        "cv_log_loss": cv_scores,
        "min_chunk_words": MIN_CHUNK_WORDS,
        "max_chunk_words": MAX_CHUNK_WORDS,
        "seed": args.seed,
        "calibration": "isotonic",
    }

    dataset_summary = {
        "pairs": len(pairs),
        "splits": dict(split_counts),
        "chunks": {
            "train": len(train_texts),
            "calib": len(calib_texts),
            "threshold": len(threshold_texts),
        },
        "human_sources": dict(
            Counter(pair["human"]["source"].get("license", "unknown") for pair in pairs)
        ),
        "journals": len({pair["group"] for pair in pairs}),
        "generators": dict(
            Counter(pair["ai"]["generator"]["model"] for pair in pairs)
        ),
        "human_file_sha256": file_sha256(args.human),
        "ai_file_sha256": file_sha256(args.ai),
        "created_at": datetime.now(UTC).isoformat(),
    }

    bundle = {
        "name": "scitrace-es-academic-detector",
        "version": args.version,
        "created_at": datetime.now(UTC).isoformat(),
        "model": model,
        "calibrator": calibrator,
        "thresholds": thresholds,
        "config": config,
        "metrics": metrics,
        "dataset": dataset_summary,
        "environment": {
            "python": platform.python_version(),
            "scikit_learn": sklearn.__version__,
            "numpy": np.__version__,
            "platform": platform.platform(),
        },
    }

    artifact = args.output / "model.joblib"
    joblib.dump(bundle, artifact, compress=3)

    (args.output / "metrics.json").write_text(
        json.dumps(metrics, ensure_ascii=False, indent=2),
        encoding="utf-8",
    )
    (args.output / "dataset.json").write_text(
        json.dumps(dataset_summary, ensure_ascii=False, indent=2),
        encoding="utf-8",
    )

    card = {
        "name": "scitrace-es-academic-detector",
        "version": args.version,
        "task": "detección de texto generado por IA en español académico",
        "artifact": artifact.name,
        "artifact_sha256": file_sha256(artifact),
        "artifact_bytes": artifact.stat().st_size,
        "config": config,
        "thresholds": thresholds,
        "dataset": dataset_summary,
        "metrics": metrics,
        "environment": bundle["environment"],
        "created_at": bundle["created_at"],
        "limitations": [
            (
                "Entrenado con resúmenes académicos y un generador local; no "
                "generaliza automáticamente a otros géneros ni a otros modelos."
            ),
            "La probabilidad es orientativa y no demuestra autoría.",
            (
                "Fuera del dominio de entrenamiento el resultado debe tratarse "
                "como no concluyente."
            ),
        ],
    }
    (args.output / "card.json").write_text(
        json.dumps(card, ensure_ascii=False, indent=2),
        encoding="utf-8",
    )

    print(json.dumps({"test": metrics["test"]}, ensure_ascii=False, indent=2))
    print(f"Artefacto: {artifact} ({artifact.stat().st_size} bytes)")


if __name__ == "__main__":
    main()
