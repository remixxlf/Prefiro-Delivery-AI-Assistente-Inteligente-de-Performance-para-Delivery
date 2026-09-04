<?php

namespace App\Services;

use App\Repositories\OrderRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\ProductRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * AnalyticsService
 *
 * Serviço central de inteligência analítica para o Delivery.
 * Orquestra as consultas agregadas dos repositórios para compor contextos
 * analíticos enriquecidos e compactos para o modelo de IA.
 *
 * REGRA FUNDAMENTAL:
 * Nenhum registro bruto é enviado à IA. Somente métricas agregadas,
 * comparativos percentuais e rankings consolidados são retornados.
 *
 * OTIMIZAÇÃO (Parte 9 - Cache):
 * Métricas consolidadas são cacheadas no Redis/Cache com TTL controlado
 * para reduzir carga no MySQL em consultas repetitivas de analytics.
 */
class AnalyticsService
{
    // TTL padrão para cache de métricas analíticas (15 minutos)
    const CACHE_TTL_METRICS = 900;

    public function __construct(
        protected OrderRepository $orderRepo,
        protected CustomerRepository $customerRepo,
        protected ProductRepository $productRepo
    ) {}

    /**
     * Limpa chaves de cache analítico.
     */
    public function clearCache(): void
    {
        Cache::forget('analytics:dashboard_summary');
        Cache::forget('analytics:best_day');
        Cache::forget('analytics:peak_hours');
    }

    /**
     * Análise completa de queda/variação de faturamento (mês atual vs mês anterior).
     * Atende diretamente o exemplo principal do PDF do teste técnico.
     */
    public function getPerformanceDropAnalysis(?Carbon $referenceDate = null): array
    {
        $ref = $referenceDate ? $referenceDate->copy() : Carbon::now();
        $cacheKey = 'analytics:perf_drop:' . $ref->format('Y-m');

        return Cache::remember($cacheKey, self::CACHE_TTL_METRICS, function () use ($ref) {
            // Mês atual
            $currentStart = $ref->copy()->startOfMonth()->toDateTimeString();
            $currentEnd = $ref->copy()->endOfMonth()->toDateTimeString();

            // Mês anterior
            $prevRef = $ref->copy()->subMonth();
            $prevStart = $prevRef->copy()->startOfMonth()->toDateTimeString();
            $prevEnd = $prevRef->copy()->endOfMonth()->toDateTimeString();

            // 1. Comparativo financeiro e volume de pedidos
            $periodsComparison = $this->orderRepo->comparePeriods(
                $currentStart,
                $currentEnd,
                $prevStart,
                $prevEnd
            );

            // 2. Churn recente: clientes que compraram no mês anterior e ainda não compraram este mês
            $churnAnalysis = $this->customerRepo->getCustomersBoughtInPreviousButNotInCurrent(
                $currentStart,
                $currentEnd,
                $prevStart,
                $prevEnd
            );

            // 3. Desempenho por categoria
            $categoryComparison = $this->productRepo->compareCategories(
                $currentStart,
                $currentEnd,
                $prevStart,
                $prevEnd
            );

            // 4. Produtos que mais perderam vendas
            $losingProducts = $this->productRepo->getProductsLosingSales(
                $currentStart,
                $currentEnd,
                $prevStart,
                $prevEnd,
                5
            );

            // 5. Total de clientes inativos há mais de 30 dias
            $inactiveCount = $this->customerRepo->getInactiveCustomersCount(30);

            return [
                'financial_comparison'  => $periodsComparison,
                'customer_churn'        => $churnAnalysis,
                'category_performance'  => $categoryComparison,
                'losing_products'       => $losingProducts,
                'inactive_customers_30d'=> $inactiveCount,
                'current_month'         => $ref->locale('pt_BR')->translatedFormat('F/Y'),
                'previous_month'        => $prevRef->locale('pt_BR')->translatedFormat('F/Y'),
            ];
        });
    }

    /**
     * Análise do melhor e pior dia da semana para vendas.
     */
    public function getBestDayOfWeekAnalysis(): array
    {
        return Cache::remember('analytics:best_day', self::CACHE_TTL_METRICS, function () {
            return $this->orderRepo->getRevenueAndOrdersByDayOfWeek();
        });
    }

    /**
     * Análise dos horários de pico (almoço vs jantar).
     */
    public function getPeakHoursAnalysis(): array
    {
        return Cache::remember('analytics:peak_hours', self::CACHE_TTL_METRICS, function () {
            return $this->orderRepo->getOrdersByHourOfDay();
        });
    }

    /**
     * Ranking dos produtos campeões de venda.
     */
    public function getTopProductsAnalysis(int $limit = 5): array
    {
        $products = $this->productRepo->getTopSellingProducts($limit);

        return [
            'ranking' => $products,
            'total_listed' => count($products),
        ];
    }

    /**
     * Ranking e perfil dos melhores clientes.
     */
    public function getBestCustomersAnalysis(int $limit = 10): array
    {
        $customers = $this->customerRepo->getTopCustomers($limit);

        return [
            'top_customers' => $customers,
            'total_listed'  => count($customers),
        ];
    }

    /**
     * Diagnóstico de clientes inativos e potencial de recuperação.
     */
    public function getInactiveCustomersAnalysis(int $daysThreshold = 30, int $sampleLimit = 20): array
    {
        $inactiveCount = $this->customerRepo->getInactiveCustomersCount($daysThreshold);
        $sample = $this->customerRepo->getInactiveCustomers($daysThreshold, $sampleLimit);

        // Ticket médio estimado
        $avgTicket = (float) round(
            count($sample) > 0 ? (array_sum(array_column($sample, 'total_spent')) / max(1, array_sum(array_column($sample, 'total_orders')))) : 50.00,
            2
        );

        $potentialLoss = round($inactiveCount * $avgTicket, 2);

        return [
            'days_threshold'         => $daysThreshold,
            'total_inactive_count'   => $inactiveCount,
            'estimated_avg_ticket'   => $avgTicket,
            'potential_revenue_loss' => $potentialLoss,
            'sample_customers'       => $sample,
        ];
    }

    /**
     * Monta diagnóstico acionável ("Três ações que posso executar esta semana").
     */
    public function getActionableInsightsData(): array
    {
        $inactive = $this->customerRepo->getInactiveCustomersCount(30);
        $bestDayData = $this->getBestDayOfWeekAnalysis();
        $losingProducts = $this->productRepo->getProductsLosingSales(
            Carbon::now()->startOfMonth()->toDateTimeString(),
            Carbon::now()->endOfMonth()->toDateTimeString(),
            Carbon::now()->subMonth()->startOfMonth()->toDateTimeString(),
            Carbon::now()->subMonth()->endOfMonth()->toDateTimeString(),
            3
        );

        return [
            'inactive_customers' => [
                'count'            => $inactive,
                'recommended_action' => 'Disparar campanha de reativação com benefício no retorno.',
            ],
            'worst_weekday'      => $bestDayData['worst_day'],
            'best_weekday'       => $bestDayData['best_day'],
            'declining_products' => $losingProducts,
        ];
    }

    /**
     * Resumo executivo para o Dashboard inicial e cards rápidos.
     */
    public function getDashboardSummary(): array
    {
        return Cache::remember('analytics:dashboard_summary', self::CACHE_TTL_METRICS, function () {
            $now = Carbon::now();
            $currStart = $now->copy()->startOfMonth()->toDateTimeString();
            $currEnd = $now->copy()->endOfMonth()->toDateTimeString();

            return [
                'current_month' => [
                    'month_name'     => $now->locale('pt_BR')->translatedFormat('F/Y'),
                    'revenue'        => $this->orderRepo->getRevenueByPeriod($currStart, $currEnd),
                    'average_ticket' => $this->orderRepo->getAverageTicket($currStart, $currEnd),
                    'orders_count'   => $this->orderRepo->getDeliveredCountByPeriod($currStart, $currEnd),
                ],
                'customer_health' => $this->customerRepo->getCustomerHealthSegmentation(),
                'top_products'    => $this->productRepo->getTopSellingProducts(5),
                'monthly_trend'   => $this->orderRepo->getMonthlyTrend(6),
            ];
        });
    }
}