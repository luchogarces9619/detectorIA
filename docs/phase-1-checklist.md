# Checklist de aceptación — Fase 1

**Fecha:** 2026-10-23.

> **Nota de vigencia:** este documento registra el cierre de la Fase 1. Los conteos de pruebas (37/11) corresponden a ese momento. Tras la Fase 2, la suite tiene 64 pruebas de Laravel (SQLite y PostgreSQL 18.6) y 30 de FastAPI con Tesseract (29 + 1 omitida sin él); la capacidad `document_extraction` pasó a `available`. Ver [phase-2.md](phase-2.md).

Estados usados:

- **Verificado:** se ejecutó en este equipo y produjo el resultado indicado.
- **Parcial:** implementado y probado en parte; falta una condición del entorno.
- **Pendiente:** no se ha podido ejecutar aquí.
- **Fuera de alcance:** corresponde a una fase posterior.

## Criterios de aceptación

| # | Criterio | Evidencia | Estado |
| --- | --- | --- | --- |
| 1 | Arquitectura definitiva del MVP | `docs/architecture.md` con componentes, fronteras y decisiones | Verificado |
| 2 | Versiones compatibles verificadas | `docs/versions.md`; `composer show`, `npm ls`, `pip`, API de Docker Hub y PyPI | Verificado |
| 3 | Requisitos de Windows | `README.md`: Windows 10/11, WSL2, Docker Desktop, RAM, puertos | Verificado |
| 4 | Estructura del repositorio | `README.md` y árbol real del proyecto | Verificado |
| 5 | Diagrama de componentes | Mermaid en `docs/architecture.md` | Verificado |
| 6 | Diseño inicial de PostgreSQL | 11 migraciones y `docs/database.md`; `migrate:fresh` correcto con SQLite y PostgreSQL 18.6 | Verificado en local; falta la variante con contenedores |
| 7 | Docker Compose | `docker-compose.yml` con 9 servicios, healthchecks, redes y volúmenes | Parcial: no validado con Docker |
| 8 | Laravel base | 37 pruebas, 112 aserciones; rutas y assets correctos | Verificado en local |
| 9 | FastAPI base | 11 pruebas; `ruff check` sin errores | Verificado en local |
| 10 | Comunicación entre servicios | `AiServiceClient`, token, `X-Request-ID`, pruebas con `Http::fake` y llamada real Laravel → FastAPI en local | Verificado en local; falta la variante con contenedores |
| 11 | Primera prueba con contenedores | No ejecutada | Pendiente: Docker no instalado |
| 12 | Checklist de aceptación | Este documento | Verificado |
| 13 | Estado implementado / pendiente / siguiente | Este documento y `README.md` | Verificado |
| 14 | Sin detector no validado | `detector_ready=false`; al cierre de la Fase 1, `/capabilities` marcaba extracción y detección como `planned` (extracción pasó a `available` en la Fase 2) | Verificado |

## Evidencia de ejecución local

```text
Laravel: 37 tests, 112 assertions, 0 failures
FastAPI: 11 passed
ruff: All checks passed
Pint: passed
Vite: built in 1.83s; public/build/manifest.json generado
Migraciones: 11/11 + AdminUserSeeder
Rutas Laravel: 12 rutas de aplicación
```

### Pruebas HTTP locales ejecutadas (sin Docker)

FastAPI con Uvicorn y la fábrica `create_app`:

- `GET /health` → `200`, `detector_ready=false`.
- `GET /internal/v1/health` con token → `200`.
- `GET /internal/v1/capabilities` con token → `200`, 5 capacidades.
- `GET /internal/v1/health` sin token → `401`.
- `php artisan scitrace:check-ai` con `AI_SERVICE_URL=http://127.0.0.1:8011` → éxito y confirmación de que el detector no está operativo.

Laravel con `php artisan serve`:

- `GET /up` → `200`.
- `GET /api/v1/health` → `200`, `database=ok`.
- `GET /login` → `200`, formulario presente y CSS de Vite enlazado.
- `GET /dashboard` sin sesión → `302` a inicio de sesión.

Comandos ejecutados:

```powershell
cd src
php artisan test --compact
php vendor/bin/pint --test
php artisan migrate:fresh --seed --force
php artisan route:list --except-vendor
php artisan config:cache
php artisan about
php artisan config:clear

cd ..\ai-service
python -m ruff check .
python -m pytest -q
```

## Prueba de humo pendiente

Estos pasos **no se ejecutaron** y no deben darse por buenos hasta completarlos en un equipo con Docker Desktop y WSL2:

```powershell
docker compose config
docker compose build
docker compose up -d --wait
docker compose --profile tools run --rm assets
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
powershell -ExecutionPolicy Bypass -File scripts/verify.ps1
```

Criterios de la prueba de humo:

- [ ] `docker compose config` valida sin errores.
- [ ] Las imágenes base se descargan y las imágenes propias se construyen.
- [ ] Los servicios quedan en ejecución; `app`, `web`, `ai`, `db` y `redis` superan sus healthchecks.
- [ ] `http://localhost:8080/up` responde `200`.
- [ ] `http://localhost:8080/api/v1/health` responde `200` con `database: ok`.
- [ ] `http://localhost:8001/health` responde `200` con `detector_ready: false`.
- [ ] `docker compose exec app php artisan scitrace:check-ai` termina con éxito.
- [ ] Las migraciones corren contra PostgreSQL 18.6.
- [ ] El administrador se crea y puede iniciar sesión.
- [ ] El correo de recuperación llega a Mailpit.
- [ ] `scripts/verify.ps1` termina sin errores.
- [ ] No hay secretos en la salida de logs.

## Implementado en la Fase 1

- Estructura monorepo (`src`, `ai-service`, `docker`, `scripts`, `docs`).
- Docker Compose con PHP-FPM, Nginx, worker, FastAPI, PostgreSQL, Redis y Mailpit.
- Dockerfiles de PHP y FastAPI (este último con etapas `runtime` y `test`).
- Laravel 13 + Livewire 4 + Tailwind 4.
- Autenticación, registro, recuperación de contraseña y perfil.
- Roles, estados y middleware.
- Panel administrativo de usuarios.
- Auditoría de eventos.
- Endpoints de salud.
- Cliente del motor IA con manejo de errores.
- Microservicio FastAPI con token interno, request ID y envelope de errores.
- Esquema completo de datos.
- Suites de pruebas y scripts de arranque/verificación.

## Pendiente o fuera de alcance

- Prueba real con contenedores y PostgreSQL.
- Prueba de navegador de extremo a extremo.
- Carga y validación de archivos.
- Extracción DOCX/PDF.
- OCR con Tesseract.
- Segmentación.
- Detector entrenado, calibrado y versionado.
- Inferencia y explicaciones.
- Informes PDF.
- Purga de retención, backups y observabilidad de producción.
- CSP, verificación de correo, 2FA y antimalware.

## Siguiente paso

**Fase 2 — Gestión y extracción documental:**

1. Validar la prueba de humo de la Fase 1 en Docker.
2. Diseñar la carga privada con límites de tamaño, MIME y extensión.
3. Implementar extracción con `python-docx` y PyMuPDF.
4. Añadir OCR con Tesseract para PDF escaneado.
5. Segmentar por secciones y párrafos, excluyendo referencias y tablas.
6. Persistir secciones y fragmentos con hashes.
7. Probar con documentos académicos de ejemplo, incluyendo casos corruptos y escaneados.

No se avanzará a la Fase 3 hasta que la Fase 2 tenga criterios de aceptación verificados.
