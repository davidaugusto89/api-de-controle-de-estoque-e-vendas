# ===========================
# Base comum
# ===========================
FROM php:8.4-fpm-alpine

# ------------------------------------------------------------
# 🧱 SO deps (runtime + build p/ PECL) e PHP extensions
# ------------------------------------------------------------
RUN set -eux; \
    # deps de runtime
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
    # deps de build (inclui linux-headers p/ xdebug)
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        openssl-dev \
        linux-headers \
    ; \
    # extensões nativas
    docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        zip \
        exif \
        pcntl \
        bcmath \
        intl \
    ; \
    # Redis (phpredis)
    pecl install redis; \
    docker-php-ext-enable redis pcntl; \
    \
    # Xdebug (para coverage) — precisa de linux-headers no Alpine
    pecl install xdebug; \
    docker-php-ext-enable xdebug; \
    \
    # OpenTelemetry (opcional; resiliente ao falhar)
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
# 🐞 Xdebug – por padrão desligado; habilite via XDEBUG_MODE
# ------------------------------------------------------------
RUN { \
    echo "xdebug.mode=\${XDEBUG_MODE:-off}"; \
    echo "xdebug.start_with_request=0"; \
    echo "xdebug.discover_client_host=0"; \
    echo "xdebug.output_dir=/tmp/xdebug"; \
} > /usr/local/etc/php/conf.d/xdebug.ini

# ------------------------------------------------------------
# 🌐 OTEL defaults
# ------------------------------------------------------------
ENV OTEL_SERVICE_NAME="sistema-de-estoque-vendas" \
    OTEL_TRACES_EXPORTER="otlp" \
    OTEL_EXPORTER_OTLP_PROTOCOL="http/protobuf" \
    OTEL_EXPORTER_OTLP_ENDPOINT="http://jaeger:4318" \
    OTEL_PROPAGATORS="tracecontext,baggage" \
    COMPOSER_ALLOW_SUPERUSER=1 \
    TZ=UTC \
    XDEBUG_MODE=off

# ------------------------------------------------------------
# 📁 App
# ------------------------------------------------------------
WORKDIR /var/www/html

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copia o projeto
COPY . .

# Instala dependências
RUN composer install --no-interaction --prefer-dist --no-progress

# Permissões + pasta de coverage
RUN mkdir -p bootstrap/cache storage/framework storage/logs coverage /tmp/xdebug \
 && chown -R www-data:www-data bootstrap/cache storage coverage /tmp/xdebug \
 && chmod -R 775 bootstrap/cache storage coverage /tmp/xdebug

CMD ["php-fpm"]
