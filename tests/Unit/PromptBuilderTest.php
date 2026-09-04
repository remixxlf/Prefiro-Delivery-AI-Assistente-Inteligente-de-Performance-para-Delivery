<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\AI\PromptBuilder;

class PromptBuilderTest extends TestCase
{
    protected PromptBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new PromptBuilder();
    }

    public function test_system_prompt_contains_strict_hallucination_rules(): void
    {
        $sys = $this->builder->getSystemPrompt();

        $this->assertStringContainsString('NÃO INVENTE NÚMEROS', $sys);
        $this->assertStringContainsString('JAMAIS deve inventar', $sys);
        $this->assertStringContainsString('Prefiro Delivery', $sys);
        $this->assertStringContainsString('R$', $sys);
    }

    public function test_build_user_prompt_embeds_json_context(): void
    {
        $question = 'Por que minhas vendas caíram?';
        $context = [
            'revenue_current' => 15000.50,
            'drop_percent'    => 14.2,
        ];

        $prompt = $this->builder->buildUserPrompt($question, $context);

        $this->assertStringContainsString('Por que minhas vendas caíram?', $prompt);
        $this->assertStringContainsString('15000.5', $prompt);
        $this->assertStringContainsString('14.2', $prompt);
        $this->assertStringContainsString('CONTEXTO DE DADOS REAIS', $prompt);
    }

    public function test_build_campaign_prompt_contains_guidelines(): void
    {
        $campaignContext = [
            'inactive_count' => 83,
        ];

        $prompt = $this->builder->buildCampaignPrompt($campaignContext);

        $this->assertStringContainsString('WhatsApp', $prompt);
        $this->assertStringContainsString('Notificação Push', $prompt);
        $this->assertStringContainsString('83', $prompt);
    }
}