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

# Evita manifest de pacotes desatualizado (ex.: Lighthouse listado sem nuwave/lighthouse no vendor).
rm -f bootstrap/cache/*.php

chown -R www-data:www-data storage bootstrap/cache || true
chmod -R ug+rwX storage bootstrap/cache || true

exec "$@"
