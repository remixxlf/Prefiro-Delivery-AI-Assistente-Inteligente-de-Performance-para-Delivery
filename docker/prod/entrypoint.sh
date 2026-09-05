#!/bin/sh
set -e

# Garante variáveis de ambiente críticas exportadas para o container
export APP_KEY="${APP_KEY:-base64:u1+Z5v/Oq4mG1aE6rW8tN0xY9pL2kJ3bV4cF5eR7tP8=}"
export CACHE_STORE="file"
export CACHE_DRIVER="file"
export SESSION_DRIVER="file"
export QUEUE_CONNECTION="sync"
export DB_CONNECTION="sqlite"

# Configura .env padrão caso não exista
if [ ! -f .env ]; then
    cp .env.example .env
fi

# Força configurações para rodar num único container (sem MySQL/Redis externo)
sed -i 's/APP_ENV=local/APP_ENV=production/g' .env
sed -i 's/DB_CONNECTION=mysql/DB_CONNECTION=sqlite/g' .env
sed -i 's/CACHE_DRIVER=.*/CACHE_DRIVER=file/g' .env
sed -i 's/SESSION_DRIVER=.*/SESSION_DRIVER=file/g' .env
sed -i 's/QUEUE_CONNECTION=.*/QUEUE_CONNECTION=sync/g' .env
sed -i 's|^APP_KEY=$|APP_KEY=base64:u1+Z5v/Oq4mG1aE6rW8tN0xY9pL2kJ3bV4cF5eR7tP8=|g' .env
grep -q "CACHE_STORE=" .env && sed -i 's/CACHE_STORE=.*/CACHE_STORE=file/g' .env || echo "CACHE_STORE=file" >> .env

# Sincroniza chaves de IA do ambiente do container (Render) diretamente para o .env
for var in GROQ_API_KEY GROK_API_KEY GEMINI_API_KEY GOOGLE_API_KEY OPENAI_API_KEY OPENROUTER_API_KEY AI_PROVIDER GROQ_MODEL GROK_MODEL; do
    eval val=\$$var
    if [ -n "$val" ]; then
        if [ "$var" = "GROK_API_KEY" ]; then
            grep -q "^GROQ_API_KEY=" .env && sed -i "s|^GROQ_API_KEY=.*|GROQ_API_KEY=$val|g" .env || echo "GROQ_API_KEY=$val" >> .env
            export GROQ_API_KEY="$val"
        fi
        grep -q "^$var=" .env && sed -i "s|^$var=.*|$var=$val|g" .env || echo "$var=$val" >> .env
        export $var="$val"
    fi
done

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
