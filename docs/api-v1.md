# API v1

La Fase 3 expone endpoints internos de salud, capacidades, **extracción de documentos** y **detección orientativa de IA**. **No existe todavía una API pública para subir o analizar documentos**: la carga y la consulta se realizan en Laravel mediante Livewire; FastAPI recibe el archivo y los fragmentos desde Laravel.

## Laravel

### `GET /api/v1/health`

Público. Comprueba la conexión a la base de datos.

```json
{
  "status": "ok",
  "service": "scitrace-web",
  "version": "0.1.0",
  "checks": {
    "database": "ok"
  },
  "latency_ms": 3,
  "timestamp": "2026-10-23T15:04:05+00:00"
}
```

- `200`: base de datos disponible.
- `503`: base de datos no disponible (`status: degraded`).

### `GET /up`

Sonda de salud del framework Laravel. Devuelve `200` con una página HTML de estado cuando la aplicación arranca. El healthcheck de Nginx comprueba únicamente el código HTTP, no el contenido.

## FastAPI

Base interna: `http://ai:8000` dentro de Docker. En el host, solo para depuración: `http://127.0.0.1:8001`.

### `GET /health`

Público dentro de la red. No requiere token y no expone secretos.

```json
{
  "status": "ok",
  "service": "SciTrace AI Engine",
  "version": "0.1.0",
  "environment": "local",
  "detector_ready": true,
  "active_model": "scitrace-es-academic-detector v1.0.0",
  "timestamp": "2026-10-23T15:04:05.123456+00:00"
}
```

`detector_ready` y `active_model` son dinámicos: solo se muestran como operativos si el artefacto existe, se puede cargar y su SHA-256 coincide con `card.json`. Si falta el artefacto, `detector_ready` vuelve a `false` y `active_model` a `null`.

### `GET /internal/v1/health`

Requiere `X-Internal-Token`. Devuelve el mismo cuerpo que `/health`.

### `GET /internal/v1/capabilities`

Requiere `X-Internal-Token`. Declara de forma explícita qué puede hacer el motor hoy.

```json
{
  "phase": "3-detector",
  "service": "SciTrace AI Engine",
  "version": "0.1.0",
  "detector_ready": true,
  "active_model": "scitrace-es-academic-detector v1.0.0",
  "capabilities": [
    {
      "name": "service_auth",
      "status": "available",
      "description": "Autenticación entre servicios mediante token compartido."
    },
    {
      "name": "health",
      "status": "available",
      "description": "Diagnóstico de disponibilidad y versión del microservicio."
    },
    {
      "name": "document_extraction",
      "status": "available",
      "description": "Extracción de DOCX/PDF y segmentación con exclusión de tablas y referencias."
    },
    {
      "name": "ocr",
      "status": "available",
      "description": "OCR de PDF escaneado con Tesseract."
    },
    {
      "name": "ai_detection",
      "status": "available",
      "description": "Detección orientativa calibrada por fragmentos con scitrace-es-academic-detector v1.0.0."
    }
  ],
  "limitations": [
    "El resultado del detector es orientativo y no demuestra autoría.",
    "El modelo se entrenó con resúmenes académicos en español y no cubre todos los géneros ni todos los generadores.",
    "Fuera de su dominio de entrenamiento el sistema debe devolver not_conclusive.",
    "La extracción no garantiza que todo PDF escaneado pueda procesarse sin OCR."
  ]
}
```

`ocr.status` es dinámico: devuelve `disabled` cuando `AI_OCR_ENABLED=false` o cuando Tesseract no está instalado en el servidor. `ai_detection.status` es `available` solo cuando el artefacto supera la verificación; en caso contrario devuelve `disabled` con el motivo real en `description`.

### `POST /internal/v1/detect`

Requiere `X-Internal-Token`. Puntúa una lista de fragmentos ya extraídos y devuelve una conclusión orientativa por documento. No calcula porcentajes de autoría.

Solicitud:

```json
{
  "segments": [
    "El estudio analiza una muestra de 240 estudiantes universitarios durante el semestre 2025-2.",
    "En conclusión, es fundamental destacar la importancia de este tema para el desarrollo integral."
  ],
  "document_id": "opcional-para-trazabilidad"
}
```

Respuesta:

Los valores numéricos de este ejemplo son ilustrativos. El SHA-256, los umbrales y la versión provienen del `card.json` del artefacto cargado y se sustituyen por los reales en la verificación de la Fase 3.

```json
{
  "model": {
    "name": "scitrace-es-academic-detector",
    "version": "1.0.0",
    "artifact_sha256": "…",
    "high_threshold": 0.81,
    "low_threshold": 0.19
  },
  "document": {
    "aggregate_score": 0.62,
    "conclusion": "mixed_signals",
    "confidence": "medium",
    "coverage": 0.85
  },
  "segments": [
    {
      "index": 0,
      "score": 0.24,
      "label": "uncertain",
      "confidence": "low",
      "requires_review": true,
      "word_count": 96,
      "chunk_count": 1
    }
  ],
  "disclaimer": "Resultado orientativo para revisión humana. No demuestra autoría ni calcula un porcentaje de texto generado por IA."
}
```

Reglas del contrato:

- `segments` admite entre 1 y 500 elementos y 400 000 caracteres en total.
- Cada fragmento se normaliza y se divide en bloques de 45–130 palabras antes de puntuarse.
- `label` es `human`, `ai` o `uncertain`; `uncertain` obliga a revisión humana.
- `conclusion` es `not_conclusive`, `no_signals`, `review_suggested` o `mixed_signals`.
- Si el detector no está disponible, responde `503` y no devuelve puntuaciones inventadas.
- Si se superan los límites, responde `422`.

### `POST /internal/v1/documents/extract`

Requiere `X-Internal-Token`. Extrae texto, secciones y fragmentos de un DOCX o PDF. **El archivo se procesa en memoria y no se persiste en FastAPI**; Laravel es el único responsable del almacenamiento, los permisos y la auditoría.

- Cuerpo: `multipart/form-data` con el campo `file`.
- Extensiones: `.docx`, `.pdf`.
- MIME declarado admitido: `application/pdf`, `application/vnd.openxmlformats-officedocument.wordprocessingml.document` o `application/octet-stream`. El MIME declarado no es una validación de seguridad; Laravel comprueba la firma real antes de almacenar.
- Tamaño máximo: `AI_MAX_DOCUMENT_BYTES` (25 MB por defecto).
- Fragmentos máximos: `AI_MAX_SEGMENTS` (10 000 por defecto).

Respuesta `200` (resumida):

```json
{
  "original_name": "tesis.docx",
  "extension": "docx",
  "mime_type": "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
  "size_bytes": 37040,
  "sha256": "e8ddf14c5906da7dfe97f7d46722b4c321b2ab03b01a8edaaee85e242ecd1dfa",
  "engine": "python-docx",
  "ocr_used": false,
  "ocr_pages": [],
  "language": null,
  "warnings": [],
  "stats": {
    "page_count": null,
    "word_count": 65,
    "paragraph_count": 2,
    "section_count": 4,
    "segment_count": 8,
    "excluded_segment_count": 3
  },
  "sections": [
    {
      "position": 0,
      "type": "title",
      "title": "Impacto de la inteligencia artificial en la educación superior",
      "level": 1,
      "char_start": 0,
      "char_end": 62
    }
  ],
  "segments": [
    {
      "position": 0,
      "section_position": 0,
      "kind": "heading",
      "text": "Impacto de la inteligencia artificial en la educación superior",
      "content_hash": "…64 caracteres hexadecimales…",
      "word_count": 9,
      "char_start": 0,
      "char_end": 62,
      "page_start": null,
      "page_end": null,
      "is_excluded": false,
      "exclusion_reason": null
    }
  ]
}
```

Campos relevantes:

| Campo | Significado |
| --- | --- |
| `engine` | `python-docx` o `pymupdf` (con OCR, el motor sigue siendo `pymupdf` y `ocr_used=true`) |
| `ocr_used` / `ocr_pages` | Indica si se aplicó OCR y en qué páginas |
| `stats.page_count` | `null` para DOCX; número de páginas para PDF |
| `sections[].type` | `title`, `abstract`, `introduction`, `methods`, `results`, `discussion`, `conclusion`, `references`, `appendix` u `other` |
| `segments[].kind` | `heading`, `paragraph`, `list_item`, `table` o `reference` |
| `segments[].content_hash` | SHA-256 del texto normalizado |
| `segments[].char_start` / `char_end` | Offsets sobre el texto concatenado del documento |
| `segments[].is_excluded` | `true` para tablas y referencias; no deben usarse en fases de análisis |

Errores específicos:

| HTTP | `error.code` | Situación |
| --- | --- | --- |
| 401 | `http_error` | Token ausente o inválido |
| 413 | `document_too_large` | Archivo por encima de `AI_MAX_DOCUMENT_BYTES` o más de `AI_MAX_SEGMENTS` fragmentos |
| 415 | `unsupported_type` | Extensión o MIME declarado no admitido |
| 422 | `empty_document` | Archivo vacío o sin texto extraíble |
| 422 | `invalid_document` | DOCX/PDF corrupto o ilegible |
| 422 | `ocr_unavailable` | PDF escaneado sin texto y OCR deshabilitado o Tesseract ausente |

Ejemplo con `curl`:

```bash
curl -s -X POST http://127.0.0.1:8001/internal/v1/documents/extract \
  -H "X-Internal-Token: $AI_INTERNAL_TOKEN" \
  -H "Accept: application/json" \
  -F "file=@tesis.docx"
```

## Autenticación entre servicios

```http
GET /internal/v1/health HTTP/1.1
Host: ai:8000
X-Internal-Token: <AI_INTERNAL_TOKEN>
X-Request-ID: 7f1c2f7e-6d5a-4c1b-9f0e-2a3b4c5d6e7f
Accept: application/json
```

- El token se compara con `secrets.compare_digest` (tiempo constante).
- Longitud mínima: 32 caracteres.
- Sin token o con token incorrecto: `401`.
- `X-Request-ID` se valida (1–128 caracteres alfanuméricos, `.`, `_` o `-`). Si falta o es inválido, FastAPI genera un UUID.
- La respuesta incluye siempre `X-Request-ID`.

Ejemplo con `curl` (sustituye el token):

```bash
curl -s http://127.0.0.1:8001/internal/v1/health \
  -H "X-Internal-Token: $AI_INTERNAL_TOKEN" \
  -H "Accept: application/json"
```

## Formato de errores de FastAPI

```json
{
  "error": {
    "code": "http_error",
    "message": "Credencial de servicio inválida."
  },
  "request_id": "7f1c2f7e-6d5a-4c1b-9f0e-2a3b4c5d6e7f"
}
```

| Código | Situación |
| --- | --- |
| `http_error` | Error HTTP controlado (401, 404, etc.) |
| `validation_error` | Datos de entrada inválidos (422) |
| `internal_error` | Error no controlado (500); el detalle solo queda en logs |

## Documentación interactiva

- `/docs` y `/openapi.json` están disponibles cuando `AI_ENVIRONMENT` no es `production`.
- En producción se desactivan para reducir superficie.
- La documentación interactiva sigue siendo interna: no publicar el puerto `8000`.

## Cliente Laravel

`App\Services\Ai\AiServiceClient` ofrece:

- `health(): array`
- `capabilities(): array`
- `extractDocument(string $filename, string $contents, string $mimeType): array` — envía el archivo en memoria como `multipart/form-data` y usa `AI_EXTRACTION_TIMEOUT_SECONDS` (120 por defecto).
- `isConfigured(): bool`

Los fallos se convierten en `AiServiceException` con códigos:

- `ai_not_configured`
- `ai_connection_failed`
- `ai_auth_failed`
- `ai_http_error`
- `ai_invalid_response`

Cuando FastAPI devuelve un error controlado de extracción, el cliente conserva el código original (`document_too_large`, `unsupported_type`, `empty_document`, `invalid_document`, `ocr_unavailable`) y el mensaje del motor. `ProcessDocument` los traduce a un estado `failed` con un código estable y no deja secciones ni fragmentos parciales.

Diagnóstico manual:

```powershell
docker compose exec app php artisan scitrace:check-ai
```

## Compatibilidad

- Los endpoints internos llevan prefijo `/internal/v1`.
- Cambios incompatibles requerirán `/internal/v2`.
- La extracción se añadió en la Fase 2 sin romper los contratos de salud y capacidades.
- La detección se añadió en la Fase 3 con `POST /internal/v1/detect`, autenticado y con límites explícitos.
- `detector_ready`, `active_model` y la capacidad `ai_detection` reflejan el estado real del artefacto cargado; nunca se fijan a mano en la respuesta.
- La Fase 4 añadirá la orquestación de análisis en Laravel, explicaciones e indicadores por fragmento. El endpoint de detección ya existe para poder verificarlo con el artefacto real.
