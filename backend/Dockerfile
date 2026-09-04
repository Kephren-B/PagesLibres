FROM php:8.5-fpm-alpine

RUN apk add --no-cache \
        postgresql-dev \
        icu-dev \
        libzip-dev \
        git \
        unzip \
    && docker-php-ext-install pdo pdo_pgsql intl zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
