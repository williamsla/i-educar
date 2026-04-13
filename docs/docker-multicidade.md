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
