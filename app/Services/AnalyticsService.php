<?php

namespace App\Services;

use App\Repositories\OrderRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\ProductRepository;
use Carbon\Carbon;

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
 */
class AnalyticsService
{
    public function __construct(
        protected OrderRepository $orderRepo,
        protected CustomerRepository $customerRepo,
        protected ProductRepository $productRepo
    ) {}

    /**
     * Análise completa de queda/variação de faturamento (mês atual vs mês anterior).
     * Atende diretamente o exemplo principal do PDF do teste técnico.
     */
    public function getPerformanceDropAnalysis(?Carbon $referenceDate = null): array
    {
        $ref = $referenceDate ? $referenceDate->copy() : Carbon::now();

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

        // 3. Aquisição vs recorrência no mês atual
        $acquisition = $this->customerRepo->getAcquisitionAndRecurrence(
            $currentStart,
            $currentEnd
        );

        // 4. Comparativo por categorias (qual categoria mais caiu)
        $categoriesComparison = $this->productRepo->compareCategories(
            $currentStart,
            $currentEnd,
            $prevStart,
            $prevEnd
        );

        // 5. Produtos que mais perderam vendas
        $productsLosing = $this->productRepo->getProductsLosingSales(
            $currentStart,
            $currentEnd,
            $prevStart,
            $prevEnd,
            5
        );

        // 6. Produtos mais vendidos no mês atual
        $topProducts = $this->productRepo->getTopSellingProducts(
            5,
            $currentStart,
            $currentEnd,
            'quantity'
        );

        // 7. Clientes inativos há mais de 30 dias (para sugestão de ação)
        $inactive30DaysCount = $this->customerRepo->getInactiveCustomersCount(30);

        return [
            'reference_month'        => $ref->locale('pt_BR')->translatedFormat('F/Y'),
            'previous_month'         => $prevRef->locale('pt_BR')->translatedFormat('F/Y'),
            'financial_comparison'   => $periodsComparison,
            'customer_churn'         => $churnAnalysis,
            'customer_acquisition'   => $acquisition,
            'category_performance'   => $categoriesComparison,
            'products_losing_sales'  => $productsLosing,
            'top_selling_products'   => $topProducts,
            'inactive_customers_30d' => $inactive30DaysCount,
        ];
    }

    /**
     * Análise do melhor e pior dia da semana para vendas.
     */
    public function getBestDayOfWeekAnalysis(?string $from = null, ?string $to = null): array
    {
        return $this->orderRepo->getRevenueAndOrdersByDayOfWeek($from, $to);
    }

    /**
     * Análise dos horários de pico e distribuição por turno.
     */
    public function getPeakHoursAnalysis(?string $from = null, ?string $to = null): array
    {
        return $this->orderRepo->getOrdersByHourOfDay($from, $to);
    }

    /**
     * Ranking dos produtos mais vendidos (volume ou receita).
     */
    public function getTopProductsAnalysis(int $limit = 10, string $sortBy = 'quantity'): array
    {
        $topProducts = $this->productRepo->getTopSellingProducts($limit, null, null, $sortBy);

        // Análise de participação das categorias no período dos últimos 90 dias
        $from = Carbon::now()->subDays(90)->toDateTimeString();
        $to = Carbon::now()->toDateTimeString();
        $categorySales = $this->productRepo->getSalesByCategory($from, $to);

        return [
            'ranking'        => $topProducts,
            'sorted_by'      => $sortBy,
            'category_share' => $categorySales,
        ];
    }

    /**
     * Ranking dos melhores clientes em receita gerada e frequência.
     */
    public function getBestCustomersAnalysis(int $limit = 10): array
    {
        $topCustomers = $this->customerRepo->getTopCustomers($limit);
        $segmentation = $this->customerRepo->getCustomerHealthSegmentation();

        return [
            'top_customers'       => $topCustomers,
            'customer_base_rfm'   => $segmentation,
        ];
    }

    /**
     * Diagnóstico de clientes inativos e público para campanhas de reativação.
     */
    public function getInactiveCustomersAnalysis(int $days = 30, int $limit = 50): array
    {
        $totalInactive = $this->customerRepo->getInactiveCustomersCount($days);
        $sampleList = $this->customerRepo->getInactiveCustomers($days, $limit);

        // Estima o ticket médio histórico desses clientes inativos
        $avgHistoricalTicket = 0.0;
        if (!empty($sampleList)) {
            $tickets = array_filter(array_column($sampleList, 'average_ticket'));
            $avgHistoricalTicket = count($tickets) > 0 ? round(array_sum($tickets) / count($tickets), 2) : 0.0;
        }

        return [
            'inactive_days_threshold' => $days,
            'total_inactive_count'    => $totalInactive,
            'estimated_avg_ticket'    => $avgHistoricalTicket,
            'sample_customers'        => $sampleList,
            'potential_revenue_loss'  => round($totalInactive * $avgHistoricalTicket, 2),
        ];
    }

    /**
     * Dados consolidados para geração de recomendações e planos de ação (3 ações práticas).
     */
    public function getActionableInsightsData(): array
    {
        $dropAnalysis = $this->getPerformanceDropAnalysis();
        $dayAnalysis = $this->getBestDayOfWeekAnalysis();
        $hoursAnalysis = $this->getPeakHoursAnalysis();
        $inactiveAudience = $this->getInactiveCustomersAnalysis(30, 10);
        $highTicketProducts = $this->productRepo->getHighTicketPotentialProducts();

        return [
            'performance_summary'  => $dropAnalysis['financial_comparison']['difference'],
            'inactive_customers'   => [
                'count'            => $inactiveAudience['total_inactive_count'],
                'potential_impact' => $inactiveAudience['potential_revenue_loss'],
            ],
            'best_weekday'         => $dayAnalysis['best_day'],
            'worst_weekday'        => $dayAnalysis['worst_day'],
            'peak_hour'            => $hoursAnalysis['peak_hour'],
            'shift_breakdown'      => $hoursAnalysis['shift_breakdown'],
            'declining_products'   => array_slice($dropAnalysis['products_losing_sales'], 0, 3),
            'top_products'         => array_slice($dropAnalysis['top_selling_products'], 0, 3),
            'high_ticket_options'  => array_slice($highTicketProducts, 0, 4),
        ];
    }

    /**
     * Resumo executivo para o Dashboard do estabelecimento.
     */
    public function getDashboardSummary(): array
    {
        $now = Carbon::now();
        $monthStart = $now->copy()->startOfMonth()->toDateTimeString();
        $monthEnd = $now->copy()->endOfMonth()->toDateTimeString();

        $monthSummary = $this->orderRepo->getSummaryByPeriod($monthStart, $monthEnd);
        $health = $this->customerRepo->getCustomerHealthSegmentation();
        $topProducts = $this->productRepo->getTopSellingProducts(5, $monthStart, $monthEnd);
        $recentTrend = $this->orderRepo->getMonthlyTrend(6);

        return [
            'current_month' => [
                'month_name'     => $now->locale('pt_BR')->translatedFormat('F/Y'),
                'revenue'        => $monthSummary['total_revenue'],
                'orders_count'   => $monthSummary['delivered_orders'],
                'average_ticket' => $monthSummary['average_ticket'],
            ],
            'customer_health' => $health,
            'top_products'    => $topProducts,
            'monthly_trend'   => $recentTrend,
        ];
    }
}