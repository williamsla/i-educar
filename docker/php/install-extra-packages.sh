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

# Bind mount (ex.: ../i-educar-reports-package no docker-compose): detectar ANTES de
# qualquer rm. `rm -rf` num mountpoint apaga o conteúdo do host (incl. .git) e só
# depois falha com "Resource busy" — a verificação antiga chegava tarde demais.
is_mountpoint() {
  dir="$1"
  [ -d "$dir" ] || return 1

  if command -v mountpoint >/dev/null 2>&1; then
    mountpoint -q "$dir" 2>/dev/null && return 0
  fi

  abs=$(cd "$dir" 2>/dev/null && pwd -P) || return 1
  awk -v p="$abs" '$5 == p { found=1; exit } END { exit !found }' /proc/self/mountinfo 2>/dev/null
}

use_local_packages() {
  [ "${USE_LOCAL_PACKAGES:-false}" = "true" ]
}

reuse_existing_package_tree() {
  target_dir="$1"
  repo_ref="${2:-}"

  if is_mountpoint "$target_dir"; then
    echo ">> '$target_dir' é bind mount; a reutilizar árvore (preserva .git)."
  elif [ -d "$target_dir/.git" ]; then
    echo ">> '$target_dir' já tem checkout git; a reutilizar árvore."
  else
    echo ">> '$target_dir' já presente (clone anterior / COPY); a reutilizar árvore."
  fi
  if [ ! -f "$target_dir/composer.json" ]; then
    echo "ERRO: '$target_dir' sem composer.json; não será limpo o bind mount do host." >&2
    exit 1
  fi

  # Ambiente local: nunca fetch/checkout remoto — usa o código já presente em ../.
  if use_local_packages || is_mountpoint "$target_dir"; then
    echo ">> USE_LOCAL_PACKAGES/mount: sem git fetch/checkout em '$target_dir'."
    return 0
  fi

  if [ -n "$repo_ref" ] && [ -d "$target_dir/.git" ]; then
    git -C "$target_dir" fetch origin 2>/dev/null || true
    git -C "$target_dir" checkout "$repo_ref" 2>/dev/null || true
  fi
}

clone_or_update_repo() {
  repo_url="$1"
  target_dir="$2"
  repo_ref="${3:-}"

  # Nunca apagar conteúdo de bind mount (preserva .git e configs do repositório no host).
  if is_mountpoint "$target_dir"; then
    reuse_existing_package_tree "$target_dir" "$repo_ref"
    return 0
  fi

  # Checkout com .git já presente (path repo / volume sem aparecer como mountpoint).
  if [ -d "$target_dir/.git" ]; then
    reuse_existing_package_tree "$target_dir" "$repo_ref"
    return 0
  fi

  # Árvore local já presente (ex.: cópia anterior) — não clonar de novo.
  if [ -f "$target_dir/composer.json" ]; then
    reuse_existing_package_tree "$target_dir" "$repo_ref"
    return 0
  fi

  if use_local_packages; then
    echo "ERRO: USE_LOCAL_PACKAGES=true e '$target_dir' ausente." >&2
    echo "      Monte o repositório local (ex.: ../ no docker-compose) ou desative USE_LOCAL_PACKAGES." >&2
    exit 1
  fi

  rm -rf "$target_dir"
  mkdir -p "$target_dir"
  git clone "$repo_url" "$target_dir"

  if [ -n "$repo_ref" ]; then
    git -C "$target_dir" checkout "$repo_ref"
  fi

  # Só em clones efémeros (build de imagem): evita "dubious ownership" no composer/git.
  # Em bind mount / checkout local o .git nunca chega aqui.
  rm -rf "$target_dir/.git"
}

# Token só é obrigatório quando vamos clonar (sem árvore/mount local).
require_git_token_unless_local() {
  target_dir="$1"
  if [ -f "$target_dir/composer.json" ] || is_mountpoint "$target_dir" || use_local_packages; then
    return 0
  fi
  require_git_token
}

apply_cdn_config_customizations() {
  cdn_dir="${CDN_CONFIG_PATH:-cdn-config}"
  cloned_ephemeral=0

  if [ -d "$cdn_dir/i-educar-reports-package" ]; then
    echo ">> cdn-config local em '$cdn_dir' (sem clone remoto)."
  elif use_local_packages; then
    echo "ERRO: USE_LOCAL_PACKAGES=true e cdn-config ausente em '$cdn_dir'." >&2
    echo "      Monte ../cdn-config (CDN_CONFIG_PATH) ou desative USE_LOCAL_PACKAGES." >&2
    exit 1
  elif [ -n "${GIT_TOKEN:-}" ]; then
    # Nunca rm -rf num bind mount (apagaria o host).
    if is_mountpoint "$cdn_dir"; then
      echo "ERRO: '$cdn_dir' é mountpoint sem conteúdo esperado de cdn-config." >&2
      exit 1
    fi
    rm -rf "$cdn_dir"
    git clone "https://${GIT_TOKEN}@github.com/semed-al/cdn-config.git" "$cdn_dir"
    cloned_ephemeral=1
  else
    echo "AVISO: GIT_TOKEN ausente e cdn-config local não encontrado; pulando customizações SEMED-AL."
    return 0
  fi

  cp -r "$cdn_dir/i-educar-reports-package/ieducar/"* \
    packages/portabilis/i-educar-reports-package/ieducar/
  cp -r "$cdn_dir/i-educar-reports-package/database/"* \
    packages/portabilis/i-educar-reports-package/database/
  cp -r "$cdn_dir/i-educar/ieducar/"* ./ieducar/

  cp -r "$cdn_dir/images/brasao/"* packages/portabilis/i-educar-reports-package/ieducar/ReportLogos/
  chmod -R 755 packages/portabilis/i-educar-reports-package/ieducar/ReportLogos/

  if [ "$cloned_ephemeral" = "1" ] && ! is_mountpoint "$cdn_dir"; then
    rm -rf "$cdn_dir"
  fi
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

skip_db_steps() {
  case "${IEDUCAR_SKIP_DB:-}" in
    1|true|TRUE|yes|YES) return 0 ;;
    *) return 1 ;;
  esac
}

