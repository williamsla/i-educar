#!/bin/sh
set -eu

cd /var/www/ieducar

ensure_laravel_runtime_dirs() {
  mkdir -p \
    storage/logs \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    bootstrap/cache
}

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

strip_sudo_from_script() {
  script_path="$1"
  if [ -f "$script_path" ]; then
    sed -i 's/^[[:space:]]*sudo[[:space:]]\+//g' "$script_path"
  fi
}

run_if_exists() {
  script_path="$1"
  if [ -f "$script_path" ]; then
    chmod +x "$script_path"
    sh "$script_path"
    return 0
  fi

  return 1
}

require_git_token() {
  if [ -z "${GIT_TOKEN:-}" ]; then
    echo "ERRO: GIT_TOKEN nao definido para repositorio privado." >&2
    exit 1
  fi
}

ensure_laravel_runtime_dirs

installed_any_package=0

if [ "${ENABLE_PACKAGE_REPORTS:-false}" = "true" ]; then
  clone_or_update_repo \
    "${PACKAGE_REPO_REPORTS:-https://github.com/portabilis/i-educar-reports-package.git}" \
    "packages/portabilis/i-educar-reports-package" \
    "${PACKAGE_REF_REPORTS:-}"

  # Ajustes especificos para SEMED-AL (quando token existir).
  if [ -n "${GIT_TOKEN:-}" ]; then
    rm -rf cdn-config
    git clone "https://${GIT_TOKEN}@github.com/semed-al/cdn-config.git"

    cp -r cdn-config/i-educar-reports-package/ieducar/* \
      packages/portabilis/i-educar-reports-package/ieducar/
    cp -r cdn-config/i-educar-reports-package/database/* \
      packages/portabilis/i-educar-reports-package/database/
    cp -r cdn-config/i-educar/ieducar/* ./ieducar/

    rm -rf cdn-config
  else
    echo "AVISO: GIT_TOKEN ausente; pulando customizacoes SEMED-AL para reports."
  fi

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

  strip_sudo_from_script "packages/despesas-escolar/install.sh"
  ensure_laravel_runtime_dirs
  run_if_exists "packages/despesas-escolar/install.sh"
  installed_any_package=1
fi

if [ "${ENABLE_PACKAGE_MERENDA:-false}" = "true" ]; then
  require_git_token
  clone_or_update_repo \
    "${PACKAGE_REPO_MERENDA:-https://${GIT_TOKEN}@github.com/williamsla/merenda.git}" \
    "packages/merenda" \
    "${PACKAGE_REF_MERENDA:-}"

  strip_sudo_from_script "packages/merenda/merenda-escolar/instalar_modulo.sh"
  strip_sudo_from_script "packages/merenda/install.sh"
  ensure_laravel_runtime_dirs
  if ! run_if_exists "packages/merenda/merenda-escolar/instalar_modulo.sh"; then
    if ! run_if_exists "packages/merenda/install.sh"; then
      echo "AVISO: script de instalacao da merenda nao encontrado no repositorio." >&2
    fi
  fi
  installed_any_package=1
fi

if [ "$installed_any_package" = "1" ]; then
  ensure_laravel_runtime_dirs
  composer plug-and-play:update --no-dev --no-scripts
  composer dump-autoload -o --no-dev --no-scripts
fi
