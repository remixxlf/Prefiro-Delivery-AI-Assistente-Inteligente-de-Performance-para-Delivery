<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_summary_returns_metrics_and_suggested_prompts(): void
    {
        $customer = Customer::factory()->create();
        Order::factory()->for($customer)->delivered()->create([
            'ordered_at' => Carbon::now()->subDays(2),
            'total'      => 95.00,
        ]);

        $response = $this->getJson('/api/v1/dashboard/summary');

        $response->assertStatus(200)
            ->assertJson(['status' => 'success'])
            ->assertJsonStructure([
                'status',
                'data' => [
                    'metrics' => [
                        'current_month',
                        'customer_health',
                        'top_products',
                        'monthly_trend',
                    ],
                    'suggested_prompts',
                ],
            ]);

        $this->assertNotEmpty($response->json('data.suggested_prompts'));
        $this->assertContains('Por que minhas vendas caíram este mês?', $response->json('data.suggested_prompts'));
    }
}