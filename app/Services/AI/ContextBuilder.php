<?php

namespace App\Services\AI;

use App\Services\AnalyticsService;
use App\Repositories\OrderRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\ProductRepository;
use Carbon\Carbon;

/**
 * ContextBuilder
 *
 * Constrói o payload de contexto analítico com base na intenção identificada.
 *
 * REGRA CENTRAL DE SEGURANÇA E PERFORMANCE:
 * - Nunca consulta nem envia registros brutos do banco para a IA.
 * - Envia exclusivamente métricas pré-computadas, taxas percentuais e rankings consolidados.
 * - Cada intenção recebe apenas os dados estritamente necessários para sua resposta.
 */
class ContextBuilder
{
    public function __construct(
        protected AnalyticsService $analyticsService,
        protected OrderRepository $orderRepo,
        protected CustomerRepository $customerRepo,
        protected ProductRepository $productRepo
    ) {}

    /**
     * Monta o array de contexto com dados reais do banco para a intenção fornecida.
     */
    public function build(string $intent, array $parameters = []): array
    {
        $now = Carbon::now();

        $data = match ($intent) {
            IntentResolver::INTENT_REVENUE_DROP =>
                $this->buildRevenueDropContext($now),

            IntentResolver::INTENT_REVENUE_COMPARISON =>
                $this->buildRevenueComparisonContext($now),

            IntentResolver::INTENT_BEST_DAY =>
                $this->analyticsService->getBestDayOfWeekAnalysis(),

            IntentResolver::INTENT_PEAK_HOURS =>
                $this->analyticsService->getPeakHoursAnalysis(),

            IntentResolver::INTENT_TOP_PRODUCTS =>
                $this->analyticsService->getTopProductsAnalysis(10),

            IntentResolver::INTENT_LOSING_PRODUCTS =>
                $this->buildLosingProductsContext($now),

            IntentResolver::INTENT_AVERAGE_TICKET =>
                $this->buildAverageTicketContext($now),

            IntentResolver::INTENT_BEST_CUSTOMERS =>
                $this->analyticsService->getBestCustomersAnalysis(10),

            IntentResolver::INTENT_INACTIVE_CUSTOMERS =>
                $this->analyticsService->getInactiveCustomersAnalysis(
                    $parameters['days'] ?? 30,
                    20
                ),

            IntentResolver::INTENT_GROWTH_ACTIONS =>
                $this->analyticsService->getActionableInsightsData(),

            IntentResolver::INTENT_CREATE_CAMPAIGN =>
                $this->buildCampaignContext($parameters['days'] ?? 30),

            default =>
                $this->analyticsService->getDashboardSummary(),
        };

        return [
            'intent'            => $intent,
            'timestamp'         => $now->toIso8601String(),
            'currency'          => 'BRL (R$)',
            'data_source'       => 'MySQL Delivery Database (Dados Reais Agregados)',
            'data_integrity'    => 'Valores consolidados no banco de dados. A IA não pode alterar nem inventar números.',
            'metrics'           => $data,
        ];
    }

    /**
     * Contexto para análise de queda de faturamento (Caso de estudo principal do teste).
     */
    protected function buildRevenueDropContext(Carbon $now): array
    {
        return $this->analyticsService->getPerformanceDropAnalysis($now);
    }

    /**
     * Contexto para comparativo de faturamento (quanto vendi, aumentou ou diminuiu).
     */
    protected function buildRevenueComparisonContext(Carbon $now): array
    {
        $currStart = $now->copy()->startOfMonth()->toDateTimeString();
        $currEnd = $now->copy()->endOfMonth()->toDateTimeString();
        $prevStart = $now->copy()->subMonth()->startOfMonth()->toDateTimeString();
        $prevEnd = $now->copy()->subMonth()->endOfMonth()->toDateTimeString();

        return [
            'periods_comparison' => $this->orderRepo->comparePeriods($currStart, $currEnd, $prevStart, $prevEnd),
            'monthly_trend'      => $this->orderRepo->getMonthlyTrend(6),
            'current_month_name' => $now->locale('pt_BR')->translatedFormat('F/Y'),
            'prev_month_name'    => $now->copy()->subMonth()->locale('pt_BR')->translatedFormat('F/Y'),
        ];
    }

    /**
     * Contexto para produtos em queda de vendas.
     */
    protected function buildLosingProductsContext(Carbon $now): array
    {
        $currStart = $now->copy()->startOfMonth()->toDateTimeString();
        $currEnd = $now->copy()->endOfMonth()->toDateTimeString();
        $prevStart = $now->copy()->subMonth()->startOfMonth()->toDateTimeString();
        $prevEnd = $now->copy()->subMonth()->endOfMonth()->toDateTimeString();

        return [
            'losing_products'    => $this->productRepo->getProductsLosingSales($currStart, $currEnd, $prevStart, $prevEnd, 10),
            'category_comparison'=> $this->productRepo->compareCategories($currStart, $currEnd, $prevStart, $prevEnd),
            'current_month'      => $now->locale('pt_BR')->translatedFormat('F/Y'),
            'previous_month'     => $now->copy()->subMonth()->locale('pt_BR')->translatedFormat('F/Y'),
        ];
    }

    /**
     * Contexto para análise de ticket médio.
     */
    protected function buildAverageTicketContext(Carbon $now): array
    {
        $currStart = $now->copy()->startOfMonth()->toDateTimeString();
        $currEnd = $now->copy()->endOfMonth()->toDateTimeString();
        $prevStart = $now->copy()->subMonth()->startOfMonth()->toDateTimeString();
        $prevEnd = $now->copy()->subMonth()->endOfMonth()->toDateTimeString();

        $comp = $this->orderRepo->comparePeriods($currStart, $currEnd, $prevStart, $prevEnd);

        return [
            'current_ticket'     => $comp['current']['average_ticket'],
            'previous_ticket'    => $comp['previous']['average_ticket'],
            'ticket_difference'  => $comp['difference']['ticket_diff'],
            'growth_percent'     => $comp['difference']['ticket_growth_percent'],
            'current_month'      => $now->locale('pt_BR')->translatedFormat('F/Y'),
            'previous_month'     => $now->copy()->subMonth()->locale('pt_BR')->translatedFormat('F/Y'),
            'high_ticket_upsell' => $this->productRepo->getHighTicketPotentialProducts(),
        ];
    }

    /**
     * Contexto para criação de campanhas inteligentes.
     */
    protected function buildCampaignContext(int $days = 30): array
    {
        $inactiveData = $this->analyticsService->getInactiveCustomersAnalysis($days, 20);
        $topProducts = $this->productRepo->getTopSellingProducts(3);

        return [
            'campaign_target'          => "Clientes inativos sem pedidos há mais de {$days} dias",
            'inactive_customers_found' => $inactiveData['total_inactive_count'],
            'estimated_historical_ticket' => $inactiveData['estimated_avg_ticket'],
            'potential_recovered_revenue' => $inactiveData['potential_revenue_loss'],
            'popular_items_for_hook'   => array_column($topProducts, 'name'),
        ];
    }
}