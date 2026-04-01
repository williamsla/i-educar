#!/bin/bash
# Script para forçar atualização de assets após alteração de arquivos JavaScript/CSS

echo "==> Forçando atualização de assets..."

# 1. Limpar cache do Laravel
echo "==> Limpando cache de configuração..."
php artisan config:clear

echo "==> Limpando cache de aplicação..."
php artisan cache:clear

echo "==> Limpando cache de views..."
php artisan view:clear

# 2. Verificar se o arquivo foi atualizado
echo ""
echo "==> Verificando data de modificação do arquivo Diario.js..."
if [ -f "public/vendor/legacy/Avaliacao/Assets/Javascripts/Diario.js" ]; then
    ls -lh public/vendor/legacy/Avaliacao/Assets/Javascripts/Diario.js
    echo "✅ Arquivo encontrado"
else
    echo "❌ Arquivo não encontrado!"
    exit 1
fi

# 3. Recarregar configuração do Nginx (se necessário)
echo ""
echo "==> Recarregando Nginx..."
if command -v nginx &> /dev/null; then
    nginx -s reload 2>/dev/null || echo "⚠️  Nginx não está rodando ou não tem permissão"
else
    echo "⚠️  Nginx não encontrado"
fi

echo ""
echo "✅ Processo concluído!"
echo ""
echo "📝 Próximos passos:"
echo "   1. Limpe o cache do navegador (Ctrl+Shift+R ou Ctrl+F5)"
echo "   2. Ou teste em modo anônimo/privado"
echo "   3. Verifique no DevTools (F12) se o arquivo está sendo carregado com a nova versão"
echo ""


