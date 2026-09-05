#!/bin/sh
set -e

# Configura SQLite se for o banco selecionado
if [ "$DB_CONNECTION" = "sqlite" ]; then
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
exec php -S 0.0.0.0:"$PORT" -t public
