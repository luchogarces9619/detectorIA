#!/bin/sh
set -eu

cd /var/www/html

mkdir -p \
    storage/app/private/documents \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

# En desarrollo el código se monta desde el host y vendor/ puede no existir.
if [ ! -f vendor/autoload.php ]; then
    echo "[scitrace] vendor/ no encontrado; ejecutando composer install..."
    composer install --no-interaction --prefer-dist
fi

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

if [ "${1:-}" = "php-fpm" ]; then
    exec php-fpm
fi

# Los procesos de consola y colas se ejecutan sin privilegios.
exec gosu www-data "$@"
