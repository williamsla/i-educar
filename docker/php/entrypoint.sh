#!/bin/sh

# Corrige permissões apenas se necessário
chown -R www-data:www-data /var/www/ieducar/storage /var/www/ieducar/bootstrap/cache

cd /var/www/ieducar
composer update-install

# --- Ativa o pacote de relatórios via Composer plug-and-play ---
if [ -d "/var/www/ieducar/packages/portabilis/i-educar-reports-package" ]; then
  echo "Ativando pacote de relatórios via Composer plug-and-play..."
  composer plug-and-play
else
  echo "Atenção: pacote i-educar-reports-package não encontrado em packages/portabilis/"
fi

# --- Instala o pacote de relatórios via Artisan ---
echo "Instalando pacote de relatórios..."
php artisan community:reports:install || true

php artisan migrate

# --- Inicia o PHP-FPM ---
exec php-fpm
