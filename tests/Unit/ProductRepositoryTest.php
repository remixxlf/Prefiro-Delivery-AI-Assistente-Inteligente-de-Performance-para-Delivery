<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Customer;
use App\Repositories\ProductRepository;

class ProductRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected ProductRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new ProductRepository();
    }

    public function test_get_top_selling_products(): void
    {
        $customer = Customer::factory()->create();
        $p1 = Product::factory()->create(['name' => 'Top 1 Product', 'price' => 30.00]);
        $p2 = Product::factory()->create(['name' => 'Top 2 Product', 'price' => 20.00]);

        $order = Order::factory()->for($customer)->delivered()->create();

        // 10 unidades de p1, 2 unidades de p2
        OrderItem::create(['order_id' => $order->id, 'product_id' => $p1->id, 'quantity' => 10, 'unit_price' => 30.00, 'subtotal' => 300.00]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $p2->id, 'quantity' => 2, 'unit_price' => 20.00, 'subtotal' => 40.00]);

        $top = $this->repo->getTopSellingProducts(5, null, null, 'quantity');

        $this->assertNotEmpty($top);
        $this->assertEquals('Top 1 Product', $top[0]['name']);
        $this->assertEquals(10, $top[0]['total_quantity']);
    }

    public function test_get_products_losing_sales(): void
    {
        $customer = Customer::factory()->create();
        $losingProduct = Product::factory()->create(['name' => 'Produto em Queda', 'category' => 'Lanches', 'price' => 25.00]);

        // Mês anterior: 10 unidades vendidas
        $prevOrder = Order::factory()->for($customer)->delivered()->create(['ordered_at' => '2026-07-10 12:00:00']);
        OrderItem::create(['order_id' => $prevOrder->id, 'product_id' => $losingProduct->id, 'quantity' => 10, 'unit_price' => 25.00, 'subtotal' => 250.00]);

        // Mês atual: apenas 2 unidades vendidas (queda de 80%)
        $currOrder = Order::factory()->for($customer)->delivered()->create(['ordered_at' => '2026-08-10 12:00:00']);
        OrderItem::create(['order_id' => $currOrder->id, 'product_id' => $losingProduct->id, 'quantity' => 2, 'unit_price' => 25.00, 'subtotal' => 50.00]);

        $losing = $this->repo->getProductsLosingSales(
            '2026-08-01 00:00:00', '2026-08-31 23:59:59',
            '2026-07-01 00:00:00', '2026-07-31 23:59:59'
        );

        $this->assertNotEmpty($losing);
        $this->assertEquals('Produto em Queda', $losing[0]['name']);
        $this->assertEquals(-8, $losing[0]['quantity_difference']);
        $this->assertEquals(-80.0, $losing[0]['quantity_change_percent']);
    }

    public function test_get_sales_by_category(): void
    {
        $customer = Customer::factory()->create();
        $pMarmita = Product::factory()->create(['category' => 'Marmitas', 'price' => 30.00]);
        $pBebida  = Product::factory()->create(['category' => 'Bebidas', 'price' => 10.00]);

        $order = Order::factory()->for($customer)->delivered()->create(['ordered_at' => '2026-08-10 12:00:00']);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $pMarmita->id, 'quantity' => 3, 'unit_price' => 30.00, 'subtotal' => 90.00]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $pBebida->id, 'quantity' => 1, 'unit_price' => 10.00, 'subtotal' => 10.00]);

        $categories = $this->repo->getSalesByCategory('2026-08-01 00:00:00', '2026-08-31 23:59:59');

        $this->assertNotEmpty($categories);
        $this->assertEquals('Marmitas', $categories[0]['category']);
        $this->assertEquals(90.00, $categories[0]['total_revenue']);
        $this->assertEquals(90.0, $categories[0]['share_percent']);
    }
}