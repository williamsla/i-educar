#!/usr/bin/env bash
set -euo pipefail

# Uso:
#   script/migracao_sislami_completa.sh [USER_ID_CAD] [ANO_LETIVO] [DB_HOST] [DB_PORT] [DB_USER] [DB_PASSWORD] [DB_NAME]
# Exemplo:
#   script/migracao_sislami_completa.sh 1 2026 127.0.0.1 2345 ieducar gF2ei9Aui4jxaf ieducar_sislami

USER_ID_CAD="${1:-1}"
ANO_LETIVO="${2:-2026}"
DB_HOST="${3:-127.0.0.1}"
DB_PORT="${4:-2345}"
DB_USER="${5:-ieducar}"
DB_PASSWORD="${6:-gF2ei9Aui4jxaf}"
DB_NAME="${7:-ieducar_sislami}"
DB_ADMIN_DB="${8:-postgres}"

export PGPASSWORD="${DB_PASSWORD}"

echo "[INFO] Destino PostgreSQL: postgresql://${DB_USER}@${DB_HOST}:${DB_PORT}/${DB_NAME}"
echo "[INFO] Valide no MESMO banco: psql -h ${DB_HOST} -p ${DB_PORT} -U ${DB_USER} -d ${DB_NAME} -f script/validar_migracao_sislami.sql"

echo "[0/3] Garantindo que o banco ${DB_NAME} exista..."
DB_EXISTS="$(
  psql \
    -h "${DB_HOST}" \
    -p "${DB_PORT}" \
    -U "${DB_USER}" \
    -d "${DB_ADMIN_DB}" \
    -tAc "SELECT 1 FROM pg_database WHERE datname = '${DB_NAME}'"
)"

if [[ "${DB_EXISTS}" != "1" ]]; then
  createdb \
    -h "${DB_HOST}" \
    -p "${DB_PORT}" \
    -U "${DB_USER}" \
    "${DB_NAME}"
  echo "Banco ${DB_NAME} criado."
else
  echo "Banco ${DB_NAME} ja existe."
fi

echo "[1/3] Importando CSVs SISLAMI e criando schema de migracao..."
python3 script/import_sislami.py \
  --truncate \
  --db-host "${DB_HOST}" \
  --db-port "${DB_PORT}" \
  --db-user "${DB_USER}" \
  --db-password "${DB_PASSWORD}" \
  --db-name "${DB_NAME}"

echo "[2/3] Cargando dados no modelo i-Educar (pmieducar/cadastro/modules)..."
psql \
  -h "${DB_HOST}" \
  -p "${DB_PORT}" \
  -U "${DB_USER}" \
  -d "${DB_NAME}" \
  -v ON_ERROR_STOP=1 \
  -v user_id_cad="${USER_ID_CAD}" \
  -v ano_letivo="${ANO_LETIVO}" \
  -f script/migrar_sislami_para_pmieducar.sql

echo "[3/3] Finalizado."

echo "Concluido."
