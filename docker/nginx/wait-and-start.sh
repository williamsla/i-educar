#!/bin/sh
set -e

# Links relativos dos assets legacy (host + container no mesmo bind-mount).
if [ -d /var/www/ieducar/public ]; then
    mkdir -p /var/www/ieducar/public/intranet
    ln -sfn ../storage/app/public /var/www/ieducar/public/storage
    ln -sfn ../ieducar/modules /var/www/ieducar/public/modules
    ln -sfn ../../ieducar/intranet/fonts /var/www/ieducar/public/intranet/fonts
    ln -sfn ../../ieducar/intranet/imagens /var/www/ieducar/public/intranet/imagens
    ln -sfn ../../ieducar/intranet/scripts /var/www/ieducar/public/intranet/scripts
    ln -sfn ../../ieducar/intranet/static /var/www/ieducar/public/intranet/static
    ln -sfn ../../ieducar/intranet/styles /var/www/ieducar/public/intranet/styles
    ln -sfn ../../ieducar/intranet/tmp /var/www/ieducar/public/intranet/tmp
fi

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
