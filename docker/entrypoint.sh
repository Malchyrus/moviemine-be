#!/bin/sh
set -e

mkdir -p storage/framework/{cache,sessions,views} storage/logs
chown -R www-data:www-data storage bootstrap/cache

php artisan migrate --database=pgsql_direct --force --no-interaction
php artisan config:cache
php artisan view:cache

exec frankenphp run --config /app/Caddyfile
