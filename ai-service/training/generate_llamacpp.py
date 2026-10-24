"""Genera textos sintéticos contra un servidor local de llama.cpp.

Se usa un servidor local (no una API comercial) porque la decodificación en CPU
con ``transformers`` resultó demasiado lenta en el equipo de desarrollo. El
servidor debe iniciarse aparte; este script solo envía peticiones HTTP a
``127.0.0.1``.

Ejemplo de arranque del servidor::

    llama-server.exe -m qwen2.5-1.5b-instruct-q4_k_m.gguf -c 4096 -t 6 \
        --parallel 4 --port 8091

El número de peticiones concurrentes no debe superar las ranuras del servidor
(``--parallel``). Cada registro lleva su propia semilla, de modo que el orden de
finalización no altera el resultado esperado de cada par.

El prompt evita dar el título cuando el registro tiene palabras clave, para que
el modelo no tenga un encabezado que copiar. Los textos con Markdown, etiquetas
de sección o título como encabezado se descartan antes de escribirse. El corpus
humano se filtra con el mismo criterio (``training.filter_human``), de modo que
ambas clases compartan el formato.
"""

from __future__ import annotations

import argparse
import concurrent.futures
import json
import re
import time
from pathlib import Path
from typing import Any

import httpx

from training.splits import split_for

PROMPT_WITH_KEYWORDS = (
    "Escribe el resumen de un artículo científico en español sobre el tema "
    "descrito por estas palabras clave: {keywords}. Extensión: entre 180 y 240 "
    "palabras, en un solo párrafo. Empieza directamente con la exposición del "
    "tema, sin anunciar lo que vas a escribir."
)

PROMPT_WITH_TITLE = (
    "Escribe el resumen de un artículo científico en español sobre el tema: "
    '"{title}". El texto debe abordar los conceptos centrales del título. '
    "Extensión: entre 180 y 240 palabras, en un solo párrafo. Empieza "
    "directamente con la exposición del tema, sin repetir ni citar el título."
)

SYSTEM_PROMPT = (
    "Eres un investigador que redacta resúmenes de artículos científicos en "
    "español. Escribes en prosa continua, con registro académico formal y "
    "vocabulario preciso. Nunca usas Markdown, viñetas, encabezados ni etiquetas "
    "como «Objetivo:», «Metodología:», «Resultados:» o «Conclusión:». Tampoco "
    "repites ni citas el título del artículo, y no anuncias lo que vas a escribir."
)

SPACE_RE = re.compile(r"\s+")
SENTENCE_END_RE = re.compile(r"[.!?…](?:[\"'”’)\]]*)(?=\s|$)")
LABEL_RE = re.compile(
    r"(?i)(?:^|(?<=[\s.;]))"
    r"(objetivo|metodolog[ií]a|resultados|conclusi[oó]n(?:es)?|resumen)\s*:"
)
META_RE = re.compile(r"(?i)(es el siguiente|a continuaci[oó]n se presenta)")
TITLE_MIN_CHARS = 20
TITLE_PREFIX_CHARS = 40


def trim_to_complete_sentence(text: str) -> str:
    """Recorta un texto truncado hasta la última oración completa.

    El modelo puede alcanzar el límite de tokens a mitad de palabra. Entrenar
    con esos cortes introduciría un artefacto que no existe en un documento
    real, así que se descarta la cola incompleta.
    """
    stripped = text.strip()
    if not stripped:
        return ""

    matches = list(SENTENCE_END_RE.finditer(stripped))
    if not matches:
        return ""

    return stripped[: matches[-1].end()].strip()


def model_slug(model_id: str) -> str:
    return model_id.replace("/", "--").lower()


