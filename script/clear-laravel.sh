#!/bin/bash
# Script para limpar caches do Laravel

echo "==> Limpando cache de configuração..."
php artisan config:clear

echo "==> Limpando cache de aplicação..."
php artisan cache:clear

echo "==> Limpando cache de views..."
php artisan view:clear

echo "==> Cache limpo com sucesso!"
