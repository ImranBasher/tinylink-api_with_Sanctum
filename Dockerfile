# ---- Stage 1: install PHP dependencies with Composer ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev

# ---- Stage 2: runtime image ----
FROM php:8.3-cli

# System deps + PHP extensions Laravel/MySQL need
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www

# App code + vendor from the build stage
COPY --from=vendor /app /var/www

# Storage/cache dirs must be writable
RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY start.sh /var/www/start.sh
RUN chmod +x /var/www/start.sh

EXPOSE 10000
CMD ["/var/www/start.sh"]
