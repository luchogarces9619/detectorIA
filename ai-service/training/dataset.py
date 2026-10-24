"""Construcción del corpus de entrenamiento de SciTrace AI.

El corpus combina dos fuentes:

* Textos humanos: resúmenes de artículos académicos en español publicados en
  revistas de acceso abierto indexadas en DOAJ, restringidos a licencias que
  permiten reutilización (CC BY, CC BY-SA, CC BY-NC, CC BY-NC-SA y CC0).
* Textos generados: resúmenes sintéticos producidos localmente por un modelo de
  lenguaje abierto, usando como tema el título del artículo humano emparejado.

Cada registro conserva su procedencia para que el corpus sea auditable. El
script no descarga documentos de usuarios ni usa servicios comerciales.
"""

from __future__ import annotations

import argparse
import hashlib
import html
import json
import re
import time
from pathlib import Path
from typing import Any
from urllib.parse import quote

import httpx
import langid

from training.splits import stable_fraction

DOAJ_API = "https://doaj.org/api"
USER_AGENT = "SciTraceAI/0.1 (corpus academico; contacto: local)"
ALLOWED_LICENSES = {
    "CC BY",
    "CC BY-SA",
    "CC BY-NC",
    "CC BY-NC-SA",
    "CC0",
}
MIN_ABSTRACT_CHARS = 600
MAX_ABSTRACT_CHARS = 2600
MIN_ABSTRACT_WORDS = 90

SPANISH_MARKERS = {
    "el", "la", "los", "las", "de", "del", "que", "y", "en", "con", "para",
    "por", "un", "una", "se", "su", "al", "es", "son", "este", "esta", "como",
    "más", "entre", "desde", "sobre", "también", "fue", "han", "sus", "sin",
    "donde", "cuando", "porque", "estos", "estas", "nos", "lo", "le", "ya",
}
PORTUGUESE_MARKERS = {
    "não", "uma", "dos", "das", "com", "pelo", "pela", "também", "são", "foi",
    "como", "mais", "entre", "sobre", "seus", "suas", "pode", "após",
}
ENGLISH_MARKERS = {
    "the", "and", "of", "to", "in", "is", "are", "with", "this", "that",
    "for", "from", "was", "were", "have", "has", "been", "their", "which",
    "study", "results", "research", "paper", "article",
}

TAG_RE = re.compile(r"<[^>]+>")
SPACE_RE = re.compile(r"\s+")
WORD_RE = re.compile(r"[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+")
LEADING_LABEL_RE = re.compile(
    r"^\s*(resumen|abstract|resumo)\s*[:.\-–—]?\s*",
    re.IGNORECASE,
)
TRAILING_KEYWORDS_RE = re.compile(
    r"\s*(palabras\s+clave|palavras-chave|keywords)\s*[:.\-–—].*$",
    re.IGNORECASE | re.DOTALL,
)


def clean_text(value: str) -> str:
    """Elimina marcado, entidades habituales y espacios redundantes."""
    text = TAG_RE.sub(" ", value)
    text = html.unescape(text)
    return SPACE_RE.sub(" ", text).strip()


def strip_abstract_labels(text: str) -> str:
    """Quita etiquetas editoriales que delatarían la fuente del texto."""
    cleaned = LEADING_LABEL_RE.sub("", text)
    cleaned = TRAILING_KEYWORDS_RE.sub("", cleaned)
    return SPACE_RE.sub(" ", cleaned).strip()


def word_count(text: str) -> int:
    return len(text.split())


def spanish_ratio(text: str) -> float:
    """Proporción de marcas lingüísticas que apuntan a español.

    Es una heurística deliberadamente simple y determinista. No sustituye a un
    detector de idioma, pero evita mezclar resúmenes en portugués o inglés.
    """
    words = [word.lower() for word in WORD_RE.findall(text)]
    if not words:
        return 0.0

    spanish = sum(1 for word in words if word in SPANISH_MARKERS)
    portuguese = sum(1 for word in words if word in PORTUGUESE_MARKERS)
    english = sum(1 for word in words if word in ENGLISH_MARKERS)
    total = spanish + portuguese + english

    if total == 0:
        return 0.0

    return spanish / total


