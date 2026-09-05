#!/bin/sh
set -e

# Configura .env padrão caso não exista
if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate --force
    
    # Força configurações para rodar num único container (sem MySQL/Redis externo)
    sed -i 's/APP_ENV=local/APP_ENV=production/g' .env
    sed -i 's/APP_DEBUG=true/APP_DEBUG=false/g' .env
    sed -i 's/DB_CONNECTION=mysql/DB_CONNECTION=sqlite/g' .env
    sed -i 's/CACHE_DRIVER=redis/CACHE_DRIVER=file/g' .env
    sed -i 's/SESSION_DRIVER=redis/SESSION_DRIVER=file/g' .env
    sed -i 's/QUEUE_CONNECTION=redis/QUEUE_CONNECTION=sync/g' .env
fi

# Configura SQLite
if grep -q "DB_CONNECTION=sqlite" .env || [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    mkdir -p database
    touch database/database.sqlite
    chmod 666 database/database.sqlite
    chmod 777 database
fi

# Descoberta de pacotes em runtime com variaveis de ambiente ativas
php artisan package:discover --ansi || true

# Executa migrations e seeders
php artisan migrate --force --seed --graceful || true

# Inicia servidor web na porta informada pelo Render ($PORT)
PORT="${PORT:-8000}"
echo "=== Prefiro Delivery AI rodando na porta $PORT ==="
exec php artisan serve --host=0.0.0.0 --port="$PORT"
