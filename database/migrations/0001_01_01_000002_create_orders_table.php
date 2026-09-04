<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a tabela de pedidos — entidade central da operação de delivery.
     *
     * Colunas financeiras usam DECIMAL(10,2) para precisão monetária.
     *
     * Fórmula: total = (subtotal_itens - discount) + delivery_fee
     *
     * Índices críticos (consultados pela IA em todas as análises):
     *  - ordered_at     → filtros por período (dia, semana, mês, ano)
     *  - status         → separar pedidos entregues de cancelados
     *  - customer_id    → análise por cliente (recorrência, ticket médio)
     *  - Composto: (ordered_at, status) → query mais comum: pedidos entregues no período
     *  - Composto: (customer_id, ordered_at) → histórico do cliente por data
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // ── Relacionamentos ────────────────────────────────────────
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete()
                ->comment('Cliente que realizou o pedido');

            // ── Dados temporais ────────────────────────────────────────
            $table->timestamp('ordered_at')->comment(
                'Data e horário exato do pedido (campo "datetime" do spec)'
            );

            // ── Status do pedido ────────────────────────────────────────
            $table->enum('status', [
                'pending',      // aguardando confirmação
                'confirmed',    // confirmado pelo estabelecimento
                'preparing',    // em preparo
                'out_for_delivery', // saiu para entrega
                'delivered',    // entregue com sucesso
                'cancelled',    // cancelado
            ])->default('pending')->comment('Status atual do pedido');

            // ── Dados financeiros ──────────────────────────────────────
            $table->decimal('discount', 10, 2)->default(0.00)
                ->comment('Valor de desconto aplicado ao pedido (BRL)');

            $table->decimal('delivery_fee', 10, 2)->default(0.00)
                ->comment('Taxa de entrega cobrada (BRL)');

            $table->decimal('total', 10, 2)
                ->comment('Valor total do pedido: (subtotal - discount) + delivery_fee (BRL)');

            // ── Metadados complementares ───────────────────────────────
            $table->string('payment_method', 50)->nullable()
                ->comment('Forma de pagamento. Ex: pix, cartao_credito, dinheiro');

            $table->text('notes')->nullable()
                ->comment('Observações do pedido (opcional)');

            $table->timestamps();

            // ── Índices simples ────────────────────────────────────────
            // Filtros por período temporal (uso mais frequente de todas as queries)
            $table->index('ordered_at', 'idx_orders_ordered_at');

            // Filtro por status (excluir cancelados das análises de faturamento)
            $table->index('status', 'idx_orders_status');

            // Análise por cliente
            $table->index('customer_id', 'idx_orders_customer_id');

            // ── Índices compostos (alta performance) ───────────────────
            // Query mais comum: "pedidos entregues em um período"
            $table->index(['ordered_at', 'status'], 'idx_orders_period_status');

            // Histórico do cliente ordenado por data
            $table->index(['customer_id', 'ordered_at'], 'idx_orders_customer_period');

            // Análise por método de pagamento por período
            $table->index(['payment_method', 'ordered_at'], 'idx_orders_payment_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};