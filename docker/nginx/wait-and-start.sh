#!/bin/sh
set -e
# Garante TCP em fpm:9000 antes do Nginx carregar upstream (resolve na inicialização).
# O FPM pode demorar (ex.: RUN_EXTRA_PACKAGES_INSTALL + composer/git no entrypoint).
WAIT_FPM_MAX_SEC="${WAIT_FPM_MAX_SEC:-900}"
i=0
while ! nc -z fpm 9000 2>/dev/null; do
    i=$((i + 1))
    if [ "$i" -gt "$WAIT_FPM_MAX_SEC" ]; then
        echo "wait-and-start.sh: timeout após ${WAIT_FPM_MAX_SEC}s aguardando fpm:9000 (veja logs do ieducar-fpm)" >&2
        exit 1
    fi
    if [ "$((i % 60))" -eq 0 ]; then
        echo "wait-and-start.sh: ainda aguardando fpm:9000 (${i}s / ${WAIT_FPM_MAX_SEC}s)..."
    fi
    sleep 1
done
exec /docker-entrypoint.sh "$@"
