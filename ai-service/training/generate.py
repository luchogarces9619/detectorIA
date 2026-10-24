"""Genera resúmenes sintéticos en español con un modelo abierto local.

No se usa ninguna API comercial. El modelo se ejecuta en CPU y cada registro
guarda la versión, el prompt y los parámetros de generación para que el corpus
sea reproducible y auditable.
"""

from __future__ import annotations

import argparse
import json
import re
import time
from pathlib import Path
from typing import Any

import torch
from transformers import AutoModelForCausalLM, AutoTokenizer

DEFAULT_PROMPT = (
    "Redacta el resumen de un artículo científico en español sobre el tema: "
    "\"{title}\".\n"
    "Tema: {keywords}.\n"
    "Escribe entre 150 y 220 palabras, en un solo párrafo, e incluye objetivo, "
    "metodología, resultados y conclusión. No repitas el título, no uses formato "
    "Markdown ni etiquetas como \"Resumen\" u \"Objetivo\", y no incluyas listas, "
    "encabezados, referencias ni expresiones como \"como modelo de lenguaje\"."
)

SPACE_RE = re.compile(r"\s+")


def model_slug(model_id: str) -> str:
    return model_id.replace("/", "--").lower()


def load_existing(path: Path) -> set[str]:
    if not path.exists():
        return set()

    done: set[str] = set()
    with path.open("r", encoding="utf-8") as handle:
        for line in handle:
            line = line.strip()
            if not line:
                continue
            try:
                done.add(json.loads(line)["id"])
            except (KeyError, json.JSONDecodeError):
                continue
    return done


def read_jsonl(path: Path) -> list[dict[str, Any]]:
    with path.open("r", encoding="utf-8") as handle:
        return [json.loads(line) for line in handle if line.strip()]


def build_prompt(record: dict[str, Any], template: str) -> str:
    keywords = record.get("keywords") or []
    keyword_text = ", ".join(str(item) for item in keywords[:6]) or "investigación académica"
    return template.format(title=record["title"], keywords=keyword_text)


def generate_texts(
    model: Any,
    tokenizer: Any,
    prompts: list[str],
    *,
    max_new_tokens: int,
    temperature: float,
    top_p: float,
    seed: int,
) -> list[str]:
    outputs: list[str] = []

    for index, prompt in enumerate(prompts):
        torch.manual_seed(seed + index)
        messages = [{"role": "user", "content": prompt}]
        text = tokenizer.apply_chat_template(
            messages,
            tokenize=False,
            add_generation_prompt=True,
        )
        inputs = tokenizer(text, return_tensors="pt")

        with torch.inference_mode():
            generated = model.generate(
                **inputs,
                max_new_tokens=max_new_tokens,
                do_sample=True,
                temperature=temperature,
                top_p=top_p,
                repetition_penalty=1.05,
                pad_token_id=tokenizer.eos_token_id,
            )

        completion = generated[0][inputs["input_ids"].shape[1] :]
        outputs.append(
            SPACE_RE.sub(
                " ",
                tokenizer.decode(completion, skip_special_tokens=True),
            ).strip()
        )

    return outputs


def main() -> None:
    parser = argparse.ArgumentParser(description="Genera textos sintéticos para SciTrace AI.")
    parser.add_argument("--input", type=Path, default=Path("data/human.jsonl"))
    parser.add_argument("--output", type=Path, default=Path("data/ai.jsonl"))
    parser.add_argument("--model", default="Qwen/Qwen2.5-1.5B-Instruct")
    parser.add_argument("--limit", type=int, default=None)
    parser.add_argument("--max-new-tokens", type=int, default=320)
    parser.add_argument("--temperature", type=float, default=0.8)
    parser.add_argument("--top-p", type=float, default=0.95)
    parser.add_argument("--seed", type=int, default=20261023)
    parser.add_argument("--quantize", action="store_true")
    parser.add_argument("--min-words", type=int, default=110)
    parser.add_argument("--max-words", type=int, default=260)
    args = parser.parse_args()

    records = read_jsonl(args.input)
    done = load_existing(args.output)
    slug = model_slug(args.model)

    pending = [
        record
        for record in records
        if f"ai-{record['id']}-{slug}" not in done
    ]
    if args.limit is not None:
        pending = pending[: args.limit]

    if not pending:
        print("No hay registros pendientes.")
        return

    print(f"Cargando {args.model} en CPU…")
    tokenizer = AutoTokenizer.from_pretrained(args.model)
    model = AutoModelForCausalLM.from_pretrained(args.model, dtype=torch.float32)
    model.eval()

    if args.quantize:
        print("Aplicando cuantización dinámica int8…")
        model = torch.ao.quantization.quantize_dynamic(
            model, {torch.nn.Linear}, dtype=torch.qint8
        )

    args.output.parent.mkdir(parents=True, exist_ok=True)
    started = time.perf_counter()
    written = 0
    skipped = 0

    with args.output.open("a", encoding="utf-8") as handle:
        for index, record in enumerate(pending):
            prompt = build_prompt(record, DEFAULT_PROMPT)
            text = generate_texts(
                model,
                tokenizer,
                [prompt],
                max_new_tokens=args.max_new_tokens,
                temperature=args.temperature,
                top_p=args.top_p,
                seed=args.seed + index,
            )[0]

            words = len(text.split())
            if not (args.min_words <= words <= args.max_words):
                skipped += 1
                print(f"  descartado {record['id']}: {words} palabras")
                continue

            handle.write(
                json.dumps(
                    {
                        "id": f"ai-{record['id']}-{slug}",
                        "pair_id": record["id"],
                        "text": text,
                        "label": "ai",
                        "language": "es",
                        "title": record["title"],
                        "keywords": record.get("keywords", []),
                        "source": {
                            "kind": "local-generation",
                            "prompt": prompt,
                            "derived_from": record["id"],
                            "derived_from_license": record["source"].get("license"),
                        },
                        "generator": {
                            "model": args.model,
                            "runtime": "transformers",
                            "torch": torch.__version__,
                            "quantized_int8": args.quantize,
                            "temperature": args.temperature,
                            "top_p": args.top_p,
                            "max_new_tokens": args.max_new_tokens,
                            "seed": args.seed + index,
                        },
                        "group": record["group"],
                    },
                    ensure_ascii=False,
                )
                + "\n"
            )
            handle.flush()
            written += 1

            elapsed = time.perf_counter() - started
            rate = written / elapsed if elapsed else 0.0
            print(
                f"[{written}/{len(pending)}] {record['id']} "
                f"{words} palabras · {rate:.2f} textos/s"
            )

    print(f"Generados: {written}; descartados: {skipped}; salida: {args.output}")


if __name__ == "__main__":
    main()
