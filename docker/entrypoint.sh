#!/bin/sh

set -eu

case "${APP_KEY:-}" in
    base64:*) ;;
    "") export APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')" ;;
    *) export APP_KEY="base64:${APP_KEY}" ;;
esac

if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    database_path="${DB_DATABASE:-/data/database.sqlite}"
    mkdir -p "$(dirname "$database_path")"
    touch "$database_path"
fi

php artisan config:clear
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache

if [ -n "${PORT:-}" ] && [ "$PORT" != "80" ]; then
    sed -ri "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
    sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
fi

exec "$@"
