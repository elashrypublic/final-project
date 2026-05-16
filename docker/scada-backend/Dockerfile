# to build this docker image use this:


FROM php:8.3-cli-alpine

# System dependencies
RUN apk add --no-cache \
    bash \
    git \
    unzip \
    curl \
    libzip-dev \
    oniguruma-dev \
    icu-dev \
    mysql-client

# PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mbstring \
    intl \
    zip

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Security
RUN addgroup -S laravelgroup && adduser -S laraveluser -G laravelgroup

WORKDIR /var/www

# Copy full Laravel project first
COPY Scada-main-system/ .

# Install dependencies
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction

# Permissions
RUN chown -R laraveluser:laravelgroup /var/www
RUN chmod -R 775 storage bootstrap/cache

RUN rm -f bootstrap/cache/*.php

USER laraveluser

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]