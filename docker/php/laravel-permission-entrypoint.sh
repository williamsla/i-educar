#!/bin/sh
set -e

# Quando o serviço sobe como root (docker-compose user: root), garante pastas
# graváveis do Laravel no volume montado antes de executar como ieducar.
if [ "$(id -u)" = "0" ]; then
    mkdir -p \
        /var/www/ieducar/storage/framework/sessions \
        /var/www/ieducar/storage/framework/cache \
        /var/www/ieducar/storage/framework/cache/data \
        /var/www/ieducar/storage/framework/views \
        /var/www/ieducar/storage/logs \
        /var/www/ieducar/bootstrap/cache
    chown -R ieducar:ieducar /var/www/ieducar/storage /var/www/ieducar/bootstrap/cache
    exec su-exec ieducar "$@"
fi

exec "$@"
