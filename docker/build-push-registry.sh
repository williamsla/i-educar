#!/bin/sh
set -eu

# Obrigatorio: REGISTRY_HOST, REGISTRY_NAMESPACE (export ou na mesma linha).
# IMAGE_TAG opcional: se vazio, usa o sha curto (8 caracteres) do HEAD.
# Opcional: ENABLE_PACKAGE_*, PACKAGE_REF_*, GIT_TOKEN (BuildKit --secret, nao --build-arg).
# Exemplo:
#   REGISTRY_HOST=container-registry.br-ne1.magalu.cloud \
#   REGISTRY_NAMESPACE=ieducar \
#   ./docker/build-push-registry.sh
# Com token para repos privados (BuildKit secret — nao use --build-arg com o token):
#   GIT_TOKEN=ghp_xxx REGISTRY_HOST=... REGISTRY_NAMESPACE=... ./docker/build-push-registry.sh
# Ou ficheiro no host (util em CI):
#   GIT_TOKEN_FILE=/caminho/token.txt REGISTRY_HOST=... ... ./docker/build-push-registry.sh
# Se usar sudo: sudo -E env GIT_TOKEN=... ./docker/build-push-registry.sh  (senao o token nao chega ao docker)

if [ -z "${REGISTRY_HOST:-}" ] || [ -z "${REGISTRY_NAMESPACE:-}" ]; then
  echo "ERRO: defina REGISTRY_HOST e REGISTRY_NAMESPACE no ambiente." >&2
  echo "Exemplo:" >&2
  echo "  REGISTRY_HOST=container-registry.br-ne1.magalu.cloud REGISTRY_NAMESPACE=ieducar $0" >&2
  exit 1
fi

if [ -z "${IMAGE_TAG:-}" ]; then
  IMAGE_TAG=$(git rev-parse HEAD 2>/dev/null | cut -c1-8 || true)
  if [ -z "${IMAGE_TAG}" ]; then
    echo "ERRO: IMAGE_TAG vazia e nao foi possivel ler o sha do HEAD." >&2
    exit 1
  fi
  echo ">> IMAGE_TAG gerada: ${IMAGE_TAG}"
fi

APP_IMAGE="${REGISTRY_HOST}/${REGISTRY_NAMESPACE}/ieducar-app:${IMAGE_TAG}"
NGINX_IMAGE="${REGISTRY_HOST}/${REGISTRY_NAMESPACE}/ieducar-nginx:${IMAGE_TAG}"
PLATFORM="${PLATFORM:-linux/amd64}"

if ! docker buildx version >/dev/null 2>&1; then
  echo "ERRO: docker buildx nao encontrado. Instale/ative o buildx." >&2
  exit 1
fi

if ! docker buildx inspect >/dev/null 2>&1; then
  docker buildx create --use --name ieducar-builder >/dev/null
fi

# Secret BuildKit id=git_token (ver docker/php/Dockerfile.prod). env=GIT_TOKEN le a variavel
# no processo que invoca o docker; src= ficheiro no host (CI recomendado).
GIT_SECRET_ARGS=""
if [ -n "${GIT_TOKEN_FILE:-}" ]; then
  if [ ! -f "${GIT_TOKEN_FILE}" ]; then
    echo "ERRO: GIT_TOKEN_FILE=${GIT_TOKEN_FILE} nao existe ou nao e ficheiro." >&2
    exit 1
  fi
  GIT_SECRET_ARGS="--secret id=git_token,src=${GIT_TOKEN_FILE}"
  echo ">> Secret git_token: a partir de GIT_TOKEN_FILE (${GIT_TOKEN_FILE})"
elif [ -n "${GIT_TOKEN:-}" ]; then
  GIT_SECRET_ARGS="--secret id=git_token,env=GIT_TOKEN"
  echo ">> Secret git_token: a partir da variavel de ambiente GIT_TOKEN (${#GIT_TOKEN} caracteres)"
else
  echo ">> Secret git_token: nao montado (GIT_TOKEN e GIT_TOKEN_FILE vazios)."
fi

needs_git_token="false"
if [ "${ENABLE_PACKAGE_DESPESAS:-false}" = "true" ] || [ "${ENABLE_PACKAGE_MERENDA:-false}" = "true" ]; then
  needs_git_token="true"
fi
if [ "${needs_git_token}" = "true" ] && [ -z "${GIT_SECRET_ARGS}" ]; then
  echo "ERRO: ENABLE_PACKAGE_MERENDA ou ENABLE_PACKAGE_DESPESAS exige GIT_TOKEN ou GIT_TOKEN_FILE (clone privado antes do composer install)." >&2
  echo "      Com sudo use: sudo -E env GIT_TOKEN=... $0 ..." >&2
  exit 1
fi

echo ">> Build+push app via buildx: ${APP_IMAGE}"
docker buildx build \
  -f docker/php/Dockerfile.prod \
  --platform "${PLATFORM}" \
  --build-arg ENABLE_PACKAGE_REPORTS="${ENABLE_PACKAGE_REPORTS:-false}" \
  --build-arg ENABLE_PACKAGE_EDUCACENSO="${ENABLE_PACKAGE_EDUCACENSO:-false}" \
  --build-arg ENABLE_PACKAGE_TRANSPORTE="${ENABLE_PACKAGE_TRANSPORTE:-false}" \
  --build-arg ENABLE_PACKAGE_PRE_MATRICULA="${ENABLE_PACKAGE_PRE_MATRICULA:-false}" \
  --build-arg ENABLE_PACKAGE_DESPESAS="${ENABLE_PACKAGE_DESPESAS:-false}" \
  --build-arg ENABLE_PACKAGE_MERENDA="${ENABLE_PACKAGE_MERENDA:-false}" \
  --build-arg PACKAGE_REF_REPORTS="${PACKAGE_REF_REPORTS:-}" \
  --build-arg PACKAGE_REF_EDUCACENSO="${PACKAGE_REF_EDUCACENSO:-}" \
  --build-arg PACKAGE_REF_TRANSPORTE="${PACKAGE_REF_TRANSPORTE:-}" \
  --build-arg PACKAGE_REF_PRE_MATRICULA="${PACKAGE_REF_PRE_MATRICULA:-}" \
  --build-arg PACKAGE_REF_DESPESAS="${PACKAGE_REF_DESPESAS:-}" \
  --build-arg PACKAGE_REF_MERENDA="${PACKAGE_REF_MERENDA:-}" \
  --build-arg MERENDA_PACKAGE_VERSION="${MERENDA_PACKAGE_VERSION:-2.11.0}" \
  --build-arg PACKAGE_REPO_MERENDA="${PACKAGE_REPO_MERENDA:-}" \
  --build-arg PACKAGE_REPO_DESPESAS="${PACKAGE_REPO_DESPESAS:-}" \
  ${GIT_SECRET_ARGS} \
  -t "${APP_IMAGE}" \
  --push \
  .

echo ">> Build+push nginx via buildx: ${NGINX_IMAGE}"
docker buildx build \
  -f docker/nginx/Dockerfile.prod \
  --platform "${PLATFORM}" \
  --build-arg APP_IMAGE="${APP_IMAGE}" \
  -t "${NGINX_IMAGE}" \
  --push \
  .

echo "OK"
echo "APP_IMAGE=${APP_IMAGE}"
echo "NGINX_IMAGE=${NGINX_IMAGE}"
