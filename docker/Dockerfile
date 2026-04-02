FROM php:8.4-cli-alpine AS base

# System deps
RUN apk add --no-cache \
    postgresql-dev \
    libsodium-dev \
    icu-dev \
    libzip-dev \
    libffi-dev \
    linux-headers \
    $PHPIZE_DEPS

# PHP extensions
RUN docker-php-ext-install \
    pdo_pgsql \
    intl \
    sodium \
    opcache \
    bcmath \
    calendar \
    zip \
    ffi

# Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Cleanup build deps to reduce image size
RUN apk del $PHPIZE_DEPS linux-headers

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# PHP config
COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini

# Non-root user
RUN addgroup -g 1000 appuser && adduser -u 1000 -G appuser -s /bin/sh -D appuser

WORKDIR /app

# Install dependencies first (layer caching)
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-interaction --prefer-dist --no-dev=false

# Copy application
COPY . .

# Run post-install scripts
RUN composer run-script auto-scripts --no-interaction || true

# Fix permissions
RUN chown -R appuser:appuser /app/var 2>/dev/null || mkdir -p /app/var && chown -R appuser:appuser /app/var

USER appuser

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=3s --retries=3 \
    CMD wget -qO- http://localhost:8080/ || exit 1

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
