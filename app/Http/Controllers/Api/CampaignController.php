<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateCampaignRequest;
use App\Services\CampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * CampaignController
 *
 * Exposto via REST API.
 * Delega o fluxo de negócio para CampaignService.
 */
class CampaignController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService
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

            $result = $this->campaignService->generateInactiveCustomersCampaign($days, $customGoal, $sessionId);

            return response()->json([
                'status' => 'success',
                'data'   => $result,
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