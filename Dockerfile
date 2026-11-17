FROM node:22-alpine AS frontend

WORKDIR /build/frontend
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci
COPY frontend/ ./
RUN npm run build

FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libicu-dev \
        libonig-dev \
        libpq-dev \
        libsqlite3-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" intl mbstring opcache pdo_pgsql pdo_sqlite zip \
    && a2enmod rewrite \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!/var/www/!/var/www/html/public!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && echo 'ServerName localhost' > /etc/apache2/conf-available/server-name.conf \
    && a2enconf server-name \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY backend/ ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && mkdir -p public/app \
    && chown -R www-data:www-data storage bootstrap/cache

COPY --from=frontend /build/frontend/dist/ public/app/
COPY docker/entrypoint.sh /usr/local/bin/mini-booking-entrypoint

RUN chmod +x /usr/local/bin/mini-booking-entrypoint

ENTRYPOINT ["mini-booking-entrypoint"]
CMD ["apache2-foreground"]
