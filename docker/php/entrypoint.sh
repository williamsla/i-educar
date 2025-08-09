#!/bin/sh

# Corrige permissões apenas se necessário
chown -R www-data:www-data /var/www/ieducar/storage /var/www/ieducar/bootstrap/cache

# Inicia o PHP-FPM
exec php-fpm
