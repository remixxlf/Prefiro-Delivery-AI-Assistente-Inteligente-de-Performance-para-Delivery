<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * DatabaseSeeder — Orquestrador principal de todos os seeders.
 *
 * Ordem de execução:
 *  1. DeliverySeeder → cria produtos, clientes e pedidos com sazonalidade
 *
 * Para resetar e popular do zero:
 *   php artisan migrate:fresh --seed
 *
 * Para popular sem resetar (adiciona dados):
 *   php artisan db:seed
 *
 * Para rodar apenas um seeder específico:
 *   php artisan db:seed --class=DeliverySeeder
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DeliverySeeder::class,
        ]);
    }
}