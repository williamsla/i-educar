ARG PHP_VERSION=8
ARG COMPOSER_VERSION=latest

FROM composer:${COMPOSER_VERSION} AS composer
FROM php:${PHP_VERSION}-fpm-alpine

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

LABEL maintainer="Portabilis <contato@portabilis.com.br>"

ENV COMPOSER_ALLOW_SUPERUSER 1
ENV COMPOSER_PROCESS_TIMEOUT 900
ENV COMPOSER_DISABLE_XDEBUG_WARN=1

ENV XDEBUG_IDEKEY=xdebug
ENV XDEBUG_CLIENT_HOST=host.docker.internal
ENV XDEBUG_CLIENT_PORT=9003
ENV XDEBUG_MODE=debug
ENV XDEBUG_START_WITH_REQUEST=off

COPY xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini
COPY conf.d/99-uploads.ini /usr/local/etc/php/conf.d/99-uploads.ini

# Alpine 3.23+ (apk-tools 3): o trigger do postgresql-common falha sem man pages;
# um segundo `apk add --virtual $PHPIZE_DEPS` costuma terminar em "1 error" no busybox trigger.
RUN set -eux; \
    mkdir -p /usr/share/man/man1 /usr/share/man/man7 /usr/share/man/man8; \
    apk upgrade --no-cache --scripts=no apk-tools; \
    apk add --no-cache \
        bash \
        libc-dev \
        libpng-dev \
        libzip-dev \
        linux-headers \
        openjdk8 \
        postgresql17-client \
        postgresql-dev \
        su-exec \
        ttf-dejavu \
        unzip \
        openssh \
        git \
        $PHPIZE_DEPS

RUN pecl install \
    redis \
    xdebug

RUN docker-php-ext-enable \
    redis \
    xdebug

RUN docker-php-ext-install  \
    bcmath \
    gd \
    pcntl \
    pdo \
    pdo_pgsql \
    pgsql \
    zip \
    ftp

RUN docker-php-ext-enable  \
     bcmath \
     gd \
     pcntl \
     pdo \
     pdo_pgsql \
     pgsql \
     zip \
     ftp

RUN apk del --no-network \
    autoconf \
    dpkg \
    dpkg-dev \
    file \
    g++ \
    gcc \
    make \
    pkgconf \
    re2c

RUN ln -s /var/www/ieducar/artisan /usr/local/bin/artisan

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

COPY laravel-permission-entrypoint.sh /usr/local/bin/laravel-permission-entrypoint.sh
RUN chmod +x /usr/local/bin/laravel-permission-entrypoint.sh

COPY fpm-healthcheck.sh /usr/local/bin/fpm-healthcheck.sh
RUN chmod +x /usr/local/bin/fpm-healthcheck.sh

# Argumentos para UID e GID do usuário do host
# Esses valores devem ser passados pelo docker-compose.yml
# para garantir que os arquivos gerados no volume montado
# tenham o mesmo proprietário do usuário no host (ex: ieducar)
# Padrão se o build não receber --build-arg (evita `addgroup -g` sem GID).
ARG HOST_UID=1001
ARG HOST_GID=1001

# Criação de grupo e usuário com UID e GID iguais ao do host
# Isso garante que arquivos criados dentro do container tenham
# as mesmas permissões do usuário do host (evita problemas com volumes)
RUN addgroup -g ${HOST_GID} ieducar 2>/dev/null || addgroup ieducar && \
    adduser -u ${HOST_UID} -G ieducar -s /bin/sh -D ieducar 2>/dev/null || \
    adduser -G ieducar -s /bin/sh -D ieducar

# A imagem oficial aponta error_log (e às vezes access.log) para /proc/self/fd/*.
# Com o master do FPM rodando como usuário não-root (su-exec ieducar no
# laravel-permission-entrypoint), abrir stderr pode falhar com EACCES e o FPM
# não sobe. Logs passam para arquivos em /tmp (gravável por qualquer UID).
RUN set -eu; \
    for f in /usr/local/etc/php-fpm.conf /usr/local/etc/php-fpm.d/*.conf; do \
        if [ -f "$f" ]; then \
            sed -i 's#/proc/self/fd/2#/tmp/php-fpm-error.log#g' "$f"; \
            sed -i 's#/proc/self/fd/1#/tmp/php-fpm-access.log#g' "$f" || true; \
        fi; \
    done

# Define o usuário padrão do container para evitar execução como root
USER ieducar

# Define o diretório de trabalho
WORKDIR /var/www/ieducar
