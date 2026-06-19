FROM php:8.3-fpm-bookworm AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx supervisor curl git unzip \
    libpng-dev libzip-dev libicu-dev libxml2-dev libonig-dev \
    && docker-php-ext-install pdo_mysql mbstring bcmath exif pcntl zip intl gd \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY . .

RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

FROM node:20-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY vite.config.js ./
COPY resources ./resources
COPY --from=base /var/www/html/vendor ./vendor
RUN mkdir -p storage/framework/views
RUN npm run build

FROM base AS final

COPY --from=assets /app/public/build /var/www/html/public/build

COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/healthcheck.sh /usr/local/bin/healthcheck.sh
RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/healthcheck.sh \
    && rm -f bootstrap/cache/*.php

EXPOSE 80

HEALTHCHECK --interval=10s --timeout=5s --start-period=180s --retries=12 \
  CMD /usr/local/bin/healthcheck.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
