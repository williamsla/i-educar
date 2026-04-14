#!/bin/sh
set -eu

cd /var/www/ieducar

clone_or_update_repo() {
  repo_url="$1"
  target_dir="$2"
  repo_ref="${3:-}"

  rm -rf "$target_dir"
  git clone "$repo_url" "$target_dir"

  if [ -n "$repo_ref" ]; then
    git -C "$target_dir" checkout "$repo_ref"
  fi
}

require_git_token() {
  if [ -z "${GIT_TOKEN:-}" ]; then
    echo "ERRO: GIT_TOKEN nao definido para repositorio privado." >&2
    exit 1
  fi
}

installed_any_package=0

if [ "${ENABLE_PACKAGE_REPORTS:-false}" = "true" ]; then
  clone_or_update_repo \
    "${PACKAGE_REPO_REPORTS:-https://github.com/portabilis/i-educar-reports-package.git}" \
    "packages/portabilis/i-educar-reports-package" \
    "${PACKAGE_REF_REPORTS:-}"
  installed_any_package=1
fi

if [ "${ENABLE_PACKAGE_EDUCACENSO:-false}" = "true" ]; then
  clone_or_update_repo \
    "${PACKAGE_REPO_EDUCACENSO:-https://github.com/portabilis/i-educar-educacenso-package.git}" \
    "packages/portabilis/i-educar-educacenso-package" \
    "${PACKAGE_REF_EDUCACENSO:-}"
  installed_any_package=1
fi

if [ "${ENABLE_PACKAGE_TRANSPORTE:-false}" = "true" ]; then
  clone_or_update_repo \
    "${PACKAGE_REPO_TRANSPORTE:-https://github.com/portabilis/i-educar-transport-package.git}" \
    "packages/portabilis/i-educar-transport-package" \
    "${PACKAGE_REF_TRANSPORTE:-}"
  installed_any_package=1
fi

if [ "${ENABLE_PACKAGE_PRE_MATRICULA:-false}" = "true" ]; then
  clone_or_update_repo \
    "${PACKAGE_REPO_PRE_MATRICULA:-https://github.com/williamsla/pre-matricula-digital.git}" \
    "packages/portabilis/pre-matricula-digital" \
    "${PACKAGE_REF_PRE_MATRICULA:-}"

  if [ -f "packages/portabilis/pre-matricula-digital/.env.example" ]; then
    cp "packages/portabilis/pre-matricula-digital/.env.example" \
      "packages/portabilis/pre-matricula-digital/.env"
  fi

  yarn --cwd packages/portabilis/pre-matricula-digital install
  yarn --cwd packages/portabilis/pre-matricula-digital build --base=/vendor/pre-matricula-digital/
  installed_any_package=1
fi

if [ "${ENABLE_PACKAGE_DESPESAS:-false}" = "true" ]; then
  require_git_token
  clone_or_update_repo \
    "${PACKAGE_REPO_DESPESAS:-https://${GIT_TOKEN}@github.com/williamsla/despesas-escolar.git}" \
    "packages/despesas-escolar" \
    "${PACKAGE_REF_DESPESAS:-}"

  chmod +x packages/despesas-escolar/install.sh
  sh packages/despesas-escolar/install.sh
  installed_any_package=1
fi

if [ "${ENABLE_PACKAGE_MERENDA:-false}" = "true" ]; then
  require_git_token
  clone_or_update_repo \
    "${PACKAGE_REPO_MERENDA:-https://${GIT_TOKEN}@github.com/williamsla/merenda.git}" \
    "packages/merenda" \
    "${PACKAGE_REF_MERENDA:-}"

  chmod +x packages/merenda/merenda-escolar/instalar_modulo.sh
  sh packages/merenda/merenda-escolar/instalar_modulo.sh
  installed_any_package=1
fi

if [ "$installed_any_package" = "1" ]; then
  composer plug-and-play:update
  composer dump-autoload -o
fi
