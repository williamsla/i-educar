#!/bin/sh
set -e

# O install extra deve correr só no FPM: se o Horizon obtiver o lock primeiro,
# o FPM fica à espera e a porta 9000 nunca abre (Nginx fica em timeout).
cmd_includes_php_fpm() {
    for a in "$@"; do
        if [ "$a" = "php-fpm" ]; then
            return 0
        fi
    done
    return 1
}

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

    if [ "${RUN_EXTRA_PACKAGES_INSTALL:-false}" = "true" ] \
        && cmd_includes_php_fpm "$@" \
        && [ -f /var/www/ieducar/docker/php/install-extra-packages.sh ]; then
        LOCKDIR=/var/www/ieducar/storage/framework/cache/.extra-packages-install-lock
        stale_mins="${STALE_EXTRA_PACKAGES_LOCK_MINUTES:-45}"

        remove_stale_extra_packages_lock() {
            [ -d "$LOCKDIR" ] || return 0
            now=$(date +%s)
            mod_epoch=$(stat -c %Y "$LOCKDIR" 2>/dev/null) || mod_epoch=""
            if [ -n "$mod_epoch" ]; then
                age_sec=$((now - mod_epoch))
                max_sec=$((stale_mins * 60))
                if [ "$age_sec" -gt "$max_sec" ]; then
                    echo ">> Removendo lock obsoleto (>${stale_mins} min): $LOCKDIR"
                    rm -rf "$LOCKDIR"
                fi
            fi
        }

        run_install_extra_packages() {
            trap 'rmdir "$LOCKDIR" 2>/dev/null || true' EXIT INT TERM
            echo ">> RUN_EXTRA_PACKAGES_INSTALL: executando docker/php/install-extra-packages.sh"
            sh /var/www/ieducar/docker/php/install-extra-packages.sh
            trap - EXIT INT TERM
            rmdir "$LOCKDIR" 2>/dev/null || true
        }

        remove_stale_extra_packages_lock
        if mkdir "$LOCKDIR" 2>/dev/null; then
            run_install_extra_packages
        else
            remove_stale_extra_packages_lock
            if mkdir "$LOCKDIR" 2>/dev/null; then
                run_install_extra_packages
            else
                echo ">> Aguardando outro contentor concluir install-extra-packages..."
                i=0
                while [ -d "$LOCKDIR" ] && [ "$i" -lt 900 ]; do
                    sleep 1
                    i=$((i + 1))
                done
                if [ -d "$LOCKDIR" ]; then
                    echo ">> Timeout (900s) aguardando install-extra-packages" >&2
                    exit 1
                fi
            fi
        fi
    fi

    chown -R ieducar:ieducar /var/www/ieducar/storage /var/www/ieducar/bootstrap/cache
    exec su-exec ieducar "$@"
fi

exec "$@"
