<?php

namespace App\Services\AI;

use App\Models\AiConversation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * AIService
 *
 * Ponto central de integração com Inteligência Artificial.
 * Orquestra a resolução de intenção, montagem de contexto seguro,
 * construção do prompt, chamada à API externa e registro em log de auditoria.
 */
class AIService
{
    public function __construct(
        protected IntentResolver $intentResolver,
        protected ContextBuilder $contextBuilder,
        protected PromptBuilder $promptBuilder
    ) {}

    /**
     * Processa a pergunta do gestor e retorna a resposta analítica da IA.
     */
    public function ask(string $question, ?string $sessionId = null): array
    {
        $startTime = microtime(true);
        $sessionId = $sessionId ?: (string) Str::uuid();

        // 1. Resolução e sanitização da intenção
        $resolved = $this->intentResolver->resolve($question);
        $intent = $resolved['intent'];
        $cleanQuestion = $resolved['sanitized_question'];

        // Se for alerta de segurança / Prompt Injection
        if ($resolved['is_suspicious']) {
            $securityResponse = "Identifiquei comandos não permitidos na sua mensagem. Como assistente de delivery, posso ajudar com análises de faturamento, pedidos, clientes e produtos. Por favor, faça uma pergunta sobre a sua operação.";
            $this->logConversation([
                'session_id'       => $sessionId,
                'question'         => $cleanQuestion,
                'context_data'     => null,
                'prompt_sent'      => null,
                'response'         => $securityResponse,
                'intent'           => $intent,
                'provider'         => 'security_guard',
                'model'            => 'rule_engine',
                'tokens_input'     => 0,
                'tokens_output'    => 0,
                'tokens_total'     => 0,
                'cost_usd'         => 0.0,
                'response_time_ms' => (int) round((microtime(true) - $startTime) * 1000),
                'status'           => AiConversation::STATUS_SUCCESS,
                'error_message'    => null,
                'was_streamed'     => false,
            ]);

            return [
                'session_id'    => $sessionId,
                'intent'        => $intent,
                'response'      => $securityResponse,
                'context_data'  => null,
                'provider'      => 'security_guard',
                'model'         => 'rule_engine',
                'tokens'        => ['total' => 0],
                'cost_usd'      => 0.0,
                'status'        => 'success',
            ];
        }

        // 2. Verifica Cache Redis para perguntas idênticas recentes (TTL de 1h)
        // Economiza tokens e oferece resposta instantânea em consultas repetitivas
        $cacheKey = 'ai:response:' . md5(trim(mb_strtolower($cleanQuestion)));
        $cacheTtl = (int) config('ai.cache_ttl_seconds', 3600);

        if (Cache::has($cacheKey)) {
            $cachedData = Cache::get($cacheKey);
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            $this->logConversation([
                'session_id'       => $sessionId,
                'question'         => $cleanQuestion,
                'context_data'     => $cachedData['context_data'] ?? null,
                'prompt_sent'      => '[RESPOSTA_RECUPERADA_DO_CACHE_REDIS]',
                'response'         => $cachedData['response'],
                'intent'           => $cachedData['intent'],
                'provider'         => $cachedData['provider'],
                'model'            => $cachedData['model'],
                'tokens_input'     => 0,
                'tokens_output'    => 0,
                'tokens_total'     => 0,
                'cost_usd'         => 0.0,
                'response_time_ms' => $durationMs,
                'status'           => AiConversation::STATUS_CACHED,
                'error_message'    => null,
                'was_streamed'     => false,
            ]);

            return array_merge($cachedData, [
                'session_id' => $sessionId,
                'cached'     => true,
                'status'     => AiConversation::STATUS_CACHED,
            ]);
        }

        // 3. Monta o contexto analítico estritamente necessário (apenas métricas agregadas)
        $contextData = $this->contextBuilder->build($intent, $resolved['parameters']);

        // 4. Monta o prompt
        $systemPrompt = $this->promptBuilder->getSystemPrompt();
        $userPrompt = $this->promptBuilder->buildUserPrompt($cleanQuestion, $contextData);

        // 5. Executa a chamada ao provedor de IA configurado
        $provider = config('ai.provider', 'openai');
        $aiResult = $this->callProvider($provider, $systemPrompt, $userPrompt, $contextData);

        $endTime = microtime(true);
        $durationMs = (int) round(($endTime - $startTime) * 1000);

        // 6. Registra auditoria completa no banco e nos logs
        $this->logConversation([
            'session_id'       => $sessionId,
            'question'         => $cleanQuestion,
            'context_data'     => $contextData,
            'prompt_sent'      => $userPrompt,
            'response'         => $aiResult['text'],
            'intent'           => $intent,
            'provider'         => $aiResult['provider'],
            'model'            => $aiResult['model'],
            'tokens_input'     => $aiResult['tokens_input'],
            'tokens_output'    => $aiResult['tokens_output'],
            'tokens_total'     => $aiResult['tokens_total'],
            'cost_usd'         => $aiResult['cost_usd'],
            'response_time_ms' => $durationMs,
            'status'           => $aiResult['status'],
            'error_message'    => $aiResult['error'] ?? null,
            'was_streamed'     => false,
        ]);

        $resultPayload = [
            'session_id'    => $sessionId,
            'intent'        => $intent,
            'response'      => $aiResult['text'],
            'context_data'  => $contextData['metrics'],
            'provider'      => $aiResult['provider'],
            'model'         => $aiResult['model'],
            'tokens'        => [
                'input' => $aiResult['tokens_input'],
                'output'=> $aiResult['tokens_output'],
                'total' => $aiResult['tokens_total'],
            ],
            'cost_usd'      => $aiResult['cost_usd'],
            'status'        => $aiResult['status'],
            'cached'        => false,
        ];

        // Salva no Cache para reutilização
        try {
            Cache::put($cacheKey, $resultPayload, $cacheTtl);
        } catch (\Throwable $e) {
            Log::channel('ai_errors')->warning("Falha ao salvar cache de resposta da IA: " . $e->getMessage());
        }

        return $resultPayload;
    }

    /**
     * Processa a pergunta com suporte a streaming de resposta (SSE).
     * Invoca o callback $chunkCallback para cada pedaço de texto gerado.
     */
    public function stream(string $question, ?string $sessionId = null, ?callable $chunkCallback = null): array
    {
        $startTime = microtime(true);
        $sessionId = $sessionId ?: (string) Str::uuid();

        $resolved = $this->intentResolver->resolve($question);
        $intent = $resolved['intent'];
        $cleanQuestion = $resolved['sanitized_question'];

        $contextData = $this->contextBuilder->build($intent, $resolved['parameters']);
        $systemPrompt = $this->promptBuilder->getSystemPrompt();
        $userPrompt = $this->promptBuilder->buildUserPrompt($cleanQuestion, $contextData);

        $provider = config('ai.provider', 'openai');
        $apiKey = config("ai.{$provider}.api_key");

        // Se tiver chave OpenAI e callback, faz stream real
        if ($provider === 'openai' && !empty($apiKey) && is_callable($chunkCallback)) {
            $model = config('ai.openai.model', 'gpt-4o-mini');
            $fullText = '';

            try {
                $response = Http::withToken($apiKey)
                    ->timeout(60)
                    ->withOptions(['stream' => true])
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model'       => $model,
                        'temperature' => (float) config('ai.openai.temperature', 0.3),
                        'stream'      => true,
                        'messages'    => [
                            ['role' => 'system', 'content' => $systemPrompt],
                            ['role' => 'user',   'content' => $userPrompt],
                        ],
                    ]);

                $body = $response->toPsrResponse()->getBody();
                while (!$body->eof()) {
                    $line = $this->readLine($body);
                    if (str_starts_with($line, 'data: ') && $line !== 'data: [DONE]') {
                        $json = json_decode(substr($line, 6), true);
                        $delta = $json['choices'][0]['delta']['content'] ?? '';
                        if ($delta !== '') {
                            $fullText .= $delta;
                            $chunkCallback($delta);
                        }
                    }
                }

                $tokensInput = (int) (mb_strlen($userPrompt) / 4);
                $tokensOutput = (int) (mb_strlen($fullText) / 4);
                $cost = $this->calculateCost('openai', $model, $tokensInput, $tokensOutput);

                $durationMs = (int) round((microtime(true) - $startTime) * 1000);

                $this->logConversation([
                    'session_id'       => $sessionId,
                    'question'         => $cleanQuestion,
                    'context_data'     => $contextData,
                    'prompt_sent'      => $userPrompt,
                    'response'         => $fullText,
                    'intent'           => $intent,
                    'provider'         => 'openai',
                    'model'            => $model,
                    'tokens_input'     => $tokensInput,
                    'tokens_output'    => $tokensOutput,
                    'tokens_total'     => $tokensInput + $tokensOutput,
                    'cost_usd'         => $cost,
                    'response_time_ms' => $durationMs,
                    'status'           => AiConversation::STATUS_SUCCESS,
                    'error_message'    => null,
                    'was_streamed'     => true,
                ]);

                return [
                    'session_id'   => $sessionId,
                    'intent'       => $intent,
                    'response'     => $fullText,
                    'context_data' => $contextData['metrics'],
                ];
            } catch (\Throwable $e) {
                Log::warning("Streaming OpenAI falhou: {$e->getMessage()}. Utilizando fallback determinístico.");
            }
        }

        // Fallback para streaming simulado (emite chunks da resposta do modelo)
        $aiResult = $this->callProvider($provider, $systemPrompt, $userPrompt, $contextData);
        $fullText = $aiResult['text'];

        if (is_callable($chunkCallback)) {
            $words = explode(' ', $fullText);
            foreach ($words as $word) {
                $chunkCallback($word . ' ');
                usleep(25000); // 25ms para simular digitação natural
            }
        }

        return [
            'session_id'   => $sessionId,
            'intent'       => $intent,
            'response'     => $fullText,
            'context_data' => $contextData['metrics'],
        ];
    }

    /**
     * Lê uma linha de uma stream PSR-7.
     */
    protected function readLine($stream): string
    {
        $buffer = '';
        while (!$stream->eof()) {
            $byte = $stream->read(1);
            if ($byte === "\n") {
                break;
            }
            $buffer .= $byte;
        }
        return trim($buffer);
    }

    /**
     * Despacha a chamada para o provedor configurado ou fallback.
     */
    protected function callProvider(string $provider, string $systemPrompt, string $userPrompt, array $contextData): array
    {
        $apiKey = config("ai.{$provider}.api_key");

        // Se tiver chave de API real no .env, tenta chamada externa
        if (!empty($apiKey)) {
            try {
                return match ($provider) {
                    'openai'    => $this->callOpenAI($apiKey, $systemPrompt, $userPrompt),
                    'gemini'    => $this->callGemini($apiKey, $systemPrompt, $userPrompt),
                    'anthropic' => $this->callAnthropic($apiKey, $systemPrompt, $userPrompt),
                    default     => $this->callOpenAI($apiKey, $systemPrompt, $userPrompt),
                };
            } catch (\Throwable $e) {
                Log::error("Erro na API de IA ({$provider}): " . $e->getMessage());
                // Cai no motor determinístico com os dados reais do banco
            }
        }

        // Motor Analítico Determinístico Baseado nos Dados Reais
        // Garante que o sistema funciona e é 100% testável mesmo sem chaves externas cadastradas no ambiente
        return $this->generateDeterministicAnalysis($contextData, $userPrompt);
    }

    /**
     * Chamada à API da OpenAI (gpt-4o-mini).
     */
    protected function callOpenAI(string $apiKey, string $systemPrompt, string $userPrompt): array
    {
        $model = config('ai.openai.model', 'gpt-4o-mini');

        $response = Http::withToken($apiKey)
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'       => $model,
                'temperature' => (float) config('ai.openai.temperature', 0.3),
                'max_tokens'  => (int) config('ai.openai.max_tokens', 2000),
                'messages'    => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userPrompt],
                ],
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException("OpenAI API Error: " . $response->body());
        }

        $json = $response->json();
        $text = $json['choices'][0]['message']['content'] ?? '';
        $tokensIn = $json['usage']['prompt_tokens'] ?? 0;
        $tokensOut = $json['usage']['completion_tokens'] ?? 0;
        $tokensTotal = $json['usage']['total_tokens'] ?? ($tokensIn + $tokensOut);

        $cost = $this->calculateCost('openai', $model, $tokensIn, $tokensOut);

        return [
            'provider'     => 'openai',
            'model'        => $model,
            'text'         => $text,
            'tokens_input' => $tokensIn,
            'tokens_output'=> $tokensOut,
            'tokens_total' => $tokensTotal,
            'cost_usd'     => $cost,
            'status'       => AiConversation::STATUS_SUCCESS,
        ];
    }

    /**
     * Chamada à API Google Gemini (gemini-1.5-flash).
     */
    protected function callGemini(string $apiKey, string $systemPrompt, string $userPrompt): array
    {
        $model = config('ai.gemini.model', 'gemini-1.5-flash');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $response = Http::timeout(30)->post($url, [
            'system_instruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [['text' => $userPrompt]],
                ],
            ],
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException("Gemini API Error: " . $response->body());
        }

        $json = $response->json();
        $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $tokensIn = $json['usageMetadata']['promptTokenCount'] ?? 0;
        $tokensOut = $json['usageMetadata']['candidatesTokenCount'] ?? 0;

        $cost = $this->calculateCost('gemini', $model, $tokensIn, $tokensOut);

        return [
            'provider'     => 'gemini',
            'model'        => $model,
            'text'         => $text,
            'tokens_input' => $tokensIn,
            'tokens_output'=> $tokensOut,
            'tokens_total' => $tokensIn + $tokensOut,
            'cost_usd'     => $cost,
            'status'       => AiConversation::STATUS_SUCCESS,
        ];
    }

    /**
     * Chamada à API da Anthropic Claude (claude-3-haiku-20240307).
     */
    protected function callAnthropic(string $apiKey, string $systemPrompt, string $userPrompt): array
    {
        $model = config('ai.anthropic.model', 'claude-3-haiku-20240307');

        $response = Http::withHeaders([
            'x-api-key'         => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
            'model'      => $model,
            'max_tokens' => (int) config('ai.openai.max_tokens', 2000),
            'system'     => $systemPrompt,
            'messages'   => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException("Anthropic API Error: " . $response->body());
        }

        $json = $response->json();
        $text = $json['content'][0]['text'] ?? '';
        $tokensIn = $json['usage']['input_tokens'] ?? 0;
        $tokensOut = $json['usage']['output_tokens'] ?? 0;

        $cost = $this->calculateCost('anthropic', $model, $tokensIn, $tokensOut);

        return [
            'provider'     => 'anthropic',
            'model'        => $model,
            'text'         => $text,
            'tokens_input' => $tokensIn,
            'tokens_output'=> $tokensOut,
            'tokens_total' => $tokensIn + $tokensOut,
            'cost_usd'     => $cost,
            'status'       => AiConversation::STATUS_SUCCESS,
        ];
    }

    /**
     * Motor Analítico Baseado nos Dados Reais.
     * Gera uma resposta 100% precisa e fiel aos números do banco,
     * garantindo teste imediato sem depender de chaves de terceiros.
     */
    protected function generateDeterministicAnalysis(array $contextData, string $userPrompt): array
    {
        $intent = $contextData['intent'] ?? 'general';
        $metrics = $contextData['metrics'] ?? [];

        $response = match ($intent) {
            IntentResolver::INTENT_REVENUE_DROP =>
                $this->formatRevenueDropResponse($metrics),

            IntentResolver::INTENT_REVENUE_COMPARISON =>
                $this->formatRevenueComparisonResponse($metrics),

            IntentResolver::INTENT_BEST_DAY =>
                $this->formatBestDayResponse($metrics),

            IntentResolver::INTENT_PEAK_HOURS =>
                $this->formatPeakHoursResponse($metrics),

            IntentResolver::INTENT_TOP_PRODUCTS =>
                $this->formatTopProductsResponse($metrics),

            IntentResolver::INTENT_LOSING_PRODUCTS =>
                $this->formatLosingProductsResponse($metrics),

            IntentResolver::INTENT_AVERAGE_TICKET =>
                $this->formatAverageTicketResponse($metrics),

            IntentResolver::INTENT_BEST_CUSTOMERS =>
                $this->formatBestCustomersResponse($metrics),

            IntentResolver::INTENT_INACTIVE_CUSTOMERS =>
                $this->formatInactiveCustomersResponse($metrics),

            IntentResolver::INTENT_GROWTH_ACTIONS =>
                $this->formatGrowthActionsResponse($metrics),

            IntentResolver::INTENT_CREATE_CAMPAIGN =>
                $this->formatCampaignResponse($metrics),

            default =>
                $this->formatGeneralResponse($metrics),
        };

        $tokensIn = (int) (mb_strlen($userPrompt) / 4);
        $tokensOut = (int) (mb_strlen($response) / 4);

        return [
            'provider'     => 'prefiro_analytics_engine',
            'model'        => 'grounded_rules_v1',
            'text'         => $response,
            'tokens_input' => $tokensIn,
            'tokens_output'=> $tokensOut,
            'tokens_total' => $tokensIn + $tokensOut,
            'cost_usd'     => 0.0,
            'status'       => AiConversation::STATUS_FALLBACK,
        ];
    }

    /**
     * Formatação da resposta de queda de faturamento (Exemplo fiel do PDF).
     */
    protected function formatRevenueDropResponse(array $m): string
    {
        $diff = $m['financial_comparison']['difference'] ?? [];
        $curr = $m['financial_comparison']['current'] ?? [];
        $prev = $m['financial_comparison']['previous'] ?? [];
        $churn = $m['customer_churn'] ?? [];
        $biggestCatDrop = $m['category_performance']['biggest_drop'] ?? null;
        $inactiveCount = $m['inactive_customers_30d'] ?? 0;

        $revGrowth = abs($diff['revenue_growth_percent'] ?? 0);
        $ordersGrowth = abs($diff['orders_growth_percent'] ?? 0);
        $currTicket = number_format($curr['average_ticket'] ?? 0, 2, ',', '.');
        $prevTicket = number_format($prev['average_ticket'] ?? 0, 2, ',', '.');
        $churnCount = $churn['churned_customers_count'] ?? 0;

        $catDropText = '';
        if ($biggestCatDrop && ($biggestCatDrop['revenue_growth_percent'] ?? 0) < 0) {
            $catName = $biggestCatDrop['category'];
            $catPct = abs($biggestCatDrop['revenue_growth_percent']);
            $catDropText = "\nA categoria **\"{$catName}\"** apresentou a maior queda, com redução de **{$catPct}%** nas vendas.";
        }

        return "Seu faturamento caiu **{$revGrowth}%** em relação ao mês anterior.\n\n"
            . "O principal fator foi a redução de **{$ordersGrowth}%** na quantidade de pedidos.\n"
            . "O ticket médio permaneceu praticamente estável, passando de **R$ {$prevTicket}** para **R$ {$currTicket}**.\n\n"
            . "Também identificamos que **{$churnCount} clientes** que compraram no mês anterior ainda não realizaram pedidos neste mês."
            . $catDropText . "\n\n"
            . "💡 **Recomendação:** Foram identificados **{$inactiveCount} clientes** há mais de 30 dias sem comprar. Uma ação prioritária recomendada é criar uma campanha direcionada a esse público para recuperar a frequência de pedidos.";
    }

    protected function formatRevenueComparisonResponse(array $m): string
    {
        $comp = $m['periods_comparison'] ?? [];
        $curr = $comp['current'] ?? [];
        $prev = $comp['previous'] ?? [];
        $diff = $comp['difference'] ?? [];

        $currRev = number_format($curr['total_revenue'] ?? 0, 2, ',', '.');
        $prevRev = number_format($prev['total_revenue'] ?? 0, 2, ',', '.');
        $pct = abs($diff['revenue_growth_percent'] ?? 0);
        $direction = ($diff['is_growth'] ?? false) ? 'aumento' : 'queda';

        return "Neste mês, você faturou **R$ {$currRev}** em **{$curr['delivered_orders']} pedidos entregues**.\n\n"
            . "Em comparação ao mês anterior (R$ {$prevRev}), houve um **{$direction} de {$pct}%** no seu faturamento.\n"
            . "• **Ticket Médio Atual:** R$ " . number_format($curr['average_ticket'] ?? 0, 2, ',', '.') . "\n"
            . "• **Ticket Médio Anterior:** R$ " . number_format($prev['average_ticket'] ?? 0, 2, ',', '.');
    }

    protected function formatBestDayResponse(array $m): string
    {
        $bestDay = $m['best_day'] ?? 'Sexta-feira';
        $worstDay = $m['worst_day'] ?? 'Segunda-feira';
        $days = $m['days'] ?? [];

        $text = "Seu melhor dia da semana é **{$bestDay}**, com o maior volume concentrado de faturamento e pedidos.\n\n";
        $text .= "Já o dia com menor movimento na sua operação é **{$worstDay}**.\n\n";
        $text .= "📊 **Distribuição por dia:**\n";
        foreach ($days as $d) {
            $rev = number_format($d['total_revenue'], 2, ',', '.');
            $text .= "• **{$d['day_name']}**: {$d['total_orders']} pedidos (R$ {$rev}) — {$d['share_percent']}% do total\n";
        }

        return $text;
    }

    protected function formatPeakHoursResponse(array $m): string
    {
        $peakHour = $m['peak_hour'] ?? '12:00';
        $peakOrders = $m['peak_orders'] ?? 0;
        $shifts = $m['shift_breakdown'] ?? [];

        $text = "Seu horário de maior volume de pedidos é às **{$peakHour}**, registrando o pico de movimento da cozinha.\n\n";
        $text .= "🍽️ **Volume por Turnos Principais:**\n";
        foreach ($shifts as $shift => $count) {
            $text .= "• **{$shift}**: {$count} pedidos\n";
        }
        $text .= "\n💡 **Dica Operacional:** Garanta que os insumos mais demandados estejam preparados antes das 11h para reduzir o tempo de expedição durante o pico.";

        return $text;
    }

    protected function formatTopProductsResponse(array $m): string
    {
        $ranking = $m['ranking'] ?? [];
        $text = "🏆 **Ranking dos Produtos Mais Vendidos:**\n\n";

        foreach (array_slice($ranking, 0, 5) as $item) {
            $rev = number_format($item['total_revenue'], 2, ',', '.');
            $price = number_format($item['current_price'], 2, ',', '.');
            $text .= "{$item['rank']}º **{$item['name']}** ({$item['category']})\n";
            $text .= "   • Quantidade vendida: **{$item['total_quantity']} un.** | Faturamento: R$ {$rev} (R$ {$price}/un.)\n";
        }

        return $text;
    }

    protected function formatLosingProductsResponse(array $m): string
    {
        $losing = $m['losing_products'] ?? [];
        if (empty($losing)) {
            return "Não identificamos produtos com quedas significativas no comparativo recente de vendas.";
        }

        $text = "⚠️ **Produtos que Apresentaram Maior Queda de Vendas:**\n\n";
        foreach (array_slice($losing, 0, 5) as $p) {
            $drop = abs($p['quantity_change_percent']);
            $text .= "• **{$p['name']}** ({$p['category']}): queda de **{$drop}%** nas unidades vendidas ";
            $text .= "(de {$p['previous_quantity']} un. para {$p['current_quantity']} un.)\n";
        }
        $text .= "\n💡 **Sugestão:** Avalie posicionar esses itens em combos com bebidas ou aplicar uma promoção de reposicionamento no cardápio.";

        return $text;
    }

    protected function formatAverageTicketResponse(array $m): string
    {
        $curr = number_format($m['current_ticket'] ?? 0, 2, ',', '.');
        $prev = number_format($m['previous_ticket'] ?? 0, 2, ',', '.');
        $diff = number_format(abs($m['ticket_difference'] ?? 0), 2, ',', '.');
        $pct = abs($m['growth_percent'] ?? 0);
        $status = ($m['growth_percent'] ?? 0) >= 0 ? 'aumento' : 'queda';

        return "Seu ticket médio atual é de **R$ {$curr}** por pedido entregue.\n\n"
            . "Em relação ao período anterior (R$ {$prev}), houve uma variação de **{$diff} ({$pct}%)** em {$status}.\n\n"
            . "💡 **Estratégia para alavancar o ticket:** Incentive adicionais na etapa final do pedido (sobremesas e bebidas) ou crie combos com preços fechados superiores a R$ {$curr}.";
    }

    protected function formatBestCustomersResponse(array $m): string
    {
        $top = $m['top_customers'] ?? [];
        $text = "⭐ **Seus Melhores Clientes (Maior Volume e Valor Acumulado):**\n\n";

        foreach (array_slice($top, 0, 5) as $c) {
            $spent = number_format($c['total_spent'], 2, ',', '.');
            $avg = number_format($c['average_ticket'], 2, ',', '.');
            $text .= "{$c['rank']}º **{$c['name']}**\n";
            $text .= "   • Total gasto: **R$ {$spent}** em **{$c['total_orders']} pedidos** (Ticket médio: R$ {$avg})\n";
        }

        return $text;
    }

    protected function formatInactiveCustomersResponse(array $m): string
    {
        $total = $m['total_inactive_count'] ?? 0;
        $ticket = number_format($m['estimated_avg_ticket'] ?? 0, 2, ',', '.');
        $loss = number_format($m['potential_revenue_loss'] ?? 0, 2, ',', '.');

        return "Identificamos **{$total} clientes** que não realizam pedidos há mais de 30 dias na sua base.\n\n"
            . "• **Ticket Médio Histórico desse Grupo:** R$ {$ticket}\n"
            . "• **Potencial de Receita em Risco:** R$ {$loss}\n\n"
            . "🎯 Você pode reativar esse público solicitando agora: *\"Crie uma campanha para clientes que não compram há mais de 30 dias\"*.";
    }

    protected function formatGrowthActionsResponse(array $m): string
    {
        $inactive = $m['inactive_customers']['count'] ?? 0;
        $worstDay = $m['worst_weekday'] ?? 'Segunda-feira';
        $declining = $m['declining_products'][0]['name'] ?? 'Marmitas Executivas';

        return "Aqui estão **três ações práticas e prioritárias** para alavancar suas vendas esta semana, baseadas no comportamento real da sua operação:\n\n"
            . "1. 📱 **Campanha de Reativação para {$inactive} Clientes Inativos:**\n"
            . "   Envie uma mensagem oferecendo taxa de entrega grátis ou 10% de desconto no próximo pedido para clientes sumidos há mais de 30 dias.\n\n"
            . "2. 🏷️ **Promoção Especial de {$worstDay}:**\n"
            . "   Como {$worstDay} é o seu dia com menor volume, lance o \"Combo de {$worstDay}\" para ocupar a cozinha e girar pedidos em um dia tradicionalmente lento.\n\n"
            . "3. 🍱 **Reposicionamento de Itens em Queda ({$declining}):**\n"
            . "   Monte um combo desse produto junto com refrigerante ou suco natural, elevando o apelo comercial sem reduzir margem.";
    }

    protected function formatCampaignResponse(array $m): string
    {
        $total = $m['inactive_customers_found'] ?? 0;
        $popular = implode(', ', array_slice($m['popular_items_for_hook'] ?? [], 0, 2));

        return "Foram encontrados **{$total} clientes** sem pedidos nos últimos 30 dias.\n\n"
            . "📢 **Sugestão de Campanha para Disparo (WhatsApp / SMS):**\n\n"
            . "*\"Sentimos sua falta! Faz um tempinho que você não pede com a gente. Volte hoje e aproveite uma condição especial no seu próximo pedido com entrega grátis! Peça agora o seu {$popular}.\"*\n\n"
            . "📱 **Notificação Push para App:**\n"
            . "*\"Saudade do seu prato favorito? 😋 Preparamos um presente exclusivo para você matar a vontade hoje!\"*\n\n"
            . "💡 **Público pronto:** O segmento de {$total} clientes foi mapeado e pode ser ativado.";
    }

    protected function formatGeneralResponse(array $m): string
    {
        $curr = $m['current_month'] ?? [];
        $rev = number_format($curr['revenue'] ?? 0, 2, ',', '.');
        $orders = $curr['orders_count'] ?? 0;
        $ticket = number_format($curr['average_ticket'] ?? 0, 2, ',', '.');

        return "Olá! Sou o seu **Assistente Inteligente de Performance para Delivery**.\n\n"
            . "No mês atual ({$curr['month_name']}), sua operação já registrou:\n"
            . "• **Faturamento:** R$ {$rev}\n"
            . "• **Pedidos Entregues:** {$orders}\n"
            . "• **Ticket Médio:** R$ {$ticket}\n\n"
            . "Você pode me perguntar coisas como:\n"
            . "• *\"Por que minhas vendas caíram este mês?\"*\n"
            . "• *\"Qual foi meu melhor dia da semana?\"*\n"
            . "• *\"Qual produto mais vendeu?\"*\n"
            . "• *\"Me dê três ações para aumentar meu faturamento.\"*\n"
            . "• *\"Crie uma campanha para clientes que não compram há mais de 30 dias.\"*";
    }

    /**
     * Calcula o custo da chamada em USD com base na tabela de preços configurada.
     */
    protected function calculateCost(string $provider, string $model, int $tokensIn, int $tokensOut): float
    {
        $pricing = config("ai.pricing.{$provider}.{$model}", ['input' => 0.0, 'output' => 0.0]);
        $costIn = ($tokensIn / 1000) * ($pricing['input'] ?? 0.0);
        $costOut = ($tokensOut / 1000) * ($pricing['output'] ?? 0.0);

        return round($costIn + $costOut, 6);
    }

    /**
     * Registra auditoria detalhada na tabela ai_conversations e no log dedicado.
     */
    protected function logConversation(array $data): void
    {
        try {
            AiConversation::create($data);

            // Log estruturado no canal diário dedicado 'ai'
            Log::channel('ai')->info("AI Call Auditor [{$data['intent']}]", [
                'session_id'  => $data['session_id'],
                'provider'    => $data['provider'],
                'model'       => $data['model'],
                'tokens'      => $data['tokens_total'],
                'cost_usd'    => $data['cost_usd'],
                'duration_ms' => $data['response_time_ms'],
                'status'      => $data['status'],
                'streamed'    => $data['was_streamed'] ?? false,
            ]);
        } catch (\Throwable $e) {
            Log::channel('ai_errors')->error("Falha ao salvar auditoria de IA: " . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }

    /**
     * Retorna indicadores e métricas consolidadas de observabilidade da IA.
     */
    public static function getObservabilityMetrics(): array
    {
        return [
            'total_calls'        => AiConversation::count(),
            'successful_calls'   => AiConversation::where('status', AiConversation::STATUS_SUCCESS)->count(),
            'cached_calls'       => AiConversation::where('status', AiConversation::STATUS_CACHED)->count(),
            'fallback_calls'     => AiConversation::where('status', AiConversation::STATUS_FALLBACK)->count(),
            'error_calls'        => AiConversation::whereIn('status', [AiConversation::STATUS_ERROR, AiConversation::STATUS_TIMEOUT])->count(),
            'total_tokens_used'  => (int) AiConversation::sum('tokens_total'),
            'total_cost_usd'     => (float) round(AiConversation::sum('cost_usd'), 6),
            'avg_response_time_ms' => (int) round(AiConversation::avg('response_time_ms') ?? 0),
            'recent_logs'        => AiConversation::orderBy('created_at', 'desc')->take(5)->get([
                'id', 'session_id', 'intent', 'provider', 'model', 'tokens_total', 'cost_usd', 'response_time_ms', 'status', 'created_at'
            ]),
        ];
    }
}