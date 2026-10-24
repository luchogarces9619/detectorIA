# Versiones verificadas

**Fecha de verificación:** 2026-10-23.

## Método

- PHP y paquetes Composer: `php -v` y `composer show`.
- Node y paquetes npm: `npm ls --depth=0`.
- Python: instalación real en un entorno virtual con `uv` y salida de `pip`.
- Imágenes Docker: consulta a la API de Docker Hub (`/v2/repositories/<repo>/tags/<tag>`). Se verificó que la etiqueta existe; **no se descargaron ni ejecutaron** porque Docker no está instalado en este equipo.
- Dependencias de extracción: instaladas realmente en el entorno virtual de la Fase 2 y verificadas con `uv pip list`; la suite las importa y ejecuta.
- PostgreSQL: binarios oficiales 18.6 para Windows, ejecutados localmente con `initdb`, migraciones y suite completa. **No se usó Docker.**
- Tesseract: instalado en Windows con `winget`; versión e idiomas comprobados con `tesseract --version` y `tesseract --list-langs`.
- Versiones objetivo de Fase 3: consulta a la API JSON de PyPI. Se verificó que la versión existe y es la última publicada; **no se instalaron**.
- Dependencias de entrenamiento: instaladas realmente con `uv` y verificadas con `importlib.metadata`; se usaron en una ejecución completa de entrenamiento y evaluación.

## Stack base (Fases 1 y 2, instalado y ejecutado)

| Componente | Versión | Verificación |
| --- | --- | --- |
| PHP (CLI local) | 8.4.27 | `php -v` |
| Laravel Framework | 13.38.0 | `composer show` |
| Livewire | 4.4.8 | `composer show` |
| Laravel Tinker | 3.0.2 | `composer show` |
| Pest | 4.7.8 | `composer show` |
| PHPUnit | 12.5.33 | `composer show` |
| Laravel Pint | 1.32.1 | `composer show` |
| Node.js | 24.22.1 | `node -v` |
| npm | 11.19.1 | `npm -v` |
| Vite | 8.3.5 | `npm ls` |
| Tailwind CSS | 4.3.3 | `npm ls` |
| `@tailwindcss/vite` | 4.3.3 | `npm ls` |
| `laravel-vite-plugin` | 3.2.0 | `npm ls` |
| Python | 3.12.15 | `python --version` (build standalone de `uv`) |
| FastAPI | 0.162.0 | `pip` |
| Starlette | 1.9.0 | resuelto por pip |
| Pydantic | 2.14.1 | resuelto por pip |
| pydantic-settings | 2.16.1 | `pip` |
| Uvicorn | 0.54.0 | `pip` |
| httpx | 0.28.1 | `pip` |
| pytest | 9.1.1 | `pip` |
| ruff | 0.17.2 | `pip` |
| PostgreSQL | 18.6 | `select version()` en binarios oficiales para Windows |
| Tesseract OCR | 5.4.0.20240606 | `tesseract --version` (UB Mannheim) |
| Datos de idioma `spa` | `tessdata_fast` | `tesseract --list-langs` |

## Dependencias de extracción de la Fase 2 (instaladas y ejecutadas)

| Componente | Versión | Verificación |
| --- | --- | --- |
| python-docx | 1.2.0 | `uv pip list`; suite de extracción |
| PyMuPDF | 2.0 | `uv pip list`; suite de extracción |
| pytesseract | 0.3.13 | `uv pip list`; prueba real de OCR |
| Pillow | 13.0.0 | `uv pip list` |
| python-multipart | 0.0.32 | `uv pip list`; endpoint multipart |
| Tesseract OCR | 5.4.0.20240606 | `tesseract --version`; OCR real con `spa+eng` |

Tesseract es un paquete del sistema, no de PyPI. En esta verificación se instaló en Windows y se añadió `spa.traineddata` desde `tessdata_fast`. La imagen `ai-service/Dockerfile` instala `tesseract-ocr`, `tesseract-ocr-spa` y `tesseract-ocr-eng`, pero **esa imagen no se ha construido en este equipo** porque Docker no está instalado. La versión de Tesseract dentro del contenedor no se ha comprobado.

## Imágenes Docker (etiqueta verificada, no ejecutada)

| Imagen | Etiqueta | Estado |
| --- | --- | --- |
| `php` | `8.4-fpm-bookworm` | Etiqueta existe |
| `composer` | `2` | Etiqueta existe |
| `nginx` | `1.30.6-alpine` | Etiqueta existe |
| `postgres` | `18.6-alpine` | Etiqueta existe |
| `redis` | `8.8.3-alpine` | Etiqueta existe |
| `node` | `24.22.1-alpine` | Etiqueta existe |
| `python` | `3.12.15-slim` | Etiqueta existe |
| `axllent/mailpit` | `v1.32.0` | Etiqueta existe |

> Nota: `php:8.4-fpm-bookworm` no fija la revisión de Debian ni el parche exacto de PHP. Para reproducibilidad estricta en producción conviene fijar un digest (`image@sha256:...`) después de la primera construcción verificada.

