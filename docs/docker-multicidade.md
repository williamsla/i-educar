# Docker multicidade (2 cidades)

Este projeto pode rodar multiplas instancias usando o mesmo codigo-fonte.
Cada cidade tem seu proprio `.env`, Redis e Nginx.
O Postgres fica em uma VM externa e e acessado via IP privado.

## Arquivos criados

- `docker-compose.multicidade.yml`
- `.env.cidade1.example`
- `.env.cidade2.example`
- `docker/nginx/conf.d/default-cidade1.conf`
- `docker/nginx/conf.d/default-cidade2.conf`

## 1) Ajustar variaveis de cada cidade

Edite os arquivos:

- `.env.cidade1.example`
- `.env.cidade2.example`

Campos principais:

- `APP_KEY` (gere uma chave Laravel por cidade)
- `APP_URL`
- `DB_HOST` (IP privado da VM de banco, ex.: `10.10.10.20`)
- `DB_PORT` (porta da VM, normalmente `5432`)
- `DB_DATABASE`
- `SESSION_COOKIE` (nome unico por cidade)
- `REDIS_PREFIX` (prefixo unico por cidade)

## 2) Subir as duas cidades

```bash
docker-compose -f docker-compose.multicidade.yml up -d --build
```

```bash
mkdir -p vendor
chmod -R 777 vendor
chmod -R 777 storage bootstrap/cache
```

Criar key do laravel e alterar .env manualmente 
```bash
docker compose exec php_igaci php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"

KEY=saida anterior

cd /var/www

chown -R 33:33 .
# 33 = www-data (padrão PHP-FPM)

cd /var/www

chown -R 33:33 ieducar
chmod -R 775 ieducar
chmod -R 775 /var/www/ieducar/storage
chmod -R 775 /var/www/ieducar/bootstrap/cache

mkdir -p /var/www/ieducar/storage/logs 
chown -R www-data:www-data /var/www/ieducar/storage /var/www/ieducar/bootstrap/cache
chmod -R ug+rwX /var/www/ieducar/storage /var/www/ieducar/bootstrap/cache

```


Aplicacoes:

- Cidade 1: `http://localhost:8081`
- Cidade 2: `http://localhost:8082`

## 3) Rodar comandos Artisan por cidade

Cidade 1:

```bash
docker-compose -f docker-compose.multicidade.yml exec php_cidade1 php artisan key:generate --force
docker-compose -f docker-compose.multicidade.yml exec php_cidade1 php artisan migrate --force
```

Cidade 2:

```bash
docker-compose -f docker-compose.multicidade.yml exec php_cidade2 php artisan key:generate --force
docker-compose -f docker-compose.multicidade.yml exec php_cidade2 php artisan migrate --force
```

## 4) Parar tudo

```bash
docker-compose -f docker-compose.multicidade.yml down
```

Para remover tambem os dados locais (Redis):

```bash
docker-compose -f docker-compose.multicidade.yml down -v
```

## 5) Adicionar cidade 3

Duplique os blocos `*_cidade2` no `docker-compose.multicidade.yml`, renomeando para `*_cidade3` e ajuste:

- portas (ex.: `8083`, `6383`)
- banco (ex.: `ieducar_cidade3`)
- arquivo `.env` (ex.: `.env.cidade3.example`)
- `fastcgi_pass` no Nginx (`fpm_cidade3:9000`)

## 6) Instalar pacote externo (ex.: relatórios)

Para pacotes externos em multicidade, use esta regra:

- instalacao de codigo (git/composer/publish) = 1 vez no projeto
- comandos que alteram banco = 1 vez por cidade

### Exemplo: `portabilis/i-educar-reports-package`

1) Baixe o pacote na pasta correta:

```bash
git clone https://github.com/portabilis/i-educar-reports-package.git packages/portabilis/i-educar-reports-package
```

2) Ative o plug-and-play (uma vez):

```bash
docker-compose -f docker-compose.multicidade.yml exec php_cidade1 composer plug-and-play
```

3) Rode a instalacao no banco de cada cidade:

