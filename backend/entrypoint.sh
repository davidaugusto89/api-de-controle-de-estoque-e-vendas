#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

# Se não houver APP_KEY no .env, gera
if ! grep -q "^APP_KEY=" .env >/dev/null 2>&1 || [ -z "$(grep '^APP_KEY=' .env | cut -d= -f2-)" ]; then
  php artisan key:generate --force || true
fi

# Migra + seed e aquece caches (idempotente)
php -d memory_limit=1G artisan migrate --force --seed || true

php artisan config:cache    || true
php artisan route:cache     || true
php artisan event:cache     || true
php artisan view:cache      || true

exec php-fpm