## Dependencias de la Fase 3 (instaladas y ejecutadas)

Estas dependencias se instalaron en el mismo entorno virtual de Python 3.12.15 y se usaron para construir el corpus, entrenar, calibrar y evaluar el detector. **El servicio en producción solo necesita `scikit-learn`, `joblib` y `numpy`; no necesita `torch`, `transformers` ni `langid`.**

| Componente | Versión | Verificación |
| --- | --- | --- |
| PyTorch | 2.14.1+cpu | `torch.__version__`; build solo CPU |
| Transformers | 5.20.2 | `transformers.__version__` |
| Accelerate | 1.15.0 | `importlib.metadata.version` |
| scikit-learn | 1.9.1 | `sklearn.__version__`; entrenamiento real |
| joblib | 1.6.0 | serialización del artefacto |
| NumPy | 2.5.4 | `numpy.__version__` |
| SciPy | 1.18.1 | resuelto por `uv` |
| langid | 1.1.6 | filtro real de idioma del corpus |
| llama.cpp | b11840 (`0.7.0-dev`, commit `36e36e63a`) | `llama-server --version` |
| Qwen2.5-1.5B-Instruct GGUF Q4_K_M | 1 117 320 736 bytes | SHA-256 `6a1a2eb6d15622bf3c96857206351ba97e1af16c30d7a74ee38970e434e9407e` |
| Qwen2.5-0.5B-Instruct GGUF Q4_K_M | 491 400 032 bytes | SHA-256 `74a4da8c9fdbcd15bd1f6d01d621410d31c6fc00986f5eb687824e7b93d7a9db` |

Los dos modelos GGUF se ejecutaron localmente con `llama-server` en `127.0.0.1`; no se usó ninguna API comercial. El equipo de desarrollo no tiene GPU NVIDIA, por lo que toda la generación y el entrenamiento se ejecutaron en CPU.

## Versiones descartadas para la Fase 3

| Componente | Motivo |
| --- | --- |
| `M4GT-Bench` | El repositorio no declara licencia y los datos están alojados en Google Drive. |
| `MULTITuDE` | El conjunto no es accesible públicamente (HTTP 401). |
| `SemEval-2024 Task 8` | El conjunto multilingüe no está accesible o no cubre español con licencia verificable. |
| `CarmenCL/HVSIA_AI_detection_spanish` | Solo 200 textos y sin licencia declarada. |

Se optó por construir un corpus propio y auditable con procedencia por documento.

## Compatibilidad comprobada

- Laravel 13.38.0 funciona con PHP 8.4.27 en las pruebas locales.
- Livewire 4.4.8, Pest 4.7.8 y PHPUnit 12.5.33 conviven sin conflictos en `composer.lock`.
- Vite 8.3.5, Tailwind CSS 4.3.3 y Node 24.22.1 generan `public/build/manifest.json` correctamente.
- FastAPI 0.162.0, Starlette 1.9.0, Pydantic 2.14.1 y Uvicorn 0.54.0 se resuelven e importan correctamente.
- La suite de FastAPI pasa con pytest 9.1.1 y httpx 0.28.1.
- python-docx 1.2.0, PyMuPDF 2.0, pytesseract 0.3.13, Pillow 13.0.0 y python-multipart 0.0.32 conviven en el mismo entorno y la suite de extracción pasa.
- Laravel 13 envía `multipart/form-data` a FastAPI y procesa la respuesta en el job `ProcessDocument` (verificado con SQLite, PostgreSQL 18.6 y servicios reales).
- PostgreSQL 18.6 acepta las 11 migraciones y la suite completa de Laravel (64 pruebas, 221 aserciones).
- Tesseract 5.4.0 con `spa+eng` procesa un PDF escaneado en español y la prueba real de OCR pasa.
- PyTorch 2.14.1+cpu, Transformers 5.20.2 y Accelerate 1.15.0 se importan y ejecutan en Python 3.12.15 sin GPU.
- scikit-learn 1.9.1, NumPy 2.5.4 y SciPy 1.18.1 conviven y entrenan el pipeline TF-IDF + regresión logística.
- joblib 1.6.0 serializa y vuelve a cargar el artefacto con calibración isotónica.
- llama.cpp b11840 sirve los GGUF de Qwen2.5 (1.5B y 0.5B) en `127.0.0.1` y genera texto en español.
- langid 1.1.6 clasifica como `es` el 100 % del corpus humano auditado.
- **No verificada:** la construcción de las imágenes Docker y la ejecución dentro de contenedores.

## Política de actualización

1. No se actualiza una dependencia principal sin ejecutar las suites de Laravel y del motor IA.
2. Antes de cada fase se vuelven a verificar las versiones objetivo.
3. Las imágenes de producción deben fijarse por digest cuando exista un entorno Docker verificado.
4. No se incorporan servicios de pago ni APIs comerciales de detección.
