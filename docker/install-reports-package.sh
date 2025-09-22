#!/bin/bash

# Script simples para instalar pacote de relatórios i-Educar

echo "🚀 Iniciando instalação do pacote de relatórios..."

# pacote de relatórios deve estar no mesmo nível do i-educar

# Instala o pacote
echo "⚙️  Instalando pacote..."
sudo docker-compose exec php composer plug-and-play
sudo docker-compose exec php php artisan community:reports:install
sudo docker-compose exec php php artisan vendor:publish --tag=reports-assets --ansi

sudo docker-compose exec php php artisan config:clear
sudo docker-compose exec php php artisan cache:clear
sudo docker-compose exec php php artisan view:clear


# Reinicia containers
echo "🔄 Reiniciando containers..."
sudo docker-compose restart

echo "✅ Instalação concluída!"
echo "🌐 Acesse o i-Educar no navegador para testar os relatórios"