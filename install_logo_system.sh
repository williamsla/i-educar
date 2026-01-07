#!/bin/bash
# install_logo_system.sh

echo "Instalando sistema de logos universal..."

# Cria diretórios necessários
mkdir -p storage/app/public/logos
mkdir -p public/storage/logos

# Ajusta permissões
chmod 755 storage/app/public/logos
chmod 755 public/storage/logos
chown www-data:www-data storage/app/public/logos 2>/dev/null || true
chown www-data:www-data public/storage/logos 2>/dev/null || true

echo "Diretórios criados com sucesso!"
echo ""
echo "Agora:"
echo "1. Substitua o arquivo educar_configurações_gerais.php"
echo "2. Atualize o template de login com o código universal"
echo "3. Teste o upload de logo"
