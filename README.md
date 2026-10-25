# SciTrace AI

Plataforma web para el análisis **orientativo** de escritura académica en español. SciTrace AI organiza documentos, extrae y segmenta su texto y, en fases posteriores, aplicará un detector calibrado. Los resultados **no constituyen prueba de autoría ni un porcentaje definitivo de texto generado por IA**.

Este repositorio contiene las **Fases 1, 2 y 3**. La Fase 1 entrega los fundamentos (autenticación, roles, infraestructura, comunicación Laravel ↔ FastAPI). La Fase 2 añade la carga privada de documentos, la extracción de texto y la segmentación. La Fase 3 entrega un **detector calibrado** con métricas reales sobre particiones no vistas.

## Estado actual

| Área | Estado |
| --- | --- |
| Arquitectura y decisiones documentadas | Implementado |
| Esquema PostgreSQL inicial (11 migraciones) | Implementado; ejecutado con SQLite y PostgreSQL 18.6 |
| Laravel 13 + Livewire 4 (auth, roles, perfil, administración) | Implementado; incluido en la suite |
| Carga privada de DOCX/PDF con validación y consentimiento | Implementado y verificado en navegador |
| Extracción DOCX (`python-docx`) y PDF (`PyMuPDF`) | Implementado y verificado con servicios reales |
| OCR con Tesseract | Implementado y verificado con Tesseract 5.4.0 + `spa+eng` |
| Segmentación, exclusión de tablas/referencias y offsets | Implementado y verificado |
| Laravel + Pest | **68 pruebas, 238 aserciones** en SQLite y en PostgreSQL 18.6 |
| FastAPI + pytest | **56 pasan, 1 omitida** con Tesseract; ruff limpio |
| Assets Vite/Tailwind | Compilados correctamente |
| Comunicación Laravel ↔ FastAPI | Verificada en local con HTTP real y token interno |
| PostgreSQL 18.6 | **Verificado**: 11 migraciones y suite completa con binarios nativos |
| Dockerfiles y `docker-compose.yml` | Escritos; etiquetas de imagen verificadas |
| Prueba de humo con contenedores | **Pendiente de entorno**: Docker no está instalado |
| **Detector de IA (Fase 3)** | **Entrenado, calibrado y evaluado**: ROC AUC 0,9987 (docs), 0,9994 (OOD) |

La tabla anterior es deliberada: nada que no se haya ejecutado se presenta como verificado. Ver [docs/phase-1-checklist.md](docs/phase-1-checklist.md), [docs/phase-2.md](docs/phase-2.md) y [docs/phase-3.md](docs/phase-3.md).

## Qué hace hoy

- Registro, inicio y cierre de sesión con Livewire.
- Recuperación de contraseña por correo (Mailpit en desarrollo).
- Roles `admin` y `user`, estados `active` y `suspended`.
- Middleware de cuenta activa y de administrador.
- Panel de usuario, edición de perfil y panel administrativo de usuarios.
- Cambio de rol y suspensión de cuentas con protecciones básicas.
- Endpoint de salud de Laravel (`/api/v1/health`, `/up`).
- Microservicio FastAPI con `/health`, `/internal/v1/health`, `/internal/v1/capabilities`, `/internal/v1/documents/extract` y **`/internal/v1/detect`**.
- Autenticación entre servicios mediante `X-Internal-Token`.
- Auditoría de eventos de autenticación, administración y documentos.
- Carga privada de DOCX/PDF con límite de tamaño, validación de firma real y consentimiento.
- Extracción de texto, secciones y fragmentos; exclusión de tablas y referencias.
- Detalle del documento con métricas, contenido extraído y ficha técnica.
- Listado, descarga autorizada, reprocesamiento y eliminación.
- Esquema completo para documentos, secciones, segmentos, análisis, predicciones, informes y versiones de modelo.
- **Detección orientativa de IA en texto académico en español** con umbrales calibrados y abstención (`not_conclusive`).
- **Evaluación OOD** con generador distinto (Qwen2.5-0.5B) que confirma generalización.

## Qué no hace todavía

- No genera informes PDF.
- No ejecuta purgas de retención.
- No entrena modelos con documentos de usuarios (requiere autorización explícita LOPDP).
- No permite que los administradores lean el contenido documental.
- No orquesta el análisis automático de documentos subidos (Fase 4).
- No se ha verificado dentro de contenedores Docker; PostgreSQL y OCR sí se verificaron de forma nativa.

## Requisitos en Windows

Para la ruta recomendada con Docker:

- Windows 10 22H2 o Windows 11, 64 bits.
- Virtualización habilitada en la BIOS/UEFI.
- WSL2 instalado y actualizado.
- Docker Desktop con Docker Compose v2.
- 8 GB de RAM mínimo; 16 GB recomendados.
- 15 GB de espacio libre aproximados para imágenes, volúmenes y dependencias.
- Puertos `8080`, `8001` y `8025` libres en `127.0.0.1`.