def format_artifacts(text: str, title: str) -> list[str]:
    """Detecta marcas de formato que no deben entrar al corpus.

    Un clasificador entrenado con Markdown, etiquetas de sección o el título
    como encabezado aprendería el formato del documento en vez del estilo. Estos
    textos se descartan en lugar de intentar limpiarlos, porque una limpieza
    parcial dejaría restos (por ejemplo, una oración huérfana).

    El mismo criterio se aplica al corpus humano para que ambas clases compartan
    el formato; de lo contrario, la presencia de una etiqueta sería una señal
    espuria de autoría humana.
    """
    reasons: list[str] = []
    stripped = text.lstrip()

    if "**" in text or "##" in text or stripped.startswith("#"):
        reasons.append("markdown")
    if LABEL_RE.search(text):
        reasons.append("etiqueta")
    if META_RE.search(text):
        reasons.append("meta")

    normalized_title = SPACE_RE.sub(" ", title).strip().lower()
    normalized_text = SPACE_RE.sub(" ", text).strip().lower()
    prefix = normalized_title[:TITLE_PREFIX_CHARS]
    # Solo se considera repetición de título si el texto empieza con él, como
    # un encabezado. Un resumen humano puede mencionar el tema con las mismas
    # palabras a mitad de una oración sin que eso sea un artefacto.
    if len(prefix) >= TITLE_MIN_CHARS and normalized_text.startswith(prefix):
        reasons.append("titulo")

    return reasons


def read_jsonl(path: Path) -> list[dict[str, Any]]:
    with path.open("r", encoding="utf-8") as handle:
        return [json.loads(line) for line in handle if line.strip()]


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


def build_prompt(record: dict[str, Any]) -> tuple[str, str]:
    """Devuelve el prompt y su tipo según los metadatos disponibles.

    Cuando hay palabras clave se usan solas, sin el título, para que el modelo
    no tenga un encabezado que copiar. El título queda como respaldo para los
    registros que no declaran palabras clave.
    """
    keywords = record.get("keywords") or []
    keyword_text = ", ".join(str(item) for item in keywords[:6])

    if keyword_text:
        return PROMPT_WITH_KEYWORDS.format(keywords=keyword_text), "keywords"

    return PROMPT_WITH_TITLE.format(title=record["title"]), "title"


def request_completion(
    client: httpx.Client,
    prompt: str,
    *,
    max_new_tokens: int,
    temperature: float,
    top_p: float,
    seed: int,
) -> str:
    response = client.post(
        "/v1/chat/completions",
        json={
            "model": "local",
            "messages": [
                {"role": "system", "content": SYSTEM_PROMPT},
                {"role": "user", "content": prompt},
            ],
            "temperature": temperature,
            "top_p": top_p,
            "max_tokens": max_new_tokens,
            "seed": seed,
        },
    )
    response.raise_for_status()
    payload = response.json()
    return SPACE_RE.sub(" ", payload["choices"][0]["message"]["content"]).strip()


def server_metadata(client: httpx.Client) -> dict[str, Any]:
    response = client.get("/props")
    response.raise_for_status()
    payload = response.json()
    return {
        "model_path": str(payload.get("model_path") or ""),
        "model_ftype": payload.get("model_ftype"),
        "total_slots": payload.get("total_slots"),
    }


