#!/bin/sh
set -e

cd /var/www/ieducar

# Links relativos: funcionam no host e no container (bind-mount).
mkdir -p public/intranet
ln -sfn ../storage/app/public public/storage
ln -sfn ../ieducar/modules public/modules
ln -sfn ../../ieducar/intranet/fonts public/intranet/fonts
ln -sfn ../../ieducar/intranet/imagens public/intranet/imagens
ln -sfn ../../ieducar/intranet/scripts public/intranet/scripts
ln -sfn ../../ieducar/intranet/static public/intranet/static
ln -sfn ../../ieducar/intranet/styles public/intranet/styles
ln -sfn ../../ieducar/intranet/tmp public/intranet/tmp

: "${SERVER_NAME:=localhost}"
: "${FPM_UPSTREAM:=fpm}"

envsubst '$SERVER_NAME $FPM_UPSTREAM' \
  < /etc/nginx/templates/default.template.conf \
  > /etc/nginx/conf.d/default.conf

exec nginx -g 'daemon off;'