# Build da imagem nao inclui .env; varios comandos artisan exigem APP_KEY.
# cache:clear/migrate nao correm no build (IEDUCAR_SKIP_DB) — ver entrypoint.prod.sh e deploy-city.sh.
apply_artisan_build_env() {
  export APP_ENV="${APP_ENV:-production}"
  export CI="${CI:-true}"
  if [ -z "${APP_KEY:-}" ]; then
    export APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
  fi
  if skip_db_steps; then
    export CACHE_STORE="${CACHE_STORE:-array}"
    export CACHE_DRIVER="${CACHE_DRIVER:-array}"
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
    echo ">> README relatórios: community:reports:link, publish de assets"
    if artisan_cmd_exists community:reports:link; then
      php artisan community:reports:link --no-interaction || true
    fi
    if skip_db_steps; then
      echo ">> Build: a omitir community:reports:install (chama migrate; corre no entrypoint/deploy)."
    elif artisan_cmd_exists community:reports:install; then
      php artisan community:reports:install --no-interaction || \
        echo ">> AVISO: community:reports:install falhou (BD/migrate pendente?). Execute após migrate: php artisan community:reports:install" >&2
    fi
    php artisan vendor:publish --tag=reports-assets --ansi --force --no-interaction 2>/dev/null || true
  fi

  if [ "${ENABLE_PACKAGE_PRE_MATRICULA:-false}" = "true" ] && [ -d packages/portabilis/pre-matricula-digital ]; then
    echo ">> README pre-matricula-digital: vendor:publish --tag=pmd"
    php artisan vendor:publish --tag=pmd --force --no-interaction 2>/dev/null || true
  fi

  if [ "${ENABLE_PACKAGE_MERENDA:-false}" = "true" ]; then
    echo ">> Merenda: a copiar CSS/JS para public/vendor/merenda"
    merenda_public="packages/merenda/merenda-escolar/public"
    if [ -d "$merenda_public" ]; then
      mkdir -p public/vendor/merenda
      cp -a "$merenda_public"/. public/vendor/merenda/
    fi
    php artisan vendor:publish --tag=merenda-assets --force --no-interaction 2>/dev/null || true
  fi
}

# No docker build, aplicar CACHE_STORE=array antes de qualquer artisan/composer.
if skip_db_steps; then
  apply_artisan_build_env
fi

ensure_laravel_runtime_dirs

ensure_git_safe_directories

installed_any_package=0

if [ "${ENABLE_PACKAGE_REPORTS:-false}" = "true" ]; then
  clone_or_update_repo \
    "${PACKAGE_REPO_REPORTS:-https://github.com/williamsla/i-educar-reports-package.git}" \
    "packages/portabilis/i-educar-reports-package" \
    "${PACKAGE_REF_REPORTS:-}"

  # Ajustes especificos para SEMED-AL (cdn-config local ou clone com token).
  apply_cdn_config_customizations

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
  require_git_token_unless_local "packages/despesas-escolar"
  clone_or_update_repo \
    "${PACKAGE_REPO_DESPESAS:-https://${GIT_TOKEN}@github.com/williamsla/despesas-escolar.git}" \
    "packages/despesas-escolar" \
    "${PACKAGE_REF_DESPESAS:-}"

  strip_sudo_from_script "packages/despesas-escolar/install.sh"
  ensure_laravel_runtime_dirs
  if [ -f packages/despesas-escolar/composer.json ]; then
    composer config --json repositories.despesas \
      '{"type":"path","url":"packages/despesas-escolar","options":{"symlink":true}}' \
      --no-interaction 2>/dev/null || true
    if ! composer show ieducar/despesa-escolar >/dev/null 2>&1; then
      composer require "ieducar/despesa-escolar:*" --no-update --no-interaction || true
    fi
  fi
  if skip_db_steps; then
    echo ">> Build: a omitir install.sh (cache:clear/migrate no entrypoint/deploy)."
  else
    run_if_exists "packages/despesas-escolar/install.sh"
  fi

  installed_any_package=1
fi

if [ "${ENABLE_PACKAGE_MERENDA:-false}" = "true" ]; then
  mkdir -p packages/merenda

  require_git_token_unless_local "packages/merenda/merenda-escolar"
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
    if skip_db_steps; then
      echo ">> Build: a omitir instalar_modulo.sh (cache:clear/migrate no entrypoint/deploy)."
    else
      sh "$merenda_install"
    fi
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
