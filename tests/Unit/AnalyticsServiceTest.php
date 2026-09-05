<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Customer;
use App\Repositories\OrderRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\ProductRepository;
use App\Services\AnalyticsService;
use Carbon\Carbon;

class AnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AnalyticsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AnalyticsService(
            new OrderRepository(),
            new CustomerRepository(),
            new ProductRepository()
        );
    }

    public function test_performance_drop_analysis_structure(): void
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['price' => 30.00]);

        $order = Order::factory()->for($customer)->delivered()->create([
            'ordered_at' => Carbon::now()->startOfMonth()->addDays(2),
            'total'      => 60.00,
        ]);

        OrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'quantity'   => 2,
            'unit_price' => 30.00,
            'subtotal'   => 60.00,
        ]);

        $analysis = $this->service->getPerformanceDropAnalysis();

        $this->assertArrayHasKey('reference_month', $analysis);
        $this->assertArrayHasKey('previous_month', $analysis);
        $this->assertArrayHasKey('financial_comparison', $analysis);
        $this->assertArrayHasKey('customer_churn', $analysis);
        $this->assertArrayHasKey('category_performance', $analysis);
        $this->assertArrayHasKey('products_losing_sales', $analysis);
        $this->assertArrayHasKey('top_selling_products', $analysis);
        $this->assertArrayHasKey('inactive_customers_30d', $analysis);
    }

    public function test_actionable_insights_data_structure(): void
    {
        $insights = $this->service->getActionableInsightsData();

        $this->assertArrayHasKey('performance_summary', $insights);
        $this->assertArrayHasKey('inactive_customers', $insights);
        $this->assertArrayHasKey('best_weekday', $insights);
        $this->assertArrayHasKey('worst_weekday', $insights);
        $this->assertArrayHasKey('peak_hour', $insights);
    }

    public function test_inactive_customers_analysis(): void
    {
        Customer::factory()->create(['last_order_at' => Carbon::now()->subDays(40)]);

        $analysis = $this->service->getInactiveCustomersAnalysis(30);

        $this->assertGreaterThanOrEqual(1, $analysis['total_inactive_count']);
        $this->assertEquals(30, $analysis['inactive_days_threshold']);
        $this->assertIsArray($analysis['sample_customers']);
    }

    public function test_dashboard_summary_structure(): void
    {
        $summary = $this->service->getDashboardSummary();

        $this->assertArrayHasKey('current_month', $summary);
        $this->assertArrayHasKey('customer_health', $summary);
        $this->assertArrayHasKey('top_products', $summary);
        $this->assertArrayHasKey('monthly_trend', $summary);
    }
}