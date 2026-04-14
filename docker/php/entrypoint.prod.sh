#!/bin/sh
set -e

cd /var/www/ieducar

# Garante diretorios de escrita do Laravel.
mkdir -p \
  storage/logs \
  storage/framework/cache \
  storage/framework/sessions \
  storage/framework/views \
  bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache || true
chmod -R ug+rwX storage bootstrap/cache || true

exec "$@"
