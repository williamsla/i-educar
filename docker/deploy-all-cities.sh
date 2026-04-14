#!/bin/sh
set -eu

# Deploy em lote para todas as cidades do compose (ou lista informada).
#
# Exemplos:
# ENV_FILE=docker/.env.registry COMPOSE_FILE=docker-compose.multicidade.registry.yml ./docker/deploy-all-cities.sh
#
# CITY_INDEXES=1,2,3 ENV_FILE=docker/.env.registry COMPOSE_FILE=docker-compose.multicidade.registry.yml ./docker/deploy-all-cities.sh

ENV_FILE="${ENV_FILE:-docker/.env.registry}"
COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.multicidade.registry.yml}"
CITY_INDEXES="${CITY_INDEXES:-}"
FORCE_POST_DEPLOY="${FORCE_POST_DEPLOY:-false}"

script_dir="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"

discover_city_indexes() {
  docker compose --env-file "${ENV_FILE}" -f "${COMPOSE_FILE}" config --services \
    | awk '
      /^php_cidade[0-9]+$/ {
        sub(/^php_cidade/, "", $0);
        print $0;
      }
    ' \
    | sort -n \
    | uniq
}

normalize_indexes() {
  printf '%s\n' "$1" | tr ',' ' ' | tr -s ' '
}

if [ -z "${CITY_INDEXES}" ]; then
  CITY_INDEXES="$(discover_city_indexes | tr '\n' ' ' | sed 's/[[:space:]]*$//')"
fi

if [ -z "${CITY_INDEXES}" ]; then
  echo "ERRO: nao foi possivel identificar cidades no compose."
  exit 1
fi

echo ">> Cidades alvo: ${CITY_INDEXES}"

for idx in $(normalize_indexes "${CITY_INDEXES}"); do
  echo "============================================================"
  echo ">> Deploy da cidade index ${idx}"
  CITY_INDEX="${idx}" \
  ENV_FILE="${ENV_FILE}" \
  COMPOSE_FILE="${COMPOSE_FILE}" \
  FORCE_POST_DEPLOY="${FORCE_POST_DEPLOY}" \
  "${script_dir}/deploy-city.sh"
done

echo ">> Deploy em lote concluido."
