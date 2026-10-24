# SciTrace AI — aplicación Laravel

Aplicación web de SciTrace AI: Laravel 13, Livewire 4 y Tailwind CSS 4.

La documentación principal está en el [README del repositorio](../README.md).

## Comandos útiles

```powershell
# Pruebas
php artisan test --compact

# Estilo
php vendor/bin/pint --test

# Migraciones y datos iniciales
php artisan migrate --force
php artisan db:seed --force

# Diagnóstico del motor IA
php artisan scitrace:check-ai

# Rutas
php artisan route:list --except-vendor
```

## Base de datos local

`.env` puede usar SQLite para pruebas rápidas:

```env
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

En Docker se usa PostgreSQL 18.6. Las pruebas automatizadas usan SQLite en memoria (`phpunit.xml`).

## Assets

```powershell
npm install
npm run build
```

En Docker:

```powershell
docker compose --profile tools run --rm assets
```

## Estructura relevante

- `app/Livewire/Auth`: inicio de sesión, registro y recuperación.
- `app/Livewire/Admin`: administración de usuarios.
- `app/Livewire/Profile`: edición de perfil.
- `app/Http/Middleware`: cuenta activa, administrador y cabeceras.
- `app/Services/Ai`: cliente del microservicio FastAPI.
- `app/Services/Audit`: registro de auditoría.
- `database/migrations`: esquema inicial completo.
- `tests/Feature`: pruebas de autenticación, acceso, salud y dominio.

## Aviso

La Fase 1 no incluye un detector de IA. `detector_ready` es `false` y el sistema no debe presentar resultados de autoría.
