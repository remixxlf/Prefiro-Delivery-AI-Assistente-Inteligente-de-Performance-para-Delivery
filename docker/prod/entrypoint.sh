#!/bin/sh
set -e

# Cria sqlite se não houver banco externo configurado
if [ "$DB_CONNECTION" = "sqlite" ] && [ ! -f "database/database.sqlite" ]; then
    touch database/database.sqlite
fi

# Executa migrations
php artisan migrate --force --graceful

# Otimiza caches para produção
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Inicia o servidor embutido do PHP na porta configurada pelo ambiente
PORT="${PORT:-8000}"
echo "Iniciando Prefiro Delivery AI na porta $PORT..."
exec php -S 0.0.0.0:"$PORT" -t public