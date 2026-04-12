#!/bin/bash
# Corrige permissões para o Nginx/PHP-FPM acessarem o projeto (evita "Permission denied" e "Primary script unknown").
# Use quando os logs mostrarem: stat() ".../public/" failed (13: Permission denied)

set -e

# Diretório do projeto (raiz do repositório)
PROJECT_ROOT="${1:-$(cd "$(dirname "$0")/.." && pwd)}"
cd "$PROJECT_ROOT"

echo "==> Ajustando permissões em: $PROJECT_ROOT"
echo ""

# 1. Diretórios: leitura e execução para outros (para o usuário nginx conseguir atravessar)
echo "==> Diretórios: chmod 755 (rx para outros)..."
find . -type d -not -path '*/\.*' -exec chmod 755 {} \;

# 2. Arquivos: leitura para outros
echo "==> Arquivos: chmod 644 (r para outros)..."
find . -type f -not -path '*/\.*' -exec chmod 644 {} \;

# 3. Scripts/executáveis que precisam de execução
echo "==> Artisan e scripts: +x..."
[ -f artisan ] && chmod +x artisan
[ -d script ] && find script -type f -name '*.sh' -exec chmod +x {} \;

# 4. storage e bootstrap/cache: precisam ser graváveis pelo PHP-FPM
echo "==> Storage e cache: 775 para escrita pelo servidor..."
for dir in storage storage/framework storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache; do
  [ -d "$dir" ] && chmod -R 775 "$dir" && echo "    $dir"
done

echo ""
echo "==> Concluído. Reinicie o Nginx (ou os containers) se ainda estiver rodando:"
echo "    Docker: docker compose restart nginx"
echo "    Host:   sudo systemctl reload nginx"
