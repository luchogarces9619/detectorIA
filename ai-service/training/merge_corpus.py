"""Une y deduplica archivos JSONL de corpus generados en paralelo.

Los procesos de generación por fragmentos escriben archivos distintos para no
interferir entre sí. Este script comprueba que un mismo identificador no tenga
dos textos diferentes y escribe una única salida ordenada.
"""

from __future__ import annotations

import argparse
import json
from pathlib import Path
from typing import Any


def read_jsonl(path: Path) -> list[dict[str, Any]]:
    with path.open("r", encoding="utf-8") as handle:
        return [json.loads(line) for line in handle if line.strip()]


def merge(paths: list[Path]) -> tuple[list[dict[str, Any]], int]:
    by_id: dict[str, dict[str, Any]] = {}
    duplicates = 0

    for path in paths:
        for record in read_jsonl(path):
            existing = by_id.get(record["id"])
            if existing is None:
                by_id[record["id"]] = record
                continue

            duplicates += 1
            if existing["text"] != record["text"]:
                raise SystemExit(
                    f"El identificador '{record['id']}' aparece con dos textos distintos."
                )

    return [by_id[key] for key in sorted(by_id)], duplicates


def main() -> None:
    parser = argparse.ArgumentParser(description="Une corpus JSONL sin duplicados.")
    parser.add_argument("inputs", nargs="+", type=Path)
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()

    records, duplicates = merge(args.inputs)

    args.output.parent.mkdir(parents=True, exist_ok=True)
    with args.output.open("w", encoding="utf-8") as handle:
        for record in records:
            handle.write(json.dumps(record, ensure_ascii=False) + "\n")

    print(
        f"Registros únicos: {len(records)}; duplicados idénticos descartados: {duplicates}; "
        f"salida: {args.output}"
    )


if __name__ == "__main__":
    main()
