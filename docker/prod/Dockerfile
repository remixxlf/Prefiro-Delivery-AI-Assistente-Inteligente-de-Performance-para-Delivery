# Multi-stage Dockerfile para Deploy em Produção (Railway, Render, Fly.io)

# ── Stage 1: Build do Frontend Vue 3 ──────────────────────────────────
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# ── Stage 2: Runtime PHP 8.3 ──────────────────────────────────────────
FROM php:8.3-cli-alpine AS runtime

WORKDIR /var/www

# Dependências do sistema e extensões PHP essenciais
RUN apk add --no-cache \
    git \
    curl \
    curl-dev \
    libpng-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    sqlite \
    sqlite-dev \
    oniguruma-dev \
    && docker-php-ext-install pdo pdo_mysql pdo_sqlite mbstring bcmath xml curl zip

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configuração de memória do Composer
ENV COMPOSER_MEMORY_LIMIT=-1
ENV COMPOSER_ALLOW_SUPERUSER=1

# Código da aplicação
COPY . .
COPY --from=frontend /app/public/build ./public/build

# Instala dependências PHP de produção com tolerância de plataforma e sem scripts
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --ignore-platform-reqs

# Permissões de storage e bootstrap/cache
RUN mkdir -p storage/framework/views storage/framework/cache storage/framework/sessions storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && chmod +x docker/prod/entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["docker/prod/entrypoint.sh"]