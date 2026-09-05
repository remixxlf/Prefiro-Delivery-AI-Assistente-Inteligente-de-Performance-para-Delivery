<?php

namespace App\Services;

use App\Services\AI\AIService;
use App\Services\AnalyticsService;
use Exception;

/**
 * CampaignService
 *
 * Encapsula a lógica de negócio para a Ação Prática com IA:
 * 1. Segmenta clientes no banco (ex: inativos).
 * 2. Compila métricas do público-alvo (quantidade, ticket médio).
 * 3. Delega a geração da copy persuasiva para o motor de IA.
 */
class CampaignService
{
    public function __construct(
        protected AnalyticsService $analyticsService,
        protected AIService $aiService
    ) {}

    /**
     * Identifica clientes inativos e gera uma campanha estruturada via IA.
     *
     * @param int $days Dias de inatividade (ex: 30, 60)
     * @param string|null $customGoal Objetivo customizado opcional
     * @param string|null $sessionId ID da sessão atual para log
     * @return array
     */
    public function generateInactiveCustomersCampaign(int $days = 30, ?string $customGoal = null, ?string $sessionId = null): array
    {
        // 1. Identifica e mensura o público-alvo no banco de dados (AnalyticsService -> Repositories)
        $inactiveData = $this->analyticsService->getInactiveCustomersAnalysis($days, 15);
        
        $totalCount = $inactiveData['total_inactive_count'];
        $historicalTicket = $inactiveData['estimated_avg_ticket'];
        $potentialLoss = $inactiveData['potential_revenue_loss'];

        // Se não houver clientes inativos suficientes, pode lançar uma exception leve ou continuar
        // Aqui optamos por seguir para demonstrar o poder da IA, mas o prompt receberá 0 clientes.
        
        // 2. Define o comando para a IA baseado no objetivo
        $question = $customGoal ?: "Crie uma campanha para clientes que não compram há mais de {$days} dias.";

        // 3. Executa a geração chamando a API de IA
        $aiResult = $this->aiService->ask($question, $sessionId);

        // 4. Estrutura a resposta unindo os dados extraídos do BD com a copy gerada pela IA
        return [
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
        ];
    }
}