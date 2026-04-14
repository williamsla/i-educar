#!/bin/sh
set -e

cd /var/www/ieducar

# Garante links estaticos esperados pelo front legacy.
mkdir -p public/intranet
ln -sfn /var/www/ieducar/storage/app/public /var/www/ieducar/public/storage
ln -sfn /var/www/ieducar/ieducar/intranet/fonts /var/www/ieducar/public/intranet/fonts
ln -sfn /var/www/ieducar/ieducar/intranet/imagens /var/www/ieducar/public/intranet/imagens
ln -sfn /var/www/ieducar/ieducar/intranet/scripts /var/www/ieducar/public/intranet/scripts
ln -sfn /var/www/ieducar/ieducar/intranet/static /var/www/ieducar/public/intranet/static
ln -sfn /var/www/ieducar/ieducar/intranet/styles /var/www/ieducar/public/intranet/styles
ln -sfn /var/www/ieducar/ieducar/intranet/tmp /var/www/ieducar/public/intranet/tmp
ln -sfn /var/www/ieducar/ieducar/modules /var/www/ieducar/public/modules

: "${SERVER_NAME:=localhost}"
: "${FPM_UPSTREAM:=fpm}"

envsubst '$SERVER_NAME $FPM_UPSTREAM' \
  < /etc/nginx/templates/default.template.conf \
  > /etc/nginx/conf.d/default.conf

exec nginx -g 'daemon off;'
