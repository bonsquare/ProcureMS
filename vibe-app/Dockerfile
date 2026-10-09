# ProcureMS on Railway: PHP 8.4 with FrankenPHP (Caddy). The database is SQLite on a Railway volume mounted at /data.
FROM dunglas/frankenphp:1-php8.4

RUN apt-get update && apt-get install -y --no-install-recommends git unzip && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions pdo_sqlite intl zip bcmath opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Dependencies first so they are cached between builds.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && php artisan package:discover --ansi \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

COPY deploy/Caddyfile /etc/caddy/Caddyfile
COPY deploy/php.ini /usr/local/etc/php/conf.d/zz-procurems.ini
COPY deploy/entrypoint.sh /usr/local/bin/procurems-entrypoint
RUN chmod +x /usr/local/bin/procurems-entrypoint

ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr
EXPOSE 8080
ENTRYPOINT ["procurems-entrypoint"]
