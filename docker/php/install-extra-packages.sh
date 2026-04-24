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

  # Evita erros de "dubious ownership" em runtime (composer/git)
  rm -rf "$target_dir/.git"
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

# Build da imagem nao inclui .env; varios comandos artisan exigem APP_KEY.
apply_artisan_build_env() {
  export APP_ENV="${APP_ENV:-production}"
  export CI="${CI:-true}"
  if [ -z "${APP_KEY:-}" ]; then
    export APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
  fi
}

artisan_cmd_exists() {
  name="$1"
  php artisan list --raw 2>/dev/null | awk '{print $1}' | grep -qx "$name"
}

# Passos README apos plug-and-play que nao exigem DB. `community:reports:install` fica
# apenas no deploy (docker/deploy-city.sh), apos migrate.
run_readme_artisan_after_composer() {
  apply_artisan_build_env

  if [ "${ENABLE_PACKAGE_REPORTS:-false}" = "true" ] && [ -d packages/portabilis/i-educar-reports-package ]; then
    echo ">> README relatórios (build): community:reports:link e publish de assets"
    if artisan_cmd_exists community:reports:link; then
      php artisan community:reports:link --no-interaction || true
    fi
    php artisan vendor:publish --tag=reports-assets --ansi --force --no-interaction 2>/dev/null || true
  fi

  if [ "${ENABLE_PACKAGE_PRE_MATRICULA:-false}" = "true" ] && [ -d packages/portabilis/pre-matricula-digital ]; then
    echo ">> README pre-matricula-digital: vendor:publish --tag=pmd"
    php artisan vendor:publish --tag=pmd --force --no-interaction 2>/dev/null || true
  fi
}

ensure_laravel_runtime_dirs

installed_any_package=0

if [ "${ENABLE_PACKAGE_REPORTS:-false}" = "true" ]; then
  clone_or_update_repo \
    "${PACKAGE_REPO_REPORTS:-https://github.com/williamsla/i-educar-reports-package.git}" \
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

    cp -r cdn-config/images/brasao/* packages/portabilis/i-educar-reports-package/ieducar/ReportLogos/
    chmod -R 755 packages/portabilis/i-educar-reports-package/ieducar/ReportLogos/
    
    rm -rf cdn-config
  else
    echo "AVISO: GIT_TOKEN ausente; pulando customizacoes SEMED-AL para reports."
  fi

  # git clone no build fica como root; Jasper grava .jasper ao compilar em ReportSources.
  if [ -d "packages/portabilis/i-educar-reports-package/ieducar/modules/Reports" ]; then
    chown -R www-data:www-data packages/portabilis/i-educar-reports-package/ieducar/modules/Reports
    chmod -R ug+rwX packages/portabilis/i-educar-reports-package/ieducar/modules/Reports
  fi

  # Ficheiros compilados (.jasper) e saídas (.pdf) ficam em ReportSources.
  if [ -d "packages/portabilis/i-educar-reports-package/ieducar/ReportSources" ]; then
    chown -R www-data:www-data packages/portabilis/i-educar-reports-package/ieducar/ReportSources
    chmod -R ug+rwX packages/portabilis/i-educar-reports-package/ieducar/ReportSources
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

  strip_sudo_from_script "packages/merenda/instalar_modulo.sh"
  ensure_laravel_runtime_dirs

  chmod +x packages/merenda/instalar_modulo.sh    
  run_if_exists "packages/merenda/instalar_modulo.sh"

  installed_any_package=1
fi

if [ "$installed_any_package" = "1" ]; then
  ensure_laravel_runtime_dirs
  
  composer plug-and-play:update 

  apply_artisan_build_env
  run_readme_artisan_after_composer
fi

# Educacenso / Transporte (README Portabilis): migrate e cache apos plug-and-play.
# migrate e cache em runtime: ver docker/deploy-city.sh (multicidade) ou processo de release.
if [ "${ENABLE_PACKAGE_EDUCACENSO:-false}" = "true" ] || [ "${ENABLE_PACKAGE_TRANSPORTE:-false}" = "true" ]; then
  echo ">> Lembrete README Educacenso/Transporte: apos deploy com DB, migrate (deploy-city) e, se atualizacao, cache:clear / optimize:clear."
fi