def is_spanish_academic(text: str) -> bool:
    words = [word.lower() for word in WORD_RE.findall(text)]
    if len(words) < MIN_ABSTRACT_WORDS:
        return False

    spanish_hits = sum(1 for word in words if word in SPANISH_MARKERS)
    if spanish_hits < 12:
        return False

    if spanish_ratio(text) < 0.55:
        return False

    language, _ = langid.classify(text)
    return language == "es"


def text_hash(text: str) -> str:
    return hashlib.sha256(SPACE_RE.sub(" ", text.lower()).strip().encode("utf-8")).hexdigest()


def fetch_json(
    client: httpx.Client,
    url: str,
    cache_path: Path,
    *,
    retries: int = 4,
) -> dict[str, Any]:
    """Descarga JSON con caché en disco y reintentos con espera exponencial."""
    if cache_path.exists():
        return json.loads(cache_path.read_text(encoding="utf-8"))

    last_error: Exception | None = None
    for attempt in range(retries):
        try:
            response = client.get(url)
            response.raise_for_status()
            payload = response.json()
            cache_path.parent.mkdir(parents=True, exist_ok=True)
            cache_path.write_text(
                json.dumps(payload, ensure_ascii=False),
                encoding="utf-8",
            )
            time.sleep(0.4)
            return payload
        except (httpx.HTTPError, ValueError) as exc:  # pragma: no cover - red real
            last_error = exc
            time.sleep(1.5 * (attempt + 1))

    raise RuntimeError(f"No se pudo descargar {url}: {last_error}")


def journal_license(journal: dict[str, Any]) -> str | None:
    """Devuelve la primera licencia permitida de una revista de DOAJ."""
    for entry in journal.get("bibjson", {}).get("license", []) or []:
        kind = str(entry.get("type", "")).strip()
        if kind in ALLOWED_LICENSES:
            return kind
    return None


def journal_identifier(journal: dict[str, Any]) -> str | None:
    bib = journal.get("bibjson", {})
    for key in ("pissn", "eissn"):
        value = bib.get(key)
        if value:
            return str(value)
    return None


def collect_journals(
    client: httpx.Client,
    raw_dir: Path,
    *,
    pages: int,
) -> list[dict[str, Any]]:
    """Recopila revistas en español con licencia reutilizable."""
    selected: list[dict[str, Any]] = []
    seen: set[str] = set()

    for page in range(1, pages + 1):
        url = f"{DOAJ_API}/search/journals/{quote('bibjson.language:ES')}?pageSize=100&page={page}"
        payload = fetch_json(client, url, raw_dir / f"journals_p{page}.json")

        for item in payload.get("results", []):
            license_type = journal_license(item)
            identifier = journal_identifier(item)
            if license_type is None or identifier is None or identifier in seen:
                continue

            bib = item.get("bibjson", {})
            seen.add(identifier)
            selected.append(
                {
                    "id": identifier,
                    "title": clean_text(str(bib.get("title", ""))),
                    "license": license_type,
                    "publisher": bib.get("publisher", {}).get("name"),
                    "country": bib.get("publisher", {}).get("country"),
                }
            )

    return selected


