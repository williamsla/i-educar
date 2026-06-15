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

  # rm -rf no próprio path falha com "Resource busy" quando target_dir é bind mount
  # (ex.: ../i-educar-reports-package no docker-compose).
  if ! rm -rf "$target_dir" 2>/dev/null; then
    if [ ! -d "$target_dir" ]; then
      echo "ERRO: não foi possível preparar '$target_dir'." >&2
      exit 1
    fi
    # Já há pacote no mount: não apagar conteúdo do host; só alinhar ref opcional.
    if [ -f "$target_dir/composer.json" ]; then
      echo ">> '$target_dir' não removível (típico: bind mount); a reutilizar árvore existente."
      if [ -n "$repo_ref" ] && [ -d "$target_dir/.git" ]; then
        git -C "$target_dir" fetch origin 2>/dev/null || true
        git -C "$target_dir" checkout "$repo_ref" 2>/dev/null || true
      fi
      return 0
    fi
    echo ">> '$target_dir' não removível; a limpar conteúdo para git clone."
    find "$target_dir" -mindepth 1 -maxdepth 1 -exec rm -rf {} +
  fi

  mkdir -p "$target_dir"
  git clone "$repo_url" "$target_dir"

  if [ -n "$repo_ref" ]; then
    git -C "$target_dir" checkout "$repo_ref"
  fi

  # Evita erros de "dubious ownership" em runtime (composer/git)
  rm -rf "$target_dir/.git"
}

# Git 2.35+: Composer/plugins invocam git no bind mount com dono do host vs UID do contentor.
ensure_git_safe_directories() {
  if ! git config --global --get-all safe.directory 2>/dev/null | grep -Fxq '/var/www/ieducar'; then
    git config --global --add safe.directory /var/www/ieducar 2>/dev/null || true
  fi
  if ! git config --global --get-all safe.directory 2>/dev/null | grep -Fxq '*'; then
    git config --global --add safe.directory '*' 2>/dev/null || true
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

# Passos README após plug-and-play. O install de relatórios usa BD; em build sem DB falha
# (esperado). No arranque do FPM com Postgres, costuma correr; se falhar, migrate + comando manual.
run_readme_artisan_after_composer() {
  apply_artisan_build_env

  if [ "${ENABLE_PACKAGE_REPORTS:-false}" = "true" ] && [ -d packages/portabilis/i-educar-reports-package ]; then
    echo ">> README relatórios: community:reports:link, community:reports:install, publish de assets"
    if artisan_cmd_exists community:reports:link; then
      php artisan community:reports:link --no-interaction || true
    fi
    if artisan_cmd_exists community:reports:install; then
      php artisan community:reports:install --no-interaction || \
        echo ">> AVISO: community:reports:install falhou (BD/migrate pendente?). Execute após migrate: php artisan community:reports:install" >&2
    fi
    php artisan vendor:publish --tag=reports-assets --ansi --force --no-interaction 2>/dev/null || true
  fi

  if [ "${ENABLE_PACKAGE_PRE_MATRICULA:-false}" = "true" ] && [ -d packages/portabilis/pre-matricula-digital ]; then
    echo ">> README pre-matricula-digital: vendor:publish --tag=pmd"
    php artisan vendor:publish --tag=pmd --force --no-interaction 2>/dev/null || true
  fi
}

ensure_laravel_runtime_dirs

ensure_git_safe_directories

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
    "${PACKAGE_REPO_EDUCACENSO:-https://github.com/williamsla/i-educar-educacenso-package.git}" \
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
  mkdir -p packages/merenda

  require_git_token
  clone_or_update_repo \
    "${PACKAGE_REPO_MERENDA:-https://${GIT_TOKEN}@github.com/williamsla/merenda.git}" \
    "packages/merenda/merenda-escolar" \
    "${PACKAGE_REF_MERENDA:-}"

  if [ -f packages/merenda/merenda-escolar/composer.json ]; then
    merenda_ver="${MERENDA_PACKAGE_VERSION:-2.11.0}"
    composer config --json repositories.merenda \
      '{"type":"path","url":"packages/merenda/merenda-escolar","options":{"symlink":true}}' \
      --no-interaction 2>/dev/null || true
    if ! composer show merenda/merenda-escolar >/dev/null 2>&1; then
      composer require "merenda/merenda-escolar:${merenda_ver}" --no-update --no-interaction || true
    fi
  fi

  merenda_install="${MERENDA_INSTALL_SCRIPT:-}"
  if [ -z "$merenda_install" ] || [ ! -f "$merenda_install" ]; then
    merenda_install=$(find packages/merenda/merenda-escolar -type f -name instalar_modulo.sh 2>/dev/null | head -n 1 || true)
  fi
  # Caminhos legados (estrutura antiga do repo).
  if [ -z "$merenda_install" ] || [ ! -f "$merenda_install" ]; then
    for candidate in \
      "packages/merenda/merenda-escolar/instalar_modulo.sh" \
      "packages/merenda/instalar_modulo.sh"
    do
      if [ -f "$candidate" ]; then
        merenda_install="$candidate"
        break
      fi
    done
  fi

  if [ -n "$merenda_install" ] && [ -f "$merenda_install" ]; then
    strip_sudo_from_script "$merenda_install"
    ensure_laravel_runtime_dirs
    chmod +x "$merenda_install"
    sh "$merenda_install"
  else
    echo "AVISO: instalar_modulo.sh nao encontrado em packages/merenda." >&2
    echo "AVISO: Defina MERENDA_INSTALL_SCRIPT com o caminho completo no contentor (ex.: packages/merenda/subpasta/instalar_modulo.sh)." >&2
  fi

  installed_any_package=1
fi

if [ "$installed_any_package" = "1" ]; then
  ensure_laravel_runtime_dirs

  ensure_git_safe_directories
  composer plug-and-play:update

  apply_artisan_build_env
  run_readme_artisan_after_composer
fi

# Educacenso / Transporte (README Portabilis): migrate e cache apos plug-and-play.
# migrate e cache em runtime: ver docker/deploy-city.sh (multicidade) ou processo de release.
if [ "${ENABLE_PACKAGE_EDUCACENSO:-false}" = "true" ] || [ "${ENABLE_PACKAGE_TRANSPORTE:-false}" = "true" ]; then
  echo ">> Lembrete README Educacenso/Transporte: apos deploy com DB, migrate (deploy-city) e, se atualizacao, cache:clear / optimize:clear."
fi