def main() -> None:
    parser = argparse.ArgumentParser(description="Genera textos con llama.cpp local.")
    parser.add_argument("--input", type=Path, default=Path("data/human.jsonl"))
    parser.add_argument("--output", type=Path, default=Path("data/ai.jsonl"))
    parser.add_argument("--base-url", default="http://127.0.0.1:8091")
    parser.add_argument("--model-label", default="Qwen/Qwen2.5-1.5B-Instruct-GGUF:q4_k_m")
    parser.add_argument("--expect-model", default=None)
    parser.add_argument("--concurrency", type=int, default=4)
    parser.add_argument("--limit", type=int, default=None)
    parser.add_argument("--max-new-tokens", type=int, default=380)
    parser.add_argument("--temperature", type=float, default=0.8)
    parser.add_argument("--top-p", type=float, default=0.95)
    parser.add_argument("--seed", type=int, default=20261023)
    parser.add_argument(
        "--only-split",
        choices=["train", "calib", "threshold", "test"],
        default=None,
        help="Genera solo para los pares de esa partición.",
    )
    parser.add_argument(
        "--shard-index",
        type=int,
        default=0,
        help="Índice del fragmento de trabajo (0..shard-count-1).",
    )
    parser.add_argument(
        "--shard-count",
        type=int,
        default=1,
        help="Número de procesos que reparten el corpus.",
    )
    parser.add_argument("--min-words", type=int, default=90)
    parser.add_argument("--max-words", type=int, default=300)
    parser.add_argument("--timeout", type=float, default=300.0)
    args = parser.parse_args()

    if args.concurrency < 1:
        raise SystemExit("--concurrency debe ser al menos 1.")
    if args.shard_count < 1:
        raise SystemExit("--shard-count debe ser al menos 1.")
    if not 0 <= args.shard_index < args.shard_count:
        raise SystemExit("--shard-index debe estar entre 0 y shard-count-1.")

    records = read_jsonl(args.input)
    index_by_id = {record["id"]: index for index, record in enumerate(records)}
    done = load_existing(args.output)
    slug = model_slug(args.model_label)

    pending = [
        record for record in records if f"ai-{record['id']}-{slug}" not in done
    ]
    if args.only_split is not None:
        pending = [
            record
            for record in pending
            if split_for(str(record["group"]), record["id"], seed=args.seed)
            == args.only_split
        ]
    if args.shard_count > 1:
        # El reparto usa la posición en el corpus completo, no en la lista de
        # pendientes, para que la semilla de cada par sea reproducible.
        pending = [
            record
            for record in pending
            if index_by_id[record["id"]] % args.shard_count == args.shard_index
        ]
    if args.limit is not None:
        pending = pending[: args.limit]

    if not pending:
        print("No hay registros pendientes.")
        return

    args.output.parent.mkdir(parents=True, exist_ok=True)
    started = time.perf_counter()
    written = 0
    skipped = 0
    failed = 0

    with httpx.Client(base_url=args.base_url, timeout=args.timeout) as client:
        health = client.get("/health")
        health.raise_for_status()
        server = server_metadata(client)

        print(
            f"Servidor: {server['model_path'] or 'desconocido'} · "
            f"{server['model_ftype'] or 'tipo desconocido'} · "
            f"{server['total_slots'] or '?'} ranuras"
        )
        if args.expect_model:
            haystack = f"{server['model_path']} {server['model_ftype']}".lower()
            if args.expect_model.lower() not in haystack:
                raise SystemExit(
                    f"El servidor no corresponde a '{args.expect_model}'. "
                    f"Modelo servido: {server['model_path']}"
                )

        with args.output.open("a", encoding="utf-8") as handle:
            with concurrent.futures.ThreadPoolExecutor(
                max_workers=args.concurrency
            ) as pool:
                futures: dict[
                    concurrent.futures.Future[str],
                    tuple[dict[str, Any], str, str, int],
                ] = {}

                for record in pending:
                    prompt, prompt_kind = build_prompt(record)
                    seed = args.seed + index_by_id[record["id"]]
                    future = pool.submit(
                        request_completion,
                        client,
                        prompt,
                        max_new_tokens=args.max_new_tokens,
                        temperature=args.temperature,
                        top_p=args.top_p,
                        seed=seed,
                    )
                    futures[future] = (record, prompt, prompt_kind, seed)

                for future in concurrent.futures.as_completed(futures):
                    record, prompt, prompt_kind, seed = futures[future]
                    try:
                        text = future.result()
                    except (httpx.HTTPError, KeyError, ValueError) as exc:
                        failed += 1
                        print(f"  error {record['id']}: {exc}")
                        continue

                    text = trim_to_complete_sentence(text)
                    words = len(text.split())
                    if not text:
                        skipped += 1
                        print(f"  descartado {record['id']}: sin oración completa")
                        continue

                    artifacts = format_artifacts(text, record["title"])
                    if artifacts:
                        skipped += 1
                        print(
                            f"  descartado {record['id']}: artefactos "
                            f"{', '.join(artifacts)}"
                        )
                        continue

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
                                    "prompt_kind": prompt_kind,
                                    "system_prompt": SYSTEM_PROMPT,
                                    "derived_from": record["id"],
                                    "derived_from_license": record["source"].get("license"),
                                },
                                "generator": {
                                    "model": args.model_label,
                                    "server_model_path": server["model_path"],
                                    "server_model_ftype": server["model_ftype"],
                                    "runtime": "llama.cpp",
                                    "quantization": "q4_k_m",
                                    "temperature": args.temperature,
                                    "top_p": args.top_p,
                                    "max_new_tokens": args.max_new_tokens,
                                    "seed": seed,
                                    "concurrency": args.concurrency,
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
                    remaining = len(pending) - written - skipped - failed
                    eta = remaining / rate / 60 if rate else 0.0
                    print(
                        f"[{written}/{len(pending)}] {record['id']} "
                        f"{words} palabras · {rate:.2f} textos/s · ETA {eta:.1f} min"
                    )

    print(
        f"Generados: {written}; descartados: {skipped}; "
        f"errores: {failed}; salida: {args.output}"
    )


if __name__ == "__main__":
    main()
