<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a tabela de clientes da operação de delivery.
     *
     * Índices adicionados:
     *  - last_order_at  → consultas de clientes inativos (> 30 dias)
     *  - first_order_at → análise de novos clientes por período
     *  - phone          → busca por telefone (único)
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            $table->string('name', 120);
            $table->string('phone', 20)->nullable()->unique()->comment('Telefone para contato e campanhas');

            // Datas derivadas dos pedidos — atualizadas via Observer ou trigger
            $table->timestamp('first_order_at')->nullable()->comment('Data do primeiro pedido realizado');
            $table->timestamp('last_order_at')->nullable()->comment('Data do último pedido realizado');

            $table->timestamps(); // created_at / updated_at

            // ── Índices ────────────────────────────────────────────────
            // Consultas de clientes inativos (ex.: last_order_at < now() - 30 days)
            $table->index('last_order_at', 'idx_customers_last_order_at');

            // Análise de aquisição: novos clientes por período
            $table->index('first_order_at', 'idx_customers_first_order_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};