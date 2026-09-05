<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use App\Models\Customer;
use App\Models\Order;
use App\Models\AiConversation;
use Carbon\Carbon;

class SecurityLogsAndCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_identical_questions_are_served_from_cache(): void
    {
        $customer = Customer::factory()->create();
        Order::factory()->for($customer)->delivered()->create([
            'ordered_at' => Carbon::now()->subDays(2),
            'total'      => 80.00,
        ]);

        $question = 'Qual meu ticket médio?';

        // 1ª Chamada: Executa e armazena em cache
        $response1 = $this->postJson('/api/v1/chat', [
            'question' => $question,
        ]);

        $response1->assertStatus(200)
            ->assertJson(['status' => 'success']);

        // 2ª Chamada: Deve ser servida diretamente do cache
        $response2 = $this->postJson('/api/v1/chat', [
            'question' => $question,
        ]);

        $response2->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        // Verifica se a segunda chamada foi registrada com status 'cached' e custo zero
        $this->assertDatabaseHas('ai_conversations', [
            'status'   => AiConversation::STATUS_CACHED,
            'cost_usd' => 0.0,
        ]);
    }

    public function test_observability_endpoint_returns_metrics(): void
    {
        // Cria uma conversa auditada
        AiConversation::create([
            'session_id'       => 'obs-session-1',
            'question'         => 'Qual foi meu melhor dia?',
            'response'         => 'Sexta-feira',
            'intent'           => 'best_day',
            'provider'         => 'openai',
            'model'            => 'gpt-4o-mini',
            'tokens_input'     => 100,
            'tokens_output'    => 50,
            'tokens_total'     => 150,
            'cost_usd'         => 0.000045,
            'response_time_ms' => 250,
            'status'           => 'success',
        ]);

        $response = $this->getJson('/api/v1/dashboard/ai-observability');

        $response->assertStatus(200)
            ->assertJson(['status' => 'success'])
            ->assertJsonStructure([
                'status',
                'data' => [
                    'total_calls',
                    'successful_calls',
                    'cached_calls',
                    'fallback_calls',
                    'total_tokens_used',
                    'total_cost_usd',
                    'avg_response_time_ms',
                    'recent_logs',
                ],
            ]);

        $this->assertEquals(1, $response->json('data.total_calls'));
        $this->assertEquals(150, $response->json('data.total_tokens_used'));
    }

    public function test_api_does_not_expose_stack_traces_on_invalid_requests(): void
    {
        // Endpoint inexistente na API deve retornar 404 seguro em JSON
        $response = $this->getJson('/api/v1/non-existent-endpoint');

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
            ]);

        // Não deve conter detalhes de arquivo nem stack traces
        $this->assertStringNotContainsString('Stack trace:', $response->getContent());
        $this->assertStringNotContainsString('vendor/laravel', $response->getContent());
    }
}