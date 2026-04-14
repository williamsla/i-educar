#!/bin/sh
set -e

cd /var/www/ieducar

# Garante diretorios de escrita do Laravel.
mkdir -p storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache || true
chmod -R ug+rwX storage bootstrap/cache || true

exec "$@"
