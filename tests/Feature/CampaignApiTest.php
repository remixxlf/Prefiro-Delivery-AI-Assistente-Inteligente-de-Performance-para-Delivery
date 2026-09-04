<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Customer;
use Carbon\Carbon;

class CampaignApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_campaign_identifies_inactives_and_creates_content(): void
    {
        // Cria clientes inativos (> 30 dias)
        Customer::factory()->count(5)->create([
            'last_order_at' => Carbon::now()->subDays(45),
        ]);

        $response = $this->postJson('/api/v1/campaigns', [
            'days' => 30,
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'success'])
            ->assertJsonStructure([
                'status',
                'data' => [
                    'audience' => [
                        'label',
                        'days_threshold',
                        'total_customers',
                        'average_historical_ticket',
                        'potential_recoverable_value',
                        'sample_recipients',
                        'audience_status',
                    ],
                    'campaign' => [
                        'goal',
                        'generated_text',
                        'provider',
                        'model',
                    ],
                    'session_id',
                ],
            ]);

        $this->assertGreaterThanOrEqual(5, $response->json('data.audience.total_customers'));
    }

    public function test_generate_campaign_validates_days(): void
    {
        $response = $this->postJson('/api/v1/campaigns', [
            'days' => 9999, // Excede max 365
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['days']);
    }
}