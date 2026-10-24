"""Genera un informe Markdown a partir del artefacto entrenado.

Lee ``metrics.json`` y ``card.json`` del directorio del modelo y escribe una
tabla con las métricas realmente medidas. No calcula ni estima nada nuevo.
"""

from __future__ import annotations

import argparse
import json
from pathlib import Path
from typing import Any


def read_json(path: Path) -> dict[str, Any]:
    return json.loads(path.read_text(encoding="utf-8"))


def format_value(value: Any) -> str:
    if value is None:
        return "—"
    if isinstance(value, float):
        return f"{value:.4f}"
    return str(value)


def metrics_table(title: str, data: dict[str, Any]) -> list[str]:
    rows = [
        ("ROC AUC", data.get("roc_auc")),
        ("PR AUC", data.get("pr_auc")),
        ("Brier", data.get("brier")),
        ("Log-loss", data.get("log_loss")),
        ("ECE (10 bins)", data.get("ece_10")),
        ("Exactitud @ 0,5", data.get("accuracy_at_050")),
        ("F1 @ 0,5", data.get("f1_at_050")),
        ("Elementos", data.get("count")),
        ("Tasa de IA", data.get("ai_rate")),
    ]
    lines = [f"### {title}", "", "| Métrica | Valor |", "| --- | ---: |"]
    lines.extend(f"| {name} | {format_value(value)} |" for name, value in rows)
    return lines


def decision_table(title: str, data: dict[str, Any]) -> list[str]:
    rows = [
        ("Cobertura", data.get("coverage")),
        ("Abstención", data.get("abstention_rate")),
        ("Decididos", data.get("decided")),
        ("Abstenidos", data.get("abstained")),
        ("Exactitud", data.get("accuracy")),
        ("Precisión", data.get("precision")),
        ("Sensibilidad", data.get("recall")),
        ("F1", data.get("f1")),
    ]
    lines = [f"### {title}", "", "| Métrica | Valor |", "| --- | ---: |"]
    lines.extend(f"| {name} | {format_value(value)} |" for name, value in rows)
    return lines


def build_report(model_dir: Path) -> str:
    card = read_json(model_dir / "card.json")
    metrics = read_json(model_dir / "metrics.json")
    dataset = card.get("dataset", {})
    thresholds = card.get("thresholds", {})

    lines = [
        f"# Informe del detector: {card.get('name')} v{card.get('version')}",
        "",
        f"- Artefacto: `{card.get('artifact')}`",
        f"- SHA-256: `{card.get('artifact_sha256')}`",
        f"- Creado: {card.get('created_at')}",
        (
            f"- Umbrales: `high={thresholds.get('high')}`, "
            f"`low={thresholds.get('low')}`, `valid={thresholds.get('valid')}`"
        ),
        (
            f"- Corpus: {dataset.get('pairs')} pares, "
            f"{dataset.get('journals')} revistas"
        ),
        f"- Particiones: {dataset.get('splits')}",
        "",
        "## Test (no usado para ajustar)",
        "",
    ]

    test = metrics.get("test", {})
    lines.extend(metrics_table("Fragmentos", test.get("chunk_level", {})))
    lines.append("")
    lines.extend(decision_table("Decisiones por fragmento", test.get("chunk_decisions", {})))
    lines.append("")
    lines.extend(metrics_table("Documentos", test.get("document_level", {})))
    lines.append("")
    lines.extend(
        decision_table("Decisiones por documento", test.get("document_decisions", {}))
    )

    if "ood" in metrics:
        ood = metrics["ood"]
        lines.extend(
            [
                "",
                "## Generalización con otro generador (OOD)",
                "",
                f"- Generador: `{ood.get('generator')}`",
                f"- Pares: {ood.get('pairs')}",
                "",
            ]
        )
        lines.extend(metrics_table("Documentos OOD", ood.get("document_level", {})))
        lines.append("")
        lines.extend(
            decision_table("Decisiones OOD por documento", ood.get("document_decisions", {}))
        )

    return "\n".join(lines) + "\n"


def main() -> None:
    parser = argparse.ArgumentParser(description="Genera un informe del detector.")
    parser.add_argument("--model-dir", type=Path, default=Path("models/scitrace-es-v1"))
    parser.add_argument("--output", type=Path, default=None)
    args = parser.parse_args()

    report = build_report(args.model_dir)

    if args.output is None:
        print(report)
        return

    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(report, encoding="utf-8")
    print(f"Informe: {args.output}")


if __name__ == "__main__":
    main()
