<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Order;
use App\Models\Customer;
use App\Repositories\OrderRepository;
use Carbon\Carbon;

class OrderRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected OrderRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new OrderRepository();
    }

    public function test_get_revenue_by_period_only_counts_delivered_orders(): void
    {
        $customer = Customer::factory()->create();

        // Pedido entregue no período
        Order::factory()->for($customer)->delivered()->create([
            'ordered_at' => '2026-08-10 12:00:00',
            'total'      => 100.00,
        ]);

        // Pedido cancelado no período (não deve somar)
        Order::factory()->for($customer)->cancelled()->create([
            'ordered_at' => '2026-08-12 12:00:00',
            'total'      => 50.00,
        ]);

        // Pedido entregue fora do período (não deve somar)
        Order::factory()->for($customer)->delivered()->create([
            'ordered_at' => '2026-07-10 12:00:00',
            'total'      => 80.00,
        ]);

        $revenue = $this->repo->getRevenueByPeriod('2026-08-01 00:00:00', '2026-08-31 23:59:59');

        $this->assertEquals(100.00, $revenue);
    }

    public function test_get_average_ticket(): void
    {
        $customer = Customer::factory()->create();

        Order::factory()->for($customer)->delivered()->create([
            'ordered_at' => '2026-08-10 12:00:00',
            'total'      => 60.00,
        ]);

        Order::factory()->for($customer)->delivered()->create([
            'ordered_at' => '2026-08-11 12:00:00',
            'total'      => 80.00,
        ]);

        $avg = $this->repo->getAverageTicket('2026-08-01 00:00:00', '2026-08-31 23:59:59');

        $this->assertEquals(70.00, $avg);
    }

    public function test_compare_periods_calculates_growth_and_differences(): void
    {
        $customer = Customer::factory()->create();

        // Mês anterior: R$ 200 em 2 pedidos (ticket 100)
        Order::factory()->for($customer)->delivered()->create([
            'ordered_at' => '2026-07-10 12:00:00',
            'total'      => 100.00,
        ]);
        Order::factory()->for($customer)->delivered()->create([
            'ordered_at' => '2026-07-15 12:00:00',
            'total'      => 100.00,
        ]);

        // Mês atual: R$ 300 em 2 pedidos (ticket 150)
        Order::factory()->for($customer)->delivered()->create([
            'ordered_at' => '2026-08-10 12:00:00',
            'total'      => 150.00,
        ]);
        Order::factory()->for($customer)->delivered()->create([
            'ordered_at' => '2026-08-15 12:00:00',
            'total'      => 150.00,
        ]);

        $comparison = $this->repo->comparePeriods(
            '2026-08-01 00:00:00', '2026-08-31 23:59:59',
            '2026-07-01 00:00:00', '2026-07-31 23:59:59'
        );

        $this->assertEquals(300.00, $comparison['current']['total_revenue']);
        $this->assertEquals(200.00, $comparison['previous']['total_revenue']);
        $this->assertEquals(100.00, $comparison['difference']['revenue_diff']);
        $this->assertEquals(50.00, $comparison['difference']['revenue_growth_percent']); // +50%
        $this->assertTrue($comparison['difference']['is_growth']);
    }

    public function test_get_revenue_and_orders_by_day_of_week(): void
    {
        $customer = Customer::factory()->create();

        // 2026-08-07 é uma Sexta-feira
        Order::factory()->for($customer)->delivered()->create([
            'ordered_at' => '2026-08-07 19:00:00',
            'total'      => 120.00,
        ]);

        $result = $this->repo->getRevenueAndOrdersByDayOfWeek('2026-08-01 00:00:00', '2026-08-31 23:59:59');

        $this->assertCount(7, $result['days']);
        $this->assertEquals('Sexta-feira', $result['best_day']);
    }

    public function test_get_orders_by_hour_of_day(): void
    {
        $customer = Customer::factory()->create();

        Order::factory()->for($customer)->delivered()->create([
            'ordered_at' => '2026-08-07 12:30:00',
            'total'      => 75.00,
        ]);

        $result = $this->repo->getOrdersByHourOfDay('2026-08-01 00:00:00', '2026-08-31 23:59:59');

        $this->assertEquals('12:00', $result['peak_hour']);
        $this->assertGreaterThan(0, $result['shift_breakdown']['Almoço (11h-14h)']);
    }
}