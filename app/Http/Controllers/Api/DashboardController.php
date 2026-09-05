<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * DashboardController
 *
 * Fornece métricas executivas consolidadas para alimentar o painel
 * e os cards visuais da aplicação frontend em Vue.js.
 */
class DashboardController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService
    ) {}

    /**
     * Retorna o resumo executivo do dashboard e sugestões de perguntas para IA.
     * GET /api/v1/dashboard/summary
     */
    public function summary(): JsonResponse
    {
        try {
            $summary = $this->analyticsService->getDashboardSummary();

            // Sugestões inteligentes de perguntas para o gestor explorar com a IA
            $suggestedPrompts = [
                'Por que minhas vendas caíram este mês?',
                'Meu faturamento aumentou ou diminuiu?',
                'Qual foi meu melhor dia da semana?',
                'Qual horário possui maior volume de pedidos?',
                'Qual produto mais vendeu?',
                'Quais produtos estão perdendo vendas?',
                'Qual meu ticket médio?',
                'Quais são meus melhores clientes?',
                'Quais clientes não compram há mais de 30 dias?',
                'Me dê três ações que eu poderia executar esta semana para aumentar minhas vendas.',
                'Crie uma campanha para clientes que não compram há mais de 30 dias.',
            ];

            return response()->json([
                'status' => 'success',
                'data'   => [
                    'metrics'           => $summary,
                    'suggested_prompts' => $suggestedPrompts,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error("DashboardController@summary falhou: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Falha ao carregar métricas do dashboard: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(),
            ], 500);
        }
    }

    /**
     * Retorna indicadores e métricas consolidadas de observabilidade e auditoria da IA.
     * GET /api/v1/dashboard/ai-observability
     */
    public function observability(): JsonResponse
    {
        try {
            $metrics = \App\Services\AI\AIService::getObservabilityMetrics();

            $groqKey = config('ai.groq.api_key') ?: getenv('GROQ_API_KEY') ?: getenv('GROK_API_KEY');
            $geminiKey = config('ai.gemini.api_key') ?: getenv('GEMINI_API_KEY') ?: getenv('GOOGLE_API_KEY');
            $openaiKey = config('ai.openai.api_key') ?: getenv('OPENAI_API_KEY');

            $diagnostics = [
                'resolved_provider' => app(\App\Services\AI\AIService::class)->resolveProvider(),
                'has_groq_key'      => !empty($groqKey),
                'groq_key_prefix'   => !empty($groqKey) ? substr($groqKey, 0, 7) . '...' : null,
                'has_gemini_key'    => !empty($geminiKey),
                'has_openai_key'    => !empty($openaiKey),
                'groq_model'        => config('ai.groq.model'),
            ];

            return response()->json([
                'status' => 'success',
                'data'   => array_merge($metrics, ['diagnostics' => $diagnostics]),
            ]);
        } catch (\Throwable $e) {
            Log::channel('ai_errors')->error("DashboardController@observability falhou: {$e->getMessage()}");

            return response()->json([
                'status'  => 'error',
                'message' => 'Falha ao consultar métricas de observabilidade da IA.',
            ], 500);
        }
    }
}