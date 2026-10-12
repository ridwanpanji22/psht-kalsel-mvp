FROM php:8.3-fpm-alpine AS base

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/composer \
    APP_ENV=local \
    APP_DEBUG=false \
    APP_TIMEZONE=Asia/Jakarta \
    PHP_OPCACHE_ENABLE=1

RUN apk add --no-cache \
        nginx \
        supervisor \
        curl \
        git \
        unzip \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        sqlite-dev \
        nodejs \
        npm \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_sqlite \
        bcmath \
        intl \
        zip \
        mbstring \
        gd \
        pcntl \
        opcache \
    && rm -rf /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Application source
COPY . /var/www/html

# Install PHP and Node dependencies, then build front-end assets.
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && npm ci --ignore-scripts \
    && npm run build \
    && rm -rf node_modules

# Writable runtime directories and the public storage symlink.
RUN mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache database \
    && touch database/database.sqlite \
    && chown www-data:www-data database/database.sqlite \
    && php artisan storage:link --force

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