Para desarrollo sin Docker (opcional):

- PHP 8.4 con `pdo_pgsql`, `pdo_sqlite`, `mbstring`, `openssl`, `fileinfo`, `curl`, `zip`, `intl`, `bcmath` y `redis`.
- Composer 2.
- Node.js 24 y npm 11.
- Python 3.12.
- PostgreSQL 18 y Redis 8 (o SQLite para pruebas puntuales).

Las versiones exactas verificadas están en [docs/versions.md](docs/versions.md).

## Inicio rápido con Docker

```powershell
powershell -ExecutionPolicy Bypass -File scripts/bootstrap.ps1
```

El script:

1. Crea `.env` a partir de `.env.example` si no existe.
2. Genera `APP_KEY`, contraseñas de PostgreSQL y Redis, token interno y contraseña del administrador.
3. Construye las imágenes e inicia los servicios.
4. Compila los assets con Vite.
5. Ejecuta migraciones y el seeder del administrador.

Si prefieres hacerlo manualmente:

```powershell
Copy-Item .env.example .env
# Edita .env y define APP_KEY, DB_PASSWORD, REDIS_PASSWORD,
# AI_INTERNAL_TOKEN (mínimo 32 caracteres) y SCITRACE_ADMIN_PASSWORD (mínimo 12).

docker compose build
docker compose up -d --wait
docker compose --profile tools run --rm assets
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
```

Servicios locales:

- Aplicación: <http://localhost:8080>
- Mailpit: <http://localhost:8025>
- Motor IA (solo depuración): <http://localhost:8001/health>

El correo del administrador se toma de `SCITRACE_ADMIN_EMAIL`. La contraseña se muestra al final de `scripts/bootstrap.ps1`; si la definiste manualmente, es la que escribiste en `.env`.

## Verificación

Con los contenedores levantados:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/verify.ps1
```

Con herramientas locales instaladas y el entorno Python activado:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/verify.ps1 -Local
```

Comandos equivalentes:

```powershell
# Laravel
cd src
php artisan test --compact
php vendor/bin/pint --test

# Motor IA
cd ai-service
python -m ruff check .
python -m pytest -q
```

## Estructura del repositorio

```text
detector IA/
├── docker-compose.yml
├── .env.example
├── docker/
│   ├── nginx/default.conf
│   └── php/
│       ├── Dockerfile
│       ├── entrypoint.sh
│       └── php.ini
├── scripts/
│   ├── bootstrap.ps1
│   └── verify.ps1
├── docs/
│   ├── architecture.md
│   ├── database.md
│   ├── api-v1.md
│   ├── security.md
│   ├── versions.md
│   ├── phase-1-checklist.md
│   ├── phase-2.md
│   └── assets/
├── src/                      # Laravel 13 + Livewire 4
│   ├── app/
│   │   ├── Jobs/ProcessDocument.php
│   │   ├── Livewire/Documents/
│   │   ├── Policies/DocumentPolicy.php
│   │   └── Services/Ai/AiServiceClient.php
│   ├── config/
│   ├── database/migrations/
│   ├── resources/
│   ├── routes/
│   └── tests/
└── ai-service/               # FastAPI
    ├── app/
    │   ├── api/v1/endpoints/documents.py
    │   └── services/extraction.py
    ├── tests/
    ├── Dockerfile
    └── requirements*.txt
```

## Documentación

- [Arquitectura y límites](docs/architecture.md)
- [Base de datos](docs/database.md)
- [API interna v1](docs/api-v1.md)
- [Seguridad y privacidad](docs/security.md)
- [Versiones verificadas](docs/versions.md)
- [Checklist de aceptación de la Fase 1](docs/phase-1-checklist.md)
- [Fase 2: carga, extracción y segmentación](docs/phase-2.md)

## Privacidad y uso responsable

- Los documentos se almacenan en un disco privado, fuera de `public/`.
- No se usan documentos de usuarios para entrenar modelos sin autorización explícita.
- El diseño considera la Ley Orgánica de Protección de Datos Personales de Ecuador (LOPDP): minimización, finalidad, retención y trazabilidad.
- Un resultado del futuro detector será una señal para revisión humana, nunca una acusación.
- El sistema debe poder devolver un resultado **no concluyente**.

## Siguiente fase

La Fase 3 está **completa**: corpus real (862 humanos + 811 sintéticos), modelo TF-IDF + Regresión Logística calibrado, umbrales con abstención validados, evaluación OOD exitosa, artefacto versionado y registrado en Laravel. Ver [docs/phase-3.md](docs/phase-3.md) e [informe del modelo](docs/phase-3-model-report.md).

La Fase 4 conectará el detector con la interfaz: orquestación del análisis, almacenamiento de predicciones, explicaciones por fragmento y revisión humana.