```bash
docker-compose -f docker-compose.multicidade.yml exec php_cidade1 php artisan community:reports:install
docker-compose -f docker-compose.multicidade.yml exec php_cidade2 php artisan community:reports:install
```

4) Publique assets (uma vez):

```bash
docker-compose -f docker-compose.multicidade.yml exec php_cidade1 php artisan vendor:publish --tag=reports-assets --ansi
```

5) Limpe cache por cidade:

```bash
docker-compose -f docker-compose.multicidade.yml exec php_cidade1 php artisan optimize:clear
docker-compose -f docker-compose.multicidade.yml exec php_cidade2 php artisan optimize:clear
```

Observacoes:

- A imagem PHP deste projeto ja instala `openjdk8`, necessario para os relatórios.
- O pacote oficial: [portabilis/i-educar-reports-package](https://github.com/portabilis/i-educar-reports-package).

## 7) Produção sem bind mount (imagem com código)

Para produção, use:

- `docker-compose.multicidade.prod.yml`
- `docker/php/Dockerfile.prod`
- `docker/nginx/Dockerfile.prod`
- `docker/php/install-extra-packages.sh`: apos `plug-and-play`, na build roda só o que nao exige DB: relatórios (`community:reports:link`, publish de assets), pré-matrícula (`vendor:publish --tag=pmd`). O `community:reports:install` roda apenas no `deploy-city.sh` apos `migrate`.

Nesta estrategia o código vai dentro das imagens e nao e montado via volume.

### 7.1 Preparar envs de produção

Crie os arquivos reais (nao usar `*.example` em produção):

```bash
cp .env.cidade1.example .env.cidade1
cp .env.cidade2.example .env.cidade2
```

Edite os dois arquivos com:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL` correto por cidade
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `SESSION_COOKIE` e `REDIS_PREFIX` unicos por cidade

### 7.2 Build e subida

```bash
docker-compose -f docker-compose.multicidade.prod.yml up -d --build
```

Se quiser apontar arquivos diferentes:

```bash
ENV_CIDADE1_FILE=.env.cidade1 ENV_CIDADE2_FILE=.env.cidade2 docker-compose -f docker-compose.multicidade.prod.yml up -d --build
```

### 7.3 Comandos por cidade (migracao e cache)

```bash
docker-compose -f docker-compose.multicidade.prod.yml exec php_cidade1 php artisan migrate --force
docker-compose -f docker-compose.multicidade.prod.yml exec php_cidade2 php artisan migrate --force

docker-compose -f docker-compose.multicidade.prod.yml exec php_cidade1 php artisan optimize:clear
docker-compose -f docker-compose.multicidade.prod.yml exec php_cidade2 php artisan optimize:clear
```

### 7.4 Observações

- Mudou o código? precisa rebuild de imagem (`up -d --build`).
- O `vendor:publish` continua sendo 1 vez por release; comandos que alteram banco continuam 1 vez por cidade.

## 8) Publicar no Registry (Magalu) e fazer deploy por imagem

Objetivo: buildar imagens, enviar para o registry e subir com compose usando apenas `image:`.

Arquivos usados:

- `docker/build-push-registry.sh`
- `docker/.env.registry.example`
- `docker-compose.multicidade.registry.yml`

### 8.1 Login no registry

```bash
docker login registry.magalu.cloud
```

Use usuario/token do seu projeto no Container Registry da Magalu.

### 8.2 Build e push das imagens

```bash
REGISTRY_HOST=registry.magalu.cloud \
REGISTRY_NAMESPACE=seu-projeto \
IMAGE_TAG=2.10.0 \
./docker/build-push-registry.sh
```

Para incluir pacotes extras ja na imagem:

```bash
REGISTRY_HOST=registry.magalu.cloud \
REGISTRY_NAMESPACE=seu-projeto \
IMAGE_TAG=2.10.0 \
ENABLE_PACKAGE_REPORTS=true \
ENABLE_PACKAGE_EDUCACENSO=true \
ENABLE_PACKAGE_TRANSPORTE=true \
ENABLE_PACKAGE_PRE_MATRICULA=true \
./docker/build-push-registry.sh
```

Se habilitar `ENABLE_PACKAGE_DESPESAS=true` ou `ENABLE_PACKAGE_MERENDA=true`, o build **exige** token: defina `GIT_TOKEN` no ambiente **antes** de `./docker/build-push-registry.sh` (o script monta `--secret id=git_token,env=GIT_TOKEN`), ou use `GIT_TOKEN_FILE=/caminho/arquivo.txt` com o token numa linha. Com `sudo`, use `sudo -E` ou `sudo env GIT_TOKEN=...` para o Docker receber a variavel. Nao use `--build-arg` com o token (fica no historico da imagem).

### 8.3 Preparar variaveis de deploy

```bash
cp docker/.env.registry.example docker/.env.registry
```

Edite `docker/.env.registry` com namespace/tag reais, dominios das cidades e os slugs de nome dos containers (`CIDADE1_SLUG`, `CIDADE2_SLUG`).

### 8.4 Deploy puxando imagens do registry

```bash
docker-compose --env-file docker/.env.registry -f docker-compose.multicidade.registry.yml pull
docker-compose --env-file docker/.env.registry -f docker-compose.multicidade.registry.yml up -d
```

Cada cidade sobe um worker de fila (`queue_cidadeN` → container `ieducar-queue-{slug}-prod`) com:

`php artisan queue:work redis --sleep=1 --tries=1 --timeout=1800 --memory=512`

No `.env` da cidade use `QUEUE_CONNECTION=redis` (e `CACHE_STORE=redis`). Sem esse container, jobs longos (ex.: verificar CPF eSUS) ficam eternamente em "Na fila…".

### 8.5 Migrations por cidade

```bash
docker-compose --env-file docker/.env.registry -f docker-compose.multicidade.registry.yml exec php_cidade1 php artisan migrate --force
docker-compose --env-file docker/.env.registry -f docker-compose.multicidade.registry.yml exec php_cidade2 php artisan migrate --force
```

Pacotes que criam tabelas/estrutura continuam exigindo migration por cidade.

### 8.6 Deploy automatico por cidade (quando imagem muda)

Para automatizar o momento certo, use:

- `docker/deploy-city.sh`

Esse script:

- faz `pull` da cidade
- recria os servicos da cidade
- compara hash das imagens antiga/nova
- se mudou imagem, executa automaticamente (imagem imutavel: sem `composer` em runtime; vendor e plug-and-play vêm do build da imagem, ex.: `docker/build-push-registry.sh` com `ENABLE_PACKAGE_*`):
  - validacao de `DB_CONNECTION=pgsql` e `CACHE_*` em redis
  - limpeza de `bootstrap/cache/*.php`
  - `php artisan storage:link`
  - `php artisan migrate --force`
  - se existir `packages/portabilis/i-educar-reports-package`, alinha com o README dos relatórios: `community:reports:link`, `community:reports:install`, `vendor:publish --tag=reports-assets`
  - `php artisan optimize:clear`

Exemplo (cidade1):

```bash
CITY_INDEX=1 ENV_FILE=docker/.env.registry COMPOSE_FILE=docker-compose.multicidade.registry.yml ./docker/deploy-city.sh
```

Para forcar o pos-deploy mesmo sem mudanca de imagem:

```bash
CITY_INDEX=1 FORCE_POST_DEPLOY=true ENV_FILE=docker/.env.registry COMPOSE_FILE=docker-compose.multicidade.registry.yml ./docker/deploy-city.sh
```

### 8.7 Deploy em lote (todas as cidades)

Para atualizar todas as cidades de uma vez:

```bash
ENV_FILE=docker/.env.registry COMPOSE_FILE=docker-compose.multicidade.registry.yml ./docker/deploy-all-cities.sh
```

O script detecta automaticamente os servicos `php_cidadeN` existentes no compose e executa `deploy-city.sh` para cada indice.

Para limitar a lista:

```bash
CITY_INDEXES=1,2,3 ENV_FILE=docker/.env.registry COMPOSE_FILE=docker-compose.multicidade.registry.yml ./docker/deploy-all-cities.sh
```
