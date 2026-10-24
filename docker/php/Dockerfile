# syntax=docker/dockerfile:1.7
FROM php:8.4-fpm-bookworm

ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        gosu \
        libicu-dev \
        libpq-dev \
        libzip-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        pdo_sqlite \
        pgsql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-scitrace.ini
COPY docker/php/entrypoint.sh /usr/local/bin/scitrace-entrypoint
RUN chmod 755 /usr/local/bin/scitrace-entrypoint

WORKDIR /var/www/html

# Las dependencias se instalan en la imagen para que el primer arranque sea rápido.
# El entrypoint vuelve a instalar si el bind mount del host oculta vendor/.
COPY src/composer.json src/composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY src/ ./
RUN composer dump-autoload --optimize --no-interaction \
    && mkdir -p \
        storage/app/private/documents \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["scitrace-entrypoint"]
CMD ["php-fpm"]
