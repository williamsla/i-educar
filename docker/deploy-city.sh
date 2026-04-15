#!/bin/sh
set -eu

# Deploy de uma cidade com deteccao de nova imagem.
# Se imagem mudou, executa passos pos-deploy automaticamente.
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
  compose exec "${php_service}" sh -lc "rm -f bootstrap/cache/*.php"
  compose exec "${php_service}" composer dump-autoload -o --no-dev --no-scripts

  compose exec "${php_service}" sh -lc '
    [ "${DB_CONNECTION:-}" = "pgsql" ] || { echo "ERRO: DB_CONNECTION=${DB_CONNECTION:-vazio} (esperado: pgsql)"; exit 1; }
    [ "${CACHE_DRIVER:-}" = "redis" ] || { echo "ERRO: CACHE_DRIVER=${CACHE_DRIVER:-vazio} (esperado: redis)"; exit 1; }
    [ "${CACHE_STORE:-}" = "redis" ] || { echo "ERRO: CACHE_STORE=${CACHE_STORE:-vazio} (esperado: redis)"; exit 1; }
  '

  compose exec "${php_service}" php artisan config:clear
  compose exec "${php_service}" php artisan cache:clear || true

  if compose exec "${php_service}" sh -lc "[ -d packages/portabilis/i-educar-reports-package ]"; then
    compose exec "${php_service}" composer plug-and-play:update --no-dev --no-scripts
    if compose exec "${php_service}" sh -lc "php artisan list --raw | awk '{print \$1}' | grep -q '^community:reports:install$'"; then
      compose exec "${php_service}" php artisan community:reports:link
      compose exec "${php_service}" php artisan community:reports:install
      compose exec "${php_service}" php artisan vendor:publish --tag=reports-assets --ansi --force
    else
      echo ">> Comandos community:reports nao encontrados. Etapa de relatorios ignorada."
    fi
  else
    echo ">> Pacote de relatorios ausente. Etapas de reports ignoradas."
  fi

  compose exec "${php_service}" php artisan storage:link || true
  compose exec "${php_service}" php artisan migrate --force
  compose exec "${php_service}" php artisan optimize:clear || true
else
  echo ">> Imagens inalteradas. Pos-deploy ignorado."
fi

echo ">> Deploy da cidade ${CITY_INDEX} concluido."
