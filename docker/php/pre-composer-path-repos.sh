#!/bin/sh
set -eu
cd /var/www/ieducar

# Clones minimos para repositorios "path" do composer.json existirem antes do primeiro
# `composer install` na imagem de producao (install-extra-packages corre depois).

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

# merenda/merenda-escolar esta em "require" — o path tem de existir antes do composer install.
if [ ! -f packages/merenda/merenda-escolar/composer.json ]; then
  if [ -z "${GIT_TOKEN:-}" ]; then
    echo "ERRO: GIT_TOKEN e obrigatorio no build para clonar merenda (composer.json exige packages/merenda/merenda-escolar)." >&2
    exit 1
  fi
  clone_path_repo \
    "${PACKAGE_REPO_MERENDA:-https://${GIT_TOKEN}@github.com/williamsla/merenda.git}" \
    "packages/merenda/merenda-escolar" \
    "${PACKAGE_REF_MERENDA:-}"
fi

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
