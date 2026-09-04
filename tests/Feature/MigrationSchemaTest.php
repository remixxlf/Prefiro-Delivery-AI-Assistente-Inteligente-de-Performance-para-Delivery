<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/**
 * Testa se todas as tabelas e colunas críticas
 * foram criadas corretamente pelas migrations.
 *
 * Roda em SQLite in-memory para velocidade.
 */
class MigrationSchemaTest extends TestCase
{
    use RefreshDatabase;

    // ── customers ──────────────────────────────────────────────────────

    public function test_customers_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('customers'));
    }

    public function test_customers_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('customers', [
            'id', 'name', 'phone', 'first_order_at', 'last_order_at',
            'created_at', 'updated_at',
        ]));
    }

    // ── products ───────────────────────────────────────────────────────

    public function test_products_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('products'));
    }

    public function test_products_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('products', [
            'id', 'name', 'category', 'price', 'is_active',
            'created_at', 'updated_at',
        ]));
    }

    // ── orders ─────────────────────────────────────────────────────────

    public function test_orders_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('orders'));
    }

    public function test_orders_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('orders', [
            'id', 'customer_id', 'ordered_at', 'status',
            'discount', 'delivery_fee', 'total',
            'payment_method', 'notes',
            'created_at', 'updated_at',
        ]));
    }

    // ── order_items ────────────────────────────────────────────────────

    public function test_order_items_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('order_items'));
    }

    public function test_order_items_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('order_items', [
            'id', 'order_id', 'product_id', 'quantity', 'unit_price', 'subtotal',
        ]));
    }

    // ── ai_conversations ───────────────────────────────────────────────

    public function test_ai_conversations_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('ai_conversations'));
    }

    public function test_ai_conversations_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('ai_conversations', [
            'id', 'session_id', 'question', 'context_data',
            'prompt_sent', 'response', 'intent',
            'provider', 'model',
            'tokens_input', 'tokens_output', 'tokens_total',
            'cost_usd', 'response_time_ms',
            'status', 'error_message', 'was_streamed',
            'created_at', 'updated_at',
        ]));
    }
}