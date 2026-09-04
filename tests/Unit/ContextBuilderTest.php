<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\AI\ContextBuilder;
use App\Services\AI\IntentResolver;
use App\Services\AnalyticsService;
use App\Repositories\OrderRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\ProductRepository;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;

class ContextBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected ContextBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new ContextBuilder(
            new AnalyticsService(new OrderRepository(), new CustomerRepository(), new ProductRepository()),
            new OrderRepository(),
            new CustomerRepository(),
            new ProductRepository()
        );
    }

    public function test_builds_revenue_drop_context_payload(): void
    {
        $customer = Customer::factory()->create();
        Order::factory()->for($customer)->delivered()->create(['ordered_at' => Carbon::now()->subDays(5)]);

        $context = $this->builder->build(IntentResolver::INTENT_REVENUE_DROP);

        $this->assertEquals(IntentResolver::INTENT_REVENUE_DROP, $context['intent']);
        $this->assertEquals('BRL (R$)', $context['currency']);
        $this->assertArrayHasKey('financial_comparison', $context['metrics']);
        $this->assertArrayHasKey('customer_churn', $context['metrics']);
        $this->assertArrayHasKey('category_performance', $context['metrics']);
    }

    public function test_builds_campaign_context_with_inactives_and_hooks(): void
    {
        Customer::factory()->create(['last_order_at' => Carbon::now()->subDays(40)]);
        Product::factory()->create(['name' => 'Marmita Especial']);

        $context = $this->builder->build(IntentResolver::INTENT_CREATE_CAMPAIGN, ['days' => 30]);

        $this->assertEquals(IntentResolver::INTENT_CREATE_CAMPAIGN, $context['intent']);
        $this->assertArrayHasKey('inactive_customers_found', $context['metrics']);
        $this->assertArrayHasKey('popular_items_for_hook', $context['metrics']);
    }

    public function test_context_metadata_includes_integrity_statement(): void
    {
        $context = $this->builder->build(IntentResolver::INTENT_BEST_DAY);

        $this->assertArrayHasKey('timestamp', $context);
        $this->assertArrayHasKey('data_integrity', $context);
        $this->assertStringContainsString('banco de dados', $context['data_integrity']);
    }
}