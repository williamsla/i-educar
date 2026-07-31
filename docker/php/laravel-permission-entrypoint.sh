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
        # Default 10 min (antes 45): waiters têm 900s; se o dono morrer (SIGKILL/OOM),
        # o órfão precisa ser limpo durante a espera. Sobrescreve no .env.
        stale_mins="${STALE_EXTRA_PACKAGES_LOCK_MINUTES:-10}"

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
            trap 'rm -rf "$LOCKDIR" 2>/dev/null || true' EXIT INT TERM
            echo ">> RUN_EXTRA_PACKAGES_INSTALL: executando docker/php/install-extra-packages.sh"
            sh /var/www/ieducar/docker/php/install-extra-packages.sh
            trap - EXIT INT TERM
            rm -rf "$LOCKDIR" 2>/dev/null || true
        }

        try_acquire_and_run() {
            remove_stale_extra_packages_lock
            if mkdir "$LOCKDIR" 2>/dev/null; then
                run_install_extra_packages
                return 0
            fi
            return 1
        }

        if ! try_acquire_and_run; then
            echo ">> Aguardando outro contentor concluir install-extra-packages..."
            i=0
            while [ "$i" -lt 900 ]; do
                sleep 1
                i=$((i + 1))
                # A cada 30s reavalia lock órfão (contentor morto a meio do install).
                if [ $((i % 30)) -eq 0 ]; then
                    remove_stale_extra_packages_lock
                fi
                if [ ! -d "$LOCKDIR" ]; then
                    break
                fi
            done
            if [ -d "$LOCKDIR" ]; then
                echo ">> Timeout (900s) aguardando install-extra-packages" >&2
                echo ">> Se não houver outro contentor a instalar, remova o lock órfão:" >&2
                echo ">>   rm -rf storage/framework/cache/.extra-packages-install-lock" >&2
                echo ">> e suba o fpm de novo (ou baixe STALE_EXTRA_PACKAGES_LOCK_MINUTES)." >&2
                exit 1
            fi
            # Lock libertado (ou órfão removido): tenta instalar neste contentor.
            if ! try_acquire_and_run; then
                echo ">> Lock ainda presente após espera; outro contentor provavelmente a instalar — a continuar sem reexecutar." >&2
            fi
        fi
    fi

    chown -R ieducar:ieducar /var/www/ieducar/storage /var/www/ieducar/bootstrap/cache
    exec su-exec ieducar "$@"
fi

exec "$@"