def collect_articles(
    client: httpx.Client,
    raw_dir: Path,
    journal: dict[str, Any],
    *,
    max_pages: int,
) -> list[dict[str, Any]]:
    """Descarga los artículos de una revista y conserva los resúmenes válidos."""
    records: list[dict[str, Any]] = []

    for page in range(1, max_pages + 1):
        query = quote(f'bibjson.journal.issns:"{journal["id"]}"')
        url = f"{DOAJ_API}/search/articles/{query}?pageSize=100&page={page}"
        payload = fetch_json(
            client,
            url,
            raw_dir / "articles" / f"{journal['id']}_p{page}.json",
        )
        results = payload.get("results", [])
        if not results:
            break

        for item in results:
            bib = item.get("bibjson", {})
            abstract = strip_abstract_labels(clean_text(str(bib.get("abstract", ""))))
            title = clean_text(str(bib.get("title", "")))

            if not abstract or not title:
                continue
            if not (MIN_ABSTRACT_CHARS <= len(abstract) <= MAX_ABSTRACT_CHARS):
                continue
            if not is_spanish_academic(abstract):
                continue

            doi = None
            for identifier in bib.get("identifier", []) or []:
                if identifier.get("type") == "doi":
                    doi = identifier.get("id")
                    break

            records.append(
                {
                    "doi": doi,
                    "title": title,
                    "abstract": abstract,
                    "journal_id": journal["id"],
                    "journal": journal["title"],
                    "license": journal["license"],
                    "year": bib.get("year"),
                    "keywords": bib.get("keywords", []) or [],
                    "url": f"https://doaj.org/article/{item.get('id')}" if item.get("id") else None,
                }
            )

        if len(results) < 100:
            break

    return records


def build_human(
    output: Path,
    raw_dir: Path,
    *,
    journal_pages: int,
    article_pages: int,
    target: int,
    seed: int,
) -> list[dict[str, Any]]:
    """Construye el conjunto humano deduplicado."""
    headers = {"User-Agent": USER_AGENT, "Accept": "application/json"}

    with httpx.Client(headers=headers, timeout=45.0, follow_redirects=True) as client:
        journals = collect_journals(client, raw_dir, pages=journal_pages)
        print(f"Revistas con licencia permitida: {len(journals)}")

        by_journal: list[dict[str, Any]] = []
        for journal in journals:
            articles = collect_articles(
                client, raw_dir, journal, max_pages=article_pages
            )
            if articles:
                print(f"  {journal['title'][:60]:60s} {len(articles):4d} resúmenes")
            by_journal.extend(articles)
            if len(by_journal) >= target * 2:
                break

    seen_hashes: set[str] = set()
    seen_dois: set[str] = set()
    unique: list[dict[str, Any]] = []

    for record in by_journal:
        digest = text_hash(record["abstract"])
        if digest in seen_hashes:
            continue
        if record["doi"] and record["doi"].lower() in seen_dois:
            continue
        seen_hashes.add(digest)
        if record["doi"]:
            seen_dois.add(record["doi"].lower())
        unique.append(record)

    unique.sort(key=lambda record: stable_fraction(record["abstract"] + str(seed)))
    unique = unique[:target]

    output.parent.mkdir(parents=True, exist_ok=True)
    with output.open("w", encoding="utf-8") as handle:
        for index, record in enumerate(unique):
            handle.write(
                json.dumps(
                    {
                        "id": f"human-{index:05d}",
                        "text": record["abstract"],
                        "label": "human",
                        "language": "es",
                        "title": record["title"],
                        "keywords": record["keywords"],
                        "source": {
                            "kind": "doaj",
                            "doi": record["doi"],
                            "journal": record["journal"],
                            "journal_id": record["journal_id"],
                            "license": record["license"],
                            "url": record["url"],
                            "year": record["year"],
                        },
                        "generator": None,
                        "group": record["journal_id"],
                        "sha256": text_hash(record["abstract"]),
                    },
                    ensure_ascii=False,
                )
                + "\n"
            )

    print(f"Resúmenes humanos únicos: {len(unique)} -> {output}")
    return unique


def main() -> None:
    parser = argparse.ArgumentParser(description="Construye el corpus humano de SciTrace AI.")
    parser.add_argument("--output", type=Path, default=Path("data/human.jsonl"))
    parser.add_argument("--raw-dir", type=Path, default=Path("data/raw/doaj"))
    parser.add_argument("--journal-pages", type=int, default=8)
    parser.add_argument("--article-pages", type=int, default=1)
    parser.add_argument("--target", type=int, default=800)
    parser.add_argument("--seed", type=int, default=20261023)
    args = parser.parse_args()

    build_human(
        args.output,
        args.raw_dir,
        journal_pages=args.journal_pages,
        article_pages=args.article_pages,
        target=args.target,
        seed=args.seed,
    )


if __name__ == "__main__":
    main()
