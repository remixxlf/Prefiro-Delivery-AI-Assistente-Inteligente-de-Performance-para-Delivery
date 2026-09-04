<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a tabela de itens dos pedidos (linha a linha do pedido).
     *
     * Relação: orders 1 → N order_items N → 1 products
     *
     * unit_price é armazenado no momento do pedido (snapshot),
     * garantindo que mudanças de preço futuras não afetam histórico.
     *
     * Fórmula: subtotal = quantity * unit_price
     *
     * Índices adicionados:
     *  - order_id   → recuperar todos os itens de um pedido
     *  - product_id → análises de vendas por produto
     *  - Composto (product_id, order_id) → ranking de produtos por pedido
     */
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            // ── Relacionamentos ────────────────────────────────────────
            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete()
                ->comment('Pedido ao qual este item pertence');

            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete()
                ->comment('Produto vendido — restrict: histórico protegido');

            // ── Dados do item ──────────────────────────────────────────
            $table->unsignedSmallInteger('quantity')
                ->comment('Quantidade de unidades deste produto no pedido');

            // Snapshot do preço no momento da venda
            $table->decimal('unit_price', 10, 2)
                ->comment('Preço unitário no momento da venda (snapshot imutável)');

            $table->decimal('subtotal', 10, 2)
                ->comment('Subtotal do item: quantity * unit_price');

            // ── Índices ────────────────────────────────────────────────
            // Recuperar todos os itens de um pedido (eager loading frequente)
            $table->index('order_id', 'idx_order_items_order_id');

            // Análises de vendas por produto (ranking, tendências)
            $table->index('product_id', 'idx_order_items_product_id');

            // Composto: análise de quantas vezes um produto aparece em pedidos
            $table->index(['product_id', 'order_id'], 'idx_order_items_product_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};