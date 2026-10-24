"""Filtra el corpus humano para alinear su formato con el sintético.

El corpus humano contiene resúmenes estructurados con etiquetas («Objetivo:»,
«Metodología:»), algún Markdown y títulos usados como encabezado. El corpus
sintético se filtra por el mismo criterio. Si solo se filtrara uno de los dos,
la presencia de una etiqueta se convertiría en una señal espuria de autoría.

El archivo de origen no se modifica: se escribe un corpus limpio y un informe
con los identificadores excluidos y el motivo.
"""

from __future__ import annotations

import argparse
import hashlib
import json
from collections import Counter
from pathlib import Path
from typing import Any

from training.generate_llamacpp import format_artifacts


def read_jsonl(path: Path) -> list[dict[str, Any]]:
    with path.open("r", encoding="utf-8") as handle:
        return [json.loads(line) for line in handle if line.strip()]


def file_sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for block in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(block)
    return digest.hexdigest()


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Excluye del corpus humano los textos con artefactos de formato."
    )
    parser.add_argument("--input", type=Path, default=Path("data/human.jsonl"))
    parser.add_argument("--output", type=Path, default=Path("data/human-clean.jsonl"))
    parser.add_argument(
        "--report", type=Path, default=Path("data/human-clean-report.json")
    )
    args = parser.parse_args()

    records = read_jsonl(args.input)
    kept: list[dict[str, Any]] = []
    excluded: list[dict[str, Any]] = []
    reasons: Counter[str] = Counter()

    for record in records:
        found = format_artifacts(record["text"], record.get("title") or "")
        if found:
            reasons.update(found)
            excluded.append({"id": record["id"], "reasons": found})
            continue
        kept.append(record)

    if not kept:
        raise SystemExit("El filtro no dejó ningún registro; revisa el corpus.")

    args.output.parent.mkdir(parents=True, exist_ok=True)
    with args.output.open("w", encoding="utf-8") as handle:
        for record in kept:
            handle.write(json.dumps(record, ensure_ascii=False) + "\n")

    report = {
        "input": str(args.input),
        "input_sha256": file_sha256(args.input),
        "output": str(args.output),
        "output_sha256": file_sha256(args.output),
        "total": len(records),
        "kept": len(kept),
        "excluded": len(excluded),
        "excluded_ratio": round(len(excluded) / len(records), 4),
        "reasons": dict(sorted(reasons.items())),
        "journals_kept": len({record["group"] for record in kept}),
        "excluded_records": excluded,
    }
    args.report.write_text(
        json.dumps(report, ensure_ascii=False, indent=2), encoding="utf-8"
    )

    print(
        f"Conservados: {len(kept)}/{len(records)}; excluidos: {len(excluded)} "
        f"({report['excluded_ratio']:.2%}); motivos: {report['reasons']}"
    )
    print(f"Corpus limpio: {args.output}")
    print(f"Informe: {args.report}")


if __name__ == "__main__":
    main()
