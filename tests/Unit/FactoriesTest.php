<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;

/**
 * Testes unitários das Factories.
 * Usa SQLite in-memory para velocidade.
 */
class FactoriesTest extends TestCase
{
    use RefreshDatabase;

    // ── CustomerFactory ────────────────────────────────────────────────

    public function test_customer_factory_creates_valid_customer(): void
    {
        $customer = Customer::factory()->create();

        $this->assertNotEmpty($customer->name);
        $this->assertNotEmpty($customer->phone);
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    public function test_customer_factory_inactive_state(): void
    {
        $customer = Customer::factory()->inactive()->create();

        $this->assertNotNull($customer->last_order_at);
        $diff = Carbon::now()->diffInDays(Carbon::parse($customer->last_order_at));
        $this->assertGreaterThanOrEqual(31, $diff);
        $this->assertLessThanOrEqual(90, $diff);
    }

    // ── ProductFactory ─────────────────────────────────────────────────

    public function test_product_factory_creates_valid_product(): void
    {
        $product = Product::factory()->create();

        $this->assertNotEmpty($product->name);
        $this->assertNotEmpty($product->category);
        $this->assertGreaterThan(0, (float) $product->price);
        $this->assertTrue($product->is_active);
    }

    public function test_product_factory_inactive_state(): void
    {
        $product = Product::factory()->inactive()->create();
        $this->assertFalse($product->is_active);
    }

    public function test_product_factory_of_category(): void
    {
        $product = Product::factory()->ofCategory('Bebidas')->create();
        $this->assertEquals('Bebidas', $product->category);
    }

    // ── OrderFactory ───────────────────────────────────────────────────

    public function test_order_factory_creates_valid_order(): void
    {
        $customer = Customer::factory()->create();
        $order    = Order::factory()->for($customer)->delivered()->create();

        $this->assertEquals($customer->id, $order->customer_id);
        $this->assertEquals('delivered', $order->status);
        $this->assertNotEmpty($order->ordered_at);
    }

    public function test_order_factory_cancelled_state(): void
    {
        $order = Order::factory()->cancelled()->create();
        $this->assertEquals('cancelled', $order->status);
    }

    public function test_order_discount_is_non_negative(): void
    {
        $order = Order::factory()->create();
        $this->assertGreaterThanOrEqual(0, (float) $order->discount);
    }

    public function test_order_delivery_fee_is_positive(): void
    {
        $order = Order::factory()->create();
        $this->assertGreaterThan(0, (float) $order->delivery_fee);
    }

    // ── OrderItemFactory ───────────────────────────────────────────────

    public function test_order_item_factory_calculates_subtotal(): void
    {
        Product::factory()->create(['price' => 25.90]);
        $item = OrderItem::factory()->create();

        $expected = $item->quantity * $item->unit_price;
        $this->assertEquals(round($expected, 2), round((float) $item->subtotal, 2));
    }

    // ── Relacionamentos ────────────────────────────────────────────────

    public function test_order_belongs_to_customer(): void
    {
        $customer = Customer::factory()->create();
        $order    = Order::factory()->for($customer)->create();

        $this->assertEquals($customer->id, $order->customer->id);
    }

    public function test_order_item_belongs_to_order_and_product(): void
    {
        $product = Product::factory()->create();
        $order   = Order::factory()->create();
        $item    = OrderItem::factory()->for($order)->forProduct($product)->create();

        $this->assertEquals($order->id, $item->order->id);
        $this->assertEquals($product->id, $item->product->id);
    }
}