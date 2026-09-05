<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Order;
use App\Models\Customer;
use App\Repositories\CustomerRepository;
use Carbon\Carbon;

class CustomerRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected CustomerRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new CustomerRepository();
    }

    public function test_get_top_customers_returns_highest_spenders(): void
    {
        $topClient = Customer::factory()->create(['name' => 'Cliente VIP']);
        $normalClient = Customer::factory()->create(['name' => 'Cliente Normal']);

        Order::factory()->for($topClient)->delivered()->create(['total' => 500.00]);
        Order::factory()->for($normalClient)->delivered()->create(['total' => 50.00]);

        $top = $this->repo->getTopCustomers(5);

        $this->assertNotEmpty($top);
        $this->assertEquals('Cliente VIP', $top[0]['name']);
        $this->assertEquals(500.00, $top[0]['total_spent']);
    }

    public function test_get_inactive_customers_identifies_over_30_days(): void
    {
        $inactive = Customer::factory()->create([
            'last_order_at' => Carbon::now()->subDays(45),
        ]);

        $active = Customer::factory()->create([
            'last_order_at' => Carbon::now()->subDays(5),
        ]);

        $inactives = $this->repo->getInactiveCustomers(30);

        $ids = array_column($inactives, 'customer_id');
        $this->assertContains($inactive->id, $ids);
        $this->assertNotContains($active->id, $ids);
    }

    public function test_get_customers_bought_in_previous_but_not_in_current(): void
    {
        $churnedCustomer = Customer::factory()->create(['name' => 'Cliente Churn']);
        $loyalCustomer = Customer::factory()->create(['name' => 'Cliente Fiel']);

        // Ambos compraram no mês anterior (julho)
        Order::factory()->for($churnedCustomer)->delivered()->create([
            'ordered_at' => '2026-07-15 12:00:00',
            'total'      => 80.00,
        ]);
        Order::factory()->for($loyalCustomer)->delivered()->create([
            'ordered_at' => '2026-07-15 12:00:00',
            'total'      => 80.00,
        ]);

        // Apenas o fiel comprou no mês atual (agosto)
        Order::factory()->for($loyalCustomer)->delivered()->create([
            'ordered_at' => '2026-08-15 12:00:00',
            'total'      => 80.00,
        ]);

        $result = $this->repo->getCustomersBoughtInPreviousButNotInCurrent(
            '2026-08-01 00:00:00', '2026-08-31 23:59:59',
            '2026-07-01 00:00:00', '2026-07-31 23:59:59'
        );

        $this->assertEquals(1, $result['churned_customers_count']);
        $this->assertEquals(80.00, $result['estimated_lost_revenue']);
    }

    public function test_customer_health_segmentation_breaks_down_into_4_categories(): void
    {
        Customer::factory()->create(['last_order_at' => Carbon::now()->subDays(5)]);   // Ativo
        Customer::factory()->create(['last_order_at' => Carbon::now()->subDays(20)]);  // Em risco
        Customer::factory()->create(['last_order_at' => Carbon::now()->subDays(40)]);  // Inativo
        Customer::factory()->create(['last_order_at' => Carbon::now()->subDays(100)]); // Perdido

        $seg = $this->repo->getCustomerHealthSegmentation();

        $this->assertEquals(4, $seg['total_customers']);
        $this->assertCount(4, $seg['segments']);
    }
}