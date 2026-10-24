# SciTrace AI — motor de análisis

Microservicio FastAPI de SciTrace AI. En la Fase 2 expone salud, capacidades, autenticación interna y **extracción/segmentación de DOCX y PDF**. **No ejecuta un detector de IA** ni emite juicios de autoría.

## Requisitos

- Python 3.12.
- Dependencias de `requirements.txt` (producción) o `requirements-dev.txt` (pruebas).
- Tesseract con los idiomas `spa` y `eng` **solo** para OCR real. La imagen Docker los instala; en el host es opcional. Verificado con Tesseract 5.4.0 y `spa` de `tessdata_fast`.

## Ejecución local

```powershell
python -m venv .venv
.venv\Scripts\Activate.ps1
pip install -r requirements-dev.txt

$env:AI_INTERNAL_TOKEN = "un-token-de-al-menos-32-caracteres-para-desarrollo"
uvicorn app.main:create_app --factory --host 127.0.0.1 --port 8001
```

Endpoints:

- `GET http://127.0.0.1:8001/health` — público.
- `GET http://127.0.0.1:8001/internal/v1/health` — requiere `X-Internal-Token`.
- `GET http://127.0.0.1:8001/internal/v1/capabilities` — requiere `X-Internal-Token`.
- `POST http://127.0.0.1:8001/internal/v1/documents/extract` — requiere `X-Internal-Token` y `multipart/form-data` con el campo `file`.
- `GET http://127.0.0.1:8001/docs` — solo fuera de producción.

Ejemplo de extracción:

```powershell
curl.exe -s -X POST http://127.0.0.1:8001/internal/v1/documents/extract `
  -H "X-Internal-Token: $env:AI_INTERNAL_TOKEN" `
  -H "Accept: application/json" `
  -F "file=@C:\ruta\tesis.docx"
```

El archivo se procesa **en memoria** y no se persiste en este servicio. Laravel es el único responsable del almacenamiento, los permisos y la auditoría.

## Qué extrae

- DOCX: párrafos, listas, tablas y encabezados; secciones por estilo y numeración académica.
- PDF: texto por página con PyMuPDF; OCR con Tesseract en páginas con poco texto.
- Segmentos con offsets de caracteres, hash SHA-256 y número de palabras.
- Tablas y referencias se guardan pero se marcan `is_excluded=true`.

Límites: `.docx`/`.pdf`, 25 MB y 10 000 fragmentos por defecto. Un PDF escaneado sin OCR disponible falla con `ocr_unavailable` en lugar de devolver texto inventado.

## Variables de entorno

| Variable | Obligatoria | Descripción |
| --- | --- | --- |
| `AI_INTERNAL_TOKEN` | Sí | Token compartido con Laravel; mínimo 32 caracteres |
| `AI_ENVIRONMENT` | No | `local`, `test` o `production` |
| `AI_LOG_LEVEL` | No | `DEBUG`, `INFO`, `WARNING`, `ERROR` o `CRITICAL` |
| `AI_DETECTOR_READY` | No | Debe permanecer en `false` |
| `AI_ACTIVE_MODEL` | No | Vacío mientras no exista un modelo calibrado |
| `AI_MAX_DOCUMENT_BYTES` | No | Tamaño máximo del archivo (25 MB por defecto) |
| `AI_MAX_SEGMENTS` | No | Fragmentos máximos (10 000 por defecto) |
| `AI_OCR_ENABLED` | No | Activa el OCR (`true` por defecto) |
| `AI_OCR_LANGUAGES` | No | Idiomas de Tesseract (`spa+eng` por defecto) |
| `AI_OCR_DPI` | No | Resolución de rasterizado (200 por defecto) |
| `AI_MIN_TEXT_CHARS_PER_PAGE` | No | Umbral para intentar OCR (20 por defecto) |

No se incluyen credenciales por defecto.

## Pruebas

```powershell
python -m ruff check .
python -m pytest -q
```

En Docker:

```powershell
docker compose --profile tools run --rm --build ai-tests
```

La prueba de OCR real se omite cuando Tesseract no está instalado. La suite no simula OCR como si hubiera funcionado: comprueba el comportamiento condicional. En la verificación de cierre se ejecutó con Tesseract 5.4.0 y `spa+eng`: **30 pruebas pasan, 0 omitidas**.

## Docker

El `Dockerfile` tiene dos etapas:

- `runtime`: dependencias de producción, Tesseract con `spa`/`eng` y Uvicorn.
- `test`: añade pytest, ruff y la suite de pruebas.

Compose usa `target: runtime` para el servicio `ai` y `target: test` para `ai-tests`.

## Aviso

`detector_ready=false` es intencional. La capacidad `ai_detection` permanece en `planned`. El servicio no debe usarse para afirmar autoría ni calcular porcentajes de IA.
