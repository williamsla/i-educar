#!/bin/sh
set -e
# Garante TCP em fpm:9000 antes do Nginx carregar upstream (resolve na inicialização).
i=0
while ! nc -z fpm 9000 2>/dev/null; do
    i=$((i + 1))
    if [ "$i" -gt 120 ]; then
        echo "wait-and-start.sh: timeout aguardando fpm:9000" >&2
        exit 1
    fi
    sleep 1
done
exec /docker-entrypoint.sh "$@"
