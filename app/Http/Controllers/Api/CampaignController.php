<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateCampaignRequest;
use App\Services\AI\AIService;
use App\Services\AnalyticsService;
use App\Repositories\CustomerRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * CampaignController
 *
 * Ação prática com Inteligência Artificial:
 * 1. Identifica clientes inativos no banco de dados
 * 2. Mensura a quantidade exata encontrada e ticket histórico
 * 3. Simula a criação da audiência / segmento
 * 4. Utiliza a IA para redigir a campanha personalizada
 */
class CampaignController extends Controller
{
    public function __construct(
        protected AIService $aiService,
        protected AnalyticsService $analyticsService,
        protected CustomerRepository $customerRepo
    ) {}

    /**
     * Gera campanha inteligente direcionada ao público inativo.
     * POST /api/v1/campaigns
     */
    public function generate(GenerateCampaignRequest $request): JsonResponse
    {
        try {
            $days      = (int) ($request->validated('days') ?: 30);
            $customGoal= $request->validated('goal');
            $sessionId = $request->validated('session_id');

            // 1. Identifica no banco e mensura o público
            $inactiveData = $this->analyticsService->getInactiveCustomersAnalysis($days, 15);
            $totalCount = $inactiveData['total_inactive_count'];
            $historicalTicket = $inactiveData['estimated_avg_ticket'];
            $potentialLoss = $inactiveData['potential_revenue_loss'];

            // 2. Monta a pergunta estruturada para a IA
            $question = $customGoal
                ?: "Crie uma campanha para clientes que não compram há mais de {$days} dias.";

            // 3. Executa a geração via AIService
            $aiResult = $this->aiService->ask($question, $sessionId);

            // 4. Retorna a resposta estruturada para a interface Vue.js
            return response()->json([
                'status' => 'success',
                'data'   => [
                    'audience' => [
                        'label'                       => "Clientes sem pedidos há mais de {$days} dias",
                        'days_threshold'              => $days,
                        'total_customers'             => $totalCount,
                        'average_historical_ticket'   => $historicalTicket,
                        'potential_recoverable_value' => $potentialLoss,
                        'sample_recipients'           => $inactiveData['sample_customers'],
                        'audience_status'             => 'Segmento criado e pronto para disparo',
                    ],
                    'campaign' => [
                        'goal'              => $question,
                        'generated_text'    => $aiResult['response'],
                        'provider'          => $aiResult['provider'],
                        'model'             => $aiResult['model'],
                        'tokens'            => $aiResult['tokens'],
                    ],
                    'session_id' => $aiResult['session_id'],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error("CampaignController@generate falhou: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Ocorreu um erro ao gerar a campanha com IA. Tente novamente.',
            ], 500);
        }
    }
}