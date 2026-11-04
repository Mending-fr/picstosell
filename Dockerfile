# --- Interface web (Vue) ---
FROM node:22-bookworm-slim AS web
WORKDIR /src
COPY web/package*.json web/
RUN cd web && (npm ci || npm install)
COPY web/ web/
# Tant que l'interface web n'est pas terminée, un échec de compilation n'empêche pas l'API de démarrer (/app répond 503).
RUN mkdir -p backend/public/app && cd web && (npm run build || echo "Interface web non compilée")

# --- Dépendances PHP ---
FROM composer:2 AS vendor
WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader --ignore-platform-reqs
COPY backend/ ./
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# --- Image finale (FrankenPHP) ---
FROM dunglas/frankenphp:1-php8.3-bookworm
RUN install-php-extensions pdo_pgsql redis gd intl zip pcntl opcache bcmath
ENV SERVER_NAME=:8080 APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr
WORKDIR /app
COPY --from=vendor /app /app
COPY --from=web /src/backend/public/app /app/public/app
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/start.sh /usr/local/bin/start
RUN chmod +x /usr/local/bin/start && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
EXPOSE 8080
CMD ["start", "web"]
