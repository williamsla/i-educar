#!/bin/sh
set -e

cd /var/www/ieducar

# JasperStarter invoca java; sem JAVA_HOME o worker php-fpm pode nao achar o JRE
# (especialmente em Alpine). Definir aqui garante heranca ao processo php-fpm.
if command -v java >/dev/null 2>&1; then
  _java_bin="$(command -v java)"
  if command -v readlink >/dev/null 2>&1; then
    _java_real="$(readlink -f "$_java_bin" 2>/dev/null || echo "$_java_bin")"
  else
    _java_real="$_java_bin"
  fi
  if [ -n "$_java_real" ]; then
    export JAVA_HOME="$(dirname "$(dirname "$_java_real")")"
  fi
fi

# Garante diretorios de escrita do Laravel.
mkdir -p \
  storage/logs \
  storage/framework/cache \
  storage/framework/sessions \
  storage/framework/views \
  bootstrap/cache

# Evita manifest de pacotes desatualizado (ex.: Lighthouse listado sem nuwave/lighthouse no vendor).
rm -f bootstrap/cache/*.php

chown -R www-data:www-data storage bootstrap/cache || true
chmod -R ug+rwX storage bootstrap/cache || true

# Jasper compila .jrxml -> .jasper no mesmo diretorio (ReportSources). Ficheiros
# vindos de git clone no build costumam ser root:root — www-data precisa de escrita.
for _r in \
  ieducar/modules/Reports \
  packages/portabilis/i-educar-reports-package/ieducar/modules/Reports
do
  if [ -e "$_r" ]; then
    chown -R www-data:www-data "$_r" || true
    chmod -R ug+rwX "$_r" || true
  fi
done

exec "$@"
