FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    ca-certificates \
    libzip-dev \
    unzip \
    && update-ca-certificates \
    && docker-php-ext-install pcntl bcmath sockets \
    && pecl install redis \
    && docker-php-ext-enable redis

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY --from=ghcr.io/roadrunner-server/roadrunner:latest /usr/bin/rr /usr/bin/rr

WORKDIR /app
COPY . .

RUN composer install --no-interaction --optimize-autoloader --no-dev \
    --ignore-platform-req=ext-sockets

RUN chown -R www-data:www-data /app
USER www-data
ENV HOME=/tmp

EXPOSE 8080

HEALTHCHECK --interval=10s --timeout=3s --start-period=10s --retries=3 \
    CMD php artisan app:health-check || exit 1

CMD ["php", "artisan", "octane:start", "--server=roadrunner", "--host=0.0.0.0", "--port=8080"]