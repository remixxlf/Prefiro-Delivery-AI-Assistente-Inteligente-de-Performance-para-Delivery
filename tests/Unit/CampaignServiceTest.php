<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\CampaignService;
use App\Services\AnalyticsService;
use App\Services\AI\AIService;
use App\Services\AI\IntentResolver;
use App\Services\AI\ContextBuilder;
use App\Services\AI\PromptBuilder;
use App\Repositories\OrderRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\ProductRepository;
use App\Models\Customer;
use Carbon\Carbon;

class CampaignServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CampaignService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        $orderRepo = new OrderRepository();
        $customerRepo = new CustomerRepository();
        $productRepo = new ProductRepository();
        
        $analytics = new AnalyticsService($orderRepo, $customerRepo, $productRepo);
        $contextBuilder = new ContextBuilder($analytics, $orderRepo, $customerRepo, $productRepo);
        $aiService = new AIService(new IntentResolver(), $contextBuilder, new PromptBuilder());
        
        $this->service = new CampaignService($analytics, $aiService);
    }

    public function test_generate_inactive_customers_campaign_returns_structured_data(): void
    {
        // Cria 3 clientes inativos
        Customer::factory()->count(3)->create([
            'last_order_at' => Carbon::now()->subDays(35),
        ]);

        $result = $this->service->generateInactiveCustomersCampaign(30);

        $this->assertArrayHasKey('audience', $result);
        $this->assertEquals(3, $result['audience']['total_customers']);
        
        $this->assertArrayHasKey('campaign', $result);
        $this->assertNotEmpty($result['campaign']['generated_text']);
        
        $this->assertArrayHasKey('session_id', $result);
    }
}