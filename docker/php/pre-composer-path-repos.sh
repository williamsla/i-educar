#!/bin/sh
set -eu
cd /var/www/ieducar

# Clones minimos para repositorios "path" existirem antes do primeiro `composer install`
# na imagem de producao. Merenda e despesas sao opcionais (ENABLE_PACKAGE_*).

clone_path_repo() {
  repo_url="$1"
  target_dir="$2"
  repo_ref="${3:-}"

  parent=$(dirname "$target_dir")
  mkdir -p "$parent"
  rm -rf "$target_dir"
  git clone "$repo_url" "$target_dir"

  if [ -n "$repo_ref" ]; then
    git -C "$target_dir" checkout "$repo_ref"
  fi

  rm -rf "$target_dir/.git"
}

register_merenda_with_composer() {
  merenda_ver="${MERENDA_PACKAGE_VERSION:-2.11.0}"
  composer config --json repositories.merenda \
    '{"type":"path","url":"packages/merenda/merenda-escolar","options":{"symlink":true}}' \
    --no-interaction
  composer require "merenda/merenda-escolar:${merenda_ver}" --no-install --no-interaction
}

# Clonar todos os path repos habilitados antes de qualquer comando composer.
# O Composer valida todos os repositories.path do composer.json em cada operacao.
if [ "${ENABLE_PACKAGE_DESPESAS:-false}" = "true" ]; then
  if [ ! -f packages/despesas-escolar/composer.json ]; then
    if [ -z "${GIT_TOKEN:-}" ]; then
      echo "ERRO: GIT_TOKEN e obrigatorio para clonar despesas-escolar (ENABLE_PACKAGE_DESPESAS=true)." >&2
      exit 1
    fi
    clone_path_repo \
      "${PACKAGE_REPO_DESPESAS:-https://${GIT_TOKEN}@github.com/williamsla/despesas-escolar.git}" \
      "packages/despesas-escolar" \
      "${PACKAGE_REF_DESPESAS:-}"
  fi
fi

if [ "${ENABLE_PACKAGE_MERENDA:-false}" = "true" ]; then
  if [ ! -f packages/merenda/merenda-escolar/composer.json ]; then
    if [ -z "${GIT_TOKEN:-}" ]; then
      echo "ERRO: GIT_TOKEN e obrigatorio para clonar merenda (ENABLE_PACKAGE_MERENDA=true)." >&2
      exit 1
    fi
    clone_path_repo \
      "${PACKAGE_REPO_MERENDA:-https://${GIT_TOKEN}@github.com/williamsla/merenda.git}" \
      "packages/merenda/merenda-escolar" \
      "${PACKAGE_REF_MERENDA:-}"
  fi
fi

# Remove path repos desabilitados/ausentes antes de `composer require` (ex.: despesas com merenda ativo).
ENABLE_PACKAGE_MERENDA="${ENABLE_PACKAGE_MERENDA:-false}" \
ENABLE_PACKAGE_DESPESAS="${ENABLE_PACKAGE_DESPESAS:-false}" \
php docker/php/drop-composer-lock-if-merenda-path-missing.php

if [ "${ENABLE_PACKAGE_MERENDA:-false}" = "true" ]; then
  register_merenda_with_composer
fi
