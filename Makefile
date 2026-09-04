.PHONY: up down build shell artisan migrate seed fresh logs test

## Subir containers
up:
	docker compose up -d

## Derrubar containers
down:
	docker compose down

## Build + subir
build:
	docker compose up -d --build

## Shell no container app
shell:
	docker compose exec app bash

## Executar artisan
artisan:
	docker compose exec app php artisan $(cmd)

## Executar migrations
migrate:
	docker compose exec app php artisan migrate

## Executar seeders
seed:
	docker compose exec app php artisan db:seed

## Migrations + seed do zero
fresh:
	docker compose exec app php artisan migrate:fresh --seed

## Ver logs do container app
logs:
	docker compose logs -f app

## Rodar testes
test:
	docker compose exec app php artisan test

## Instalar dependências PHP
composer-install:
	docker compose exec app composer install

## Instalar dependências JS
npm-install:
	docker compose exec app npm install

## Compilar assets
npm-build:
	docker compose exec app npm run build

## Inicializar projeto (primeira vez)
setup:
	cp .env.example .env
	docker compose up -d --build
	docker compose exec app composer install
	docker compose exec app php artisan key:generate
	docker compose exec app php artisan migrate:fresh --seed
	docker compose exec app npm install
	docker compose exec app npm run build
	@echo ""
	@echo "✅ Projeto rodando em http://localhost:8000"