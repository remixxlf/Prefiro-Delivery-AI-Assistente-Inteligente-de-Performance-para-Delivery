<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a tabela de produtos do cardápio.
     *
     * Índices adicionados:
     *  - category → agrupamento por categoria nas análises
     *  - price    → ordenação e filtragem por faixa de preço
     *  - name     → busca por nome (fulltext possível futuramente)
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->string('name', 150)->comment('Nome do produto no cardápio');

            $table->string('category', 80)->comment(
                'Categoria do produto. Ex: Marmitas, Bebidas, Sobremesas, Combos, Lanches'
            );

            $table->decimal('price', 10, 2)->comment('Preço unitário de venda em BRL');

            // Permite desativar produto sem deletar histórico
            $table->boolean('is_active')->default(true)->comment('Produto disponível no cardápio');

            $table->timestamps();

            // ── Índices ────────────────────────────────────────────────
            // Análises por categoria (ex.: qual categoria mais vende?)
            $table->index('category', 'idx_products_category');

            // Filtros por faixa de preço
            $table->index('price', 'idx_products_price');

            // Filtro de ativos (evita retornar produtos inativos)
            $table->index('is_active', 'idx_products_is_active');

            // Índice composto: categoria + ativo (query frequente)
            $table->index(['category', 'is_active'], 'idx_products_category_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};