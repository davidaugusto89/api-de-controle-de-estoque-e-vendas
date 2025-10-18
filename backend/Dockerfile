# ===========================
# Base comum
# ===========================
FROM php:8.4-fpm-alpine

# ------------------------------------------------------------
# 🧱 SO deps (runtime + build p/ PECL) e PHP extensions
# ------------------------------------------------------------
RUN set -eux; \
    apk add --no-cache \
        git \
        curl \
        tzdata \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        zip \
        unzip \
    ; \
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        openssl-dev \
    ; \
    docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        zip \
        exif \
        pcntl \
        bcmath \
        intl \
    ; \
    # Redis (phpredis) - recomendado p/ cache/queue/session
    pecl install redis; \
    docker-php-ext-enable redis pcntl; \
    \
    # OpenTelemetry (opcional; mantém simples e resiliente ao falhar)
    pecl install opentelemetry || true; \
    docker-php-ext-enable opentelemetry || true; \
    \
    # Limpa dependências de build
    apk del .build-deps

# ------------------------------------------------------------
# 🔧 OPcache (ajuste validate_timestamps=1 p/ dev com bind mount)
# ------------------------------------------------------------
RUN { \
    echo "opcache.enable=1"; \
    echo "opcache.enable_cli=1"; \
    echo "opcache.validate_timestamps=1"; \
    echo "opcache.jit_buffer_size=64M"; \
    echo "opcache.jit=tracing"; \
} > /usr/local/etc/php/conf.d/opcache.ini

# ------------------------------------------------------------
# 🌐 OTEL defaults (pode sobrescrever no compose/.env)
# ------------------------------------------------------------
ENV OTEL_SERVICE_NAME="sistema-de-estoque-vendas" \
    OTEL_TRACES_EXPORTER="otlp" \
    OTEL_EXPORTER_OTLP_PROTOCOL="http/protobuf" \
    OTEL_EXPORTER_OTLP_ENDPOINT="http://jaeger:4318" \
    OTEL_PROPAGATORS="tracecontext,baggage" \
    COMPOSER_ALLOW_SUPERUSER=1 \
    TZ=UTC

# ------------------------------------------------------------
# 📁 App
# ------------------------------------------------------------
WORKDIR /var/www/html

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copia o projeto (assuma que este Dockerfile está na pasta /backend)
COPY . .

# Instala dependências (inclui dev p/ rodar PHPUnit no mesmo container)
RUN composer install --no-interaction --prefer-dist --no-progress

# Permissões (www-data padrão do php-fpm)
RUN mkdir -p bootstrap/cache storage/framework storage/logs \
 && chown -R www-data:www-data bootstrap/cache storage \
 && chmod -R 775 bootstrap/cache storage

CMD ["php-fpm"]
