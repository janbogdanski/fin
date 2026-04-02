FROM php:8.4-cli-alpine

# System deps
RUN apk add --no-cache \
    postgresql-dev \
    libsodium-dev \
    icu-dev \
    linux-headers \
    $PHPIZE_DEPS

# PHP extensions
RUN docker-php-ext-install \
    pdo_pgsql \
    intl \
    sodium \
    opcache \
    bcmath \
    calendar

# Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# PHP config
RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/memory.ini
RUN echo "opcache.enable_cli=1" > /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /app

COPY composer.json composer.lock* ./
RUN composer install --no-scripts --no-interaction --prefer-dist 2>/dev/null || true

COPY . .

RUN composer install --no-interaction --prefer-dist 2>/dev/null || true

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
