#!/bin/sh
set -eu

# Deploy de uma cidade com deteccao de nova imagem.
# Se imagem mudou, executa pos-deploy automaticamente (fluxo imutavel: sem composer
# em runtime; vendor/plug-and-play ficam na imagem via Dockerfile.prod + build).
#
# Relatorios (README i-educar-reports-package): apos migrate, confirma install e
# assets no container (com DB); a build da imagem ja tenta publish sem DB.
#
# Exemplo:
# CITY_INDEX=1 \
# ENV_FILE=docker/.env.registry \
# COMPOSE_FILE=docker-compose.multicidade.registry.yml \
# ./docker/deploy-city.sh

CITY_INDEX="${CITY_INDEX:-1}"
ENV_FILE="${ENV_FILE:-docker/.env.registry}"
COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.multicidade.registry.yml}"
FORCE_POST_DEPLOY="${FORCE_POST_DEPLOY:-false}"

php_service="php_cidade${CITY_INDEX}"
fpm_service="fpm_cidade${CITY_INDEX}"
nginx_service="nginx_cidade${CITY_INDEX}"
redis_service="redis_cidade${CITY_INDEX}"

compose() {
  docker compose --env-file "${ENV_FILE}" -f "${COMPOSE_FILE}" "$@"
}

container_id() {
  compose ps -q "$1" 2>/dev/null || true
}

image_id_from_container() {
  cid="$1"
  if [ -z "${cid}" ]; then
    echo ""
    return 0
  fi
  docker inspect --format '{{.Image}}' "${cid}" 2>/dev/null || true
}

old_php_cid="$(container_id "${php_service}")"
old_fpm_cid="$(container_id "${fpm_service}")"
old_nginx_cid="$(container_id "${nginx_service}")"

old_php_image="$(image_id_from_container "${old_php_cid}")"
old_fpm_image="$(image_id_from_container "${old_fpm_cid}")"
old_nginx_image="$(image_id_from_container "${old_nginx_cid}")"

echo ">> Pull de imagens (${php_service}, ${fpm_service}, ${nginx_service})"
compose pull "${php_service}" "${fpm_service}" "${nginx_service}"

echo ">> Subindo/recriando serviços da cidade ${CITY_INDEX}"
compose up -d --force-recreate "${redis_service}" "${php_service}" "${fpm_service}" "${nginx_service}"

new_php_cid="$(container_id "${php_service}")"
new_fpm_cid="$(container_id "${fpm_service}")"
new_nginx_cid="$(container_id "${nginx_service}")"

new_php_image="$(image_id_from_container "${new_php_cid}")"
new_fpm_image="$(image_id_from_container "${new_fpm_cid}")"
new_nginx_image="$(image_id_from_container "${new_nginx_cid}")"

images_changed="false"
if [ "${old_php_image}" != "${new_php_image}" ] || \
   [ "${old_fpm_image}" != "${new_fpm_image}" ] || \
   [ "${old_nginx_image}" != "${new_nginx_image}" ] || \
   [ -z "${old_php_cid}" ]; then
  images_changed="true"
fi

if [ "${FORCE_POST_DEPLOY}" = "true" ]; then
  images_changed="true"
fi

if [ "${images_changed}" = "true" ]; then
  echo ">> Nova imagem detectada. Executando pos-deploy da cidade ${CITY_INDEX}"

  compose exec -T "${php_service}" sh -lc '
    [ "${DB_CONNECTION:-}" = "pgsql" ] || { echo "ERRO: DB_CONNECTION=${DB_CONNECTION:-vazio} (esperado: pgsql)"; exit 1; }
    [ "${CACHE_DRIVER:-}" = "redis" ] || { echo "ERRO: CACHE_DRIVER=${CACHE_DRIVER:-vazio} (esperado: redis)"; exit 1; }
    [ "${CACHE_STORE:-}" = "redis" ] || { echo "ERRO: CACHE_STORE=${CACHE_STORE:-vazio} (esperado: redis)"; exit 1; }
  '

  compose exec -T "${php_service}" sh -lc "rm -f bootstrap/cache/*.php"

  compose exec -T "${php_service}" php artisan storage:link || true
  compose exec -T "${php_service}" php artisan migrate --force

  # Imagem registry pode nao ter packages/portabilis/... (build sem ENABLE_PACKAGE_REPORTS).
  # O gatilho correto e' o comando Artisan registado (plug-and-play / composer).
  if compose exec -T "${php_service}" sh -lc "php artisan list --raw 2>/dev/null | awk '{print \$1}' | grep -qx community:reports:install"; then
    echo ">> Relatorios: link/install/publish (pacote presente na app)"
    if compose exec -T "${php_service}" sh -lc "php artisan list --raw 2>/dev/null | awk '{print \$1}' | grep -qx community:reports:link"; then
      compose exec -T "${php_service}" php artisan community:reports:link --no-interaction || true
    fi

    compose exec -T "${fpm_service}" php artisan community:reports:install --no-interaction || true
    # publish grava em disco da app; php_* e fpm_* nao partilham camada gravada (registry).
    compose exec -T "${php_service}" php artisan vendor:publish --tag=reports-assets --ansi --force --no-interaction || true
    compose exec -T "${fpm_service}" php artisan vendor:publish --tag=reports-assets --ansi --force --no-interaction || true
    # Jasper grava/compila ficheiros em ReportSources; e os containers (php/fpm) podem
    # nao ter permissao suficiente se a instalacao correu como root.
    for svc in "${php_service}" "${fpm_service}"; do
      compose exec -T "${svc}" sh -lc '
        for d in \
          ieducar/modules/Reports \
          ieducar/modules/Reports/ReportSources \
          packages/portabilis/i-educar-reports-package/ieducar/modules/Reports \
          packages/portabilis/i-educar-reports-package/ieducar/ReportSources
        do
          [ -e "$d" ] || continue
          chown -R www-data:www-data "$d" 2>/dev/null || true
          chmod -R ug+rwX "$d" 2>/dev/null || true
        done
      ' || true
    done
  else
    echo ">> Relatorios: ignorados (comando community:reports:install inexistente). Para incluir na imagem: build com ENABLE_PACKAGE_REPORTS=true (ver docker/php/Dockerfile.prod)."
  fi

  compose exec -T "${php_service}" php artisan optimize:clear || true
else
  echo ">> Imagens inalteradas. Pos-deploy ignorado."
fi

echo ">> Deploy da cidade ${CITY_INDEX} concluido."
