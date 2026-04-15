#!/bin/sh
set -eu

# Uso:
: <<EOF
REGISTRY_HOST=container-registry.br-ne1.magalu.cloud \
REGISTRY_NAMESPACE=ieducar \
IMAGE_TAG=2.10.0 \
ENABLE_PACKAGE_REPORTS=true \
ENABLE_PACKAGE_EDUCACENSO=true \
ENABLE_PACKAGE_TRANSPORTE=true \
ENABLE_PACKAGE_PRE_MATRICULA=true \
ENABLE_PACKAGE_DESPESAS=true \
ENABLE_PACKAGE_MERENDA=true \
GIT_TOKEN=ghp_xxx ./docker/build-push-registry.sh
EOF

: "${REGISTRY_HOST:?defina REGISTRY_HOST}"
: "${REGISTRY_NAMESPACE:?defina REGISTRY_NAMESPACE}"
: "${IMAGE_TAG:?defina IMAGE_TAG}"

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
  ${GIT_TOKEN:+--secret id=git_token,env=GIT_TOKEN} \
  -t "${APP_IMAGE}" \
  --push \
  .

echo ">> Build+push nginx via buildx: ${NGINX_IMAGE}"
docker buildx build \
  -f docker/nginx/Dockerfile.prod \
  --platform "${PLATFORM}" \
  -t "${NGINX_IMAGE}" \
  --push \
  .

echo "OK"
echo "APP_IMAGE=${APP_IMAGE}"
echo "NGINX_IMAGE=${NGINX_IMAGE}"
