# ---------- Build : dépendances de production uniquement ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-progress --prefer-dist --optimize-autoloader

# ---------- Runtime : PHP-FPM + nginx (API) ----------
FROM php:8.5-fpm-alpine
RUN apk add --no-cache \
        postgresql-dev \
        icu-dev \
        libzip-dev \
        git \
        unzip \
        nginx \
    && docker-php-ext-install pdo pdo_pgsql intl zip \
    && echo "expose_php = Off" > $PHP_INI_DIR/conf.d/security.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .
COPY --from=vendor /app/vendor ./vendor

RUN mkdir -p var/cache var/log /run/nginx \
    && chown -R www-data:www-data var /run/nginx

# nginx sert l'API (port 80) ; php-fpm écoute en local (127.0.0.1:9000).
COPY docker/nginx/nginx-prod.conf /etc/nginx/conf.d/default.conf
COPY docker/entrypoint-prod.sh /usr/local/bin/entrypoint-prod
RUN chmod +x /usr/local/bin/entrypoint-prod

EXPOSE 80
CMD ["entrypoint-prod"]
