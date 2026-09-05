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

# Dependências do sistema e extensões PHP
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    sqlite \
    sqlite-dev \
    oniguruma-dev \
    && docker-php-ext-install pdo pdo_mysql pdo_sqlite mbstring bcmath

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Código da aplicação
COPY . .
COPY --from=frontend /app/public/build ./public/build

# Instala dependências PHP de produção
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Permissões de storage e bootstrap/cache
RUN chmod -R 775 storage bootstrap/cache \
    && chmod +x docker/prod/entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["docker/prod/entrypoint.sh"]