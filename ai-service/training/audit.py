"""Auditoría del corpus antes de entrenar.

Comprueba de forma explícita lo que la documentación afirmará: idioma, tamaño,
procedencia, duplicados, emparejamiento y posible fuga de texto entre clases.

No corrige ni filtra nada de forma silenciosa: informa y devuelve un código de
salida distinto de cero si encuentra un problema bloqueante.
"""

from __future__ import annotations

import argparse
import collections
import json
import sys
from pathlib import Path
from typing import Any

import langid

from app.services.normalize import normalize_text
from training.generate_llamacpp import format_artifacts
from training.splits import split_for

SHINGLE_SIZE = 13
MAX_CONTAINMENT = 0.20


def read_jsonl(path: Path) -> list[dict[str, Any]]:
    with path.open("r", encoding="utf-8") as handle:
        return [json.loads(line) for line in handle if line.strip()]


def shingles(text: str, size: int = SHINGLE_SIZE) -> set[tuple[str, ...]]:
    words = normalize_text(text).lower().split()
    if len(words) < size:
        return set()
    return {tuple(words[index : index + size]) for index in range(len(words) - size + 1)}


def containment(left: set[tuple[str, ...]], right: set[tuple[str, ...]]) -> float:
    if not left or not right:
        return 0.0
    return len(left & right) / min(len(left), len(right))


def word_stats(values: list[int]) -> dict[str, int]:
    ordered = sorted(values)
    return {
        "min": ordered[0],
        "p25": ordered[len(ordered) // 4],
        "median": ordered[len(ordered) // 2],
        "p75": ordered[(3 * len(ordered)) // 4],
        "max": ordered[-1],
        "mean": round(sum(ordered) / len(ordered), 1),
    }


def audit(human_path: Path, ai_path: Path, *, seed: int) -> tuple[dict[str, Any], list[str]]:
    human = read_jsonl(human_path)
    ai = read_jsonl(ai_path)
    problems: list[str] = []

    human_ids = {record["id"] for record in human}
    human_by_id = {record["id"]: record for record in human}

    languages = collections.Counter(
        langid.classify(record["text"])[0] for record in human + ai
    )
    human_languages = collections.Counter(
        langid.classify(record["text"])[0] for record in human
    )
    ai_languages = collections.Counter(langid.classify(record["text"])[0] for record in ai)

    if human_languages.get("es", 0) != len(human):
        problems.append("El corpus humano contiene textos que no son español.")
    if ai_languages.get("es", 0) != len(ai):
        problems.append("El corpus sintético contiene textos que no son español.")

    orphan_ai = [record["id"] for record in ai if record.get("pair_id") not in human_ids]
    if orphan_ai:
        problems.append(f"Hay {len(orphan_ai)} textos sintéticos sin par humano.")

    duplicated_ai_pairs = [
        pair_id
        for pair_id, count in collections.Counter(
            record.get("pair_id") for record in ai
        ).items()
        if count > 1
    ]
    if duplicated_ai_pairs:
        problems.append(
            f"Hay {len(duplicated_ai_pairs)} pares con más de un texto sintético."
        )

    normalized_hashes = collections.Counter(
        normalize_text(record["text"]).lower() for record in human + ai
    )
    repeated = sum(1 for count in normalized_hashes.values() if count > 1)
    if repeated:
        problems.append(f"Hay {repeated} textos normalizados repetidos en el corpus.")

    pair_overlap: list[float] = []
    title_repeats = 0
    artifact_counts: collections.Counter[str] = collections.Counter()
    for record in ai:
        pair = human_by_id.get(record.get("pair_id"))
        if pair is None:
            continue
        pair_overlap.append(
            containment(shingles(record["text"]), shingles(pair["text"]))
        )
        title = normalize_text(pair["title"]).lower()
        if title and normalize_text(record["text"]).lower().startswith(title[:40]):
            title_repeats += 1
        artifact_counts.update(
            format_artifacts(record["text"], record.get("title") or "")
        )

    if artifact_counts:
        problems.append(
            "El corpus sintético contiene artefactos del generador: "
            + ", ".join(
                f"{reason}={count}" for reason, count in sorted(artifact_counts.items())
            )
        )

    human_artifact_counts: collections.Counter[str] = collections.Counter()
    for record in human:
        human_artifact_counts.update(
            format_artifacts(record["text"], record.get("title") or "")
        )
    if human_artifact_counts:
        problems.append(
            "El corpus humano contiene artefactos de formato: "
            + ", ".join(
                f"{reason}={count}"
                for reason, count in sorted(human_artifact_counts.items())
            )
        )

    max_overlap = max(pair_overlap) if pair_overlap else 0.0
    high_overlap = sum(1 for value in pair_overlap if value > MAX_CONTAINMENT)
    if high_overlap:
        problems.append(
            f"{high_overlap} pares comparten más del {MAX_CONTAINMENT:.0%} de "
            f"fragmentos de {SHINGLE_SIZE} palabras."
        )

    pairs = []
    for record in ai:
        pair = human_by_id.get(record.get("pair_id"))
        if pair is None:
            continue
        pairs.append(
            {
                "pair_id": pair["id"],
                "group": pair["group"],
                "split": split_for(str(pair["group"]), pair["id"], seed=seed),
            }
        )

    split_counts = collections.Counter(pair["split"] for pair in pairs)
    split_journals = {
        split: len({pair["group"] for pair in pairs if pair["split"] == split})
        for split in ("train", "calib", "threshold", "test")
    }
    if split_counts.get("test", 0) < 50:
        problems.append("El conjunto de prueba tiene menos de 50 pares.")
    if split_journals.get("test", 0) < 3:
        problems.append("El conjunto de prueba cubre menos de 3 revistas.")

    report = {
        "human_records": len(human),
        "ai_records": len(ai),
        "pairs": len(pairs),
        "human_journals": len({record["group"] for record in human}),
        "pair_journals": len({pair["group"] for pair in pairs}),
        "languages": {
            "all": dict(languages),
            "human": dict(human_languages),
            "ai": dict(ai_languages),
        },
        "word_stats": {
            "human": word_stats([len(record["text"].split()) for record in human]),
            "ai": word_stats([len(record["text"].split()) for record in ai]),
        },
        "licenses": dict(
            collections.Counter(
                record["source"].get("license") or "unknown" for record in human
            )
        ),
        "generators": dict(
            collections.Counter(
                record["generator"]["model"] for record in ai if record.get("generator")
            )
        ),
        "splits": {
            "pairs": dict(split_counts),
            "journals": split_journals,
        },
        "duplicates": {
            "normalized_texts_repeated": repeated,
            "ai_pairs_with_duplicates": len(duplicated_ai_pairs),
            "orphan_ai": len(orphan_ai),
        },
        "leakage": {
            "shingle_size": SHINGLE_SIZE,
            "max_pair_containment": round(max_overlap, 4),
            "pairs_over_threshold": high_overlap,
            "threshold": MAX_CONTAINMENT,
            "ai_texts_starting_with_title": title_repeats,
        },
        "artifacts": dict(sorted(artifact_counts.items())),
        "human_artifacts": dict(sorted(human_artifact_counts.items())),
        "problems": problems,
    }
    return report, problems


def main() -> None:
    parser = argparse.ArgumentParser(description="Audita el corpus de SciTrace AI.")
    parser.add_argument("--human", type=Path, default=Path("data/human.jsonl"))
    parser.add_argument("--ai", type=Path, default=Path("data/ai.jsonl"))
    parser.add_argument("--output", type=Path, default=Path("data/audit.json"))
    parser.add_argument("--seed", type=int, default=20261023)
    args = parser.parse_args()

    report, problems = audit(args.human, args.ai, seed=args.seed)

    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(
        json.dumps(report, ensure_ascii=False, indent=2),
        encoding="utf-8",
    )

    print(json.dumps(report, ensure_ascii=False, indent=2))
    print(f"Informe: {args.output}")

    if problems:
        print("PROBLEMAS BLOQUEANTES:", file=sys.stderr)
        for problem in problems:
            print(f" - {problem}", file=sys.stderr)
        raise SystemExit(1)


if __name__ == "__main__":
    main()
