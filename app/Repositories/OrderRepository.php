<?php

namespace App\Repositories;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * OrderRepository
 *
 * Consultas otimizadas e agregadas para pedidos e faturamento.
 * Todas as métricas são calculadas no banco de dados e nenhum
 * registro bruto individual é retornado ou exposto desnecessariamente.
 *
 * Suporta MySQL (produção) e SQLite (testes automatizados).
 */
class OrderRepository
{
    /**
     * Retorna o driver de banco de dados ativo.
     */
    protected function getDriver(): string
    {
        return DB::connection()->getDriverName();
    }

    /**
     * Faturamento total (apenas pedidos entregues) em um período.
     */
    public function getRevenueByPeriod(string $from, string $to): float
    {
        return (float) Order::delivered()
            ->inPeriod($from, $to)
            ->sum('total');
    }

    /**
     * Ticket médio dos pedidos entregues em um período.
     */
    public function getAverageTicket(string $from, string $to): float
    {
        $avg = Order::delivered()
            ->inPeriod($from, $to)
            ->avg('total');

        return round((float) $avg, 2);
    }

    /**
     * Resumo executivo completo de métricas em um período.
     */
    public function getSummaryByPeriod(string $from, string $to): array
    {
        $deliveredQuery = Order::delivered()->inPeriod($from, $to);
        $allOrdersQuery = Order::inPeriod($from, $to);

        $totalRevenue = (float) $deliveredQuery->sum('total');
        $deliveredCount = (int) $deliveredQuery->count();
        $totalOrdersCount = (int) $allOrdersQuery->count();
        $cancelledCount = (int) Order::where('status', Order::STATUS_CANCELLED)
            ->inPeriod($from, $to)
            ->count();

        $averageTicket = $deliveredCount > 0 ? round($totalRevenue / $deliveredCount, 2) : 0.00;
        $cancellationRate = $totalOrdersCount > 0
            ? round(($cancelledCount / $totalOrdersCount) * 100, 2)
            : 0.00;

        $totalDiscounts = (float) Order::delivered()->inPeriod($from, $to)->sum('discount');
        $totalDeliveryFees = (float) Order::delivered()->inPeriod($from, $to)->sum('delivery_fee');

        return [
            'period_from'         => $from,
            'period_to'           => $to,
            'total_revenue'       => round($totalRevenue, 2),
            'delivered_orders'    => $deliveredCount,
            'total_orders'        => $totalOrdersCount,
            'cancelled_orders'    => $cancelledCount,
            'cancellation_rate'   => $cancellationRate,
            'average_ticket'      => $averageTicket,
            'total_discounts'     => round($totalDiscounts, 2),
            'total_delivery_fees' => round($totalDeliveryFees, 2),
        ];
    }

    /**
     * Compara dois períodos (ex: mês atual vs mês anterior)
     * e calcula deltas absolutos e percentuais.
     */
    public function comparePeriods(
        string $currentFrom,
        string $currentTo,
        string $previousFrom,
        string $previousTo
    ): array {
        $current = $this->getSummaryByPeriod($currentFrom, $currentTo);
        $previous = $this->getSummaryByPeriod($previousFrom, $previousTo);

        $revenueDiff = $current['total_revenue'] - $previous['total_revenue'];
        $revenueGrowthPercent = $previous['total_revenue'] > 0
            ? round(($revenueDiff / $previous['total_revenue']) * 100, 2)
            : 0.00;

        $ordersDiff = $current['delivered_orders'] - $previous['delivered_orders'];
        $ordersGrowthPercent = $previous['delivered_orders'] > 0
            ? round(($ordersDiff / $previous['delivered_orders']) * 100, 2)
            : 0.00;

        $ticketDiff = $current['average_ticket'] - $previous['average_ticket'];
        $ticketGrowthPercent = $previous['average_ticket'] > 0
            ? round(($ticketDiff / $previous['average_ticket']) * 100, 2)
            : 0.00;

        return [
            'current' => $current,
            'previous' => $previous,
            'difference' => [
                'revenue_diff'            => round($revenueDiff, 2),
                'revenue_growth_percent'  => $revenueGrowthPercent,
                'orders_diff'             => $ordersDiff,
                'orders_growth_percent'   => $ordersGrowthPercent,
                'ticket_diff'             => round($ticketDiff, 2),
                'ticket_growth_percent'   => $ticketGrowthPercent,
                'is_growth'               => $revenueDiff >= 0,
            ],
        ];
    }

    /**
     * Distribuição de pedidos e faturamento por dia da semana.
     * 1 = Domingo, 2 = Segunda, ..., 7 = Sábado.
     */
    public function getRevenueAndOrdersByDayOfWeek(?string $from = null, ?string $to = null): array
    {
        $dayNames = [
            1 => 'Domingo',
            2 => 'Segunda-feira',
            3 => 'Terça-feira',
            4 => 'Quarta-feira',
            5 => 'Quinta-feira',
            6 => 'Sexta-feira',
            7 => 'Sábado',
        ];

        $isSqlite = $this->getDriver() === 'sqlite';

        $dayOfWeekExpr = $isSqlite
            ? "(CAST(strftime('%w', ordered_at) AS INTEGER) + 1)"
            : "DAYOFWEEK(ordered_at)";

        $query = Order::delivered();
        if ($from && $to) {
            $query->inPeriod($from, $to);
        }

        $results = $query
            ->selectRaw("{$dayOfWeekExpr} as day_num")
            ->selectRaw("COUNT(*) as total_orders")
            ->selectRaw("SUM(total) as total_revenue")
            ->selectRaw("AVG(total) as avg_ticket")
            ->groupBy('day_num')
            ->orderBy('day_num')
            ->get()
            ->keyBy('day_num');

        $totalAllOrders = $results->sum('total_orders');
        $bestDayNum = null;
        $worstDayNum = null;
        $maxRevenue = -1;
        $minRevenue = PHP_FLOAT_MAX;

        $days = [];
        for ($d = 1; $d <= 7; $d++) {
            $record = $results->get($d);
            $orders = $record ? (int) $record->total_orders : 0;
            $rev = $record ? round((float) $record->total_revenue, 2) : 0.00;
            $avg = $record ? round((float) $record->avg_ticket, 2) : 0.00;

            if ($rev > $maxRevenue) {
                $maxRevenue = $rev;
                $bestDayNum = $d;
            }
            if ($rev < $minRevenue && $orders > 0) {
                $minRevenue = $rev;
                $worstDayNum = $d;
            }

            $percentage = $totalAllOrders > 0 ? round(($orders / $totalAllOrders) * 100, 1) : 0;

            $days[$d] = [
                'day_num'         => $d,
                'day_name'        => $dayNames[$d],
                'total_orders'    => $orders,
                'total_revenue'   => $rev,
                'average_ticket'  => $avg,
                'share_percent'   => $percentage,
                'is_best_day'     => false,
                'is_worst_day'    => false,
            ];
        }

        if ($bestDayNum && isset($days[$bestDayNum])) {
            $days[$bestDayNum]['is_best_day'] = true;
        }
        if ($worstDayNum && isset($days[$worstDayNum])) {
            $days[$worstDayNum]['is_worst_day'] = true;
        }

        return [
            'days' => array_values($days),
            'best_day' => $bestDayNum ? $days[$bestDayNum]['day_name'] : null,
            'worst_day' => $worstDayNum ? $days[$worstDayNum]['day_name'] : null,
        ];
    }

    /**
     * Volume de pedidos por horário do dia (picos de almoço e jantar).
     */
    public function getOrdersByHourOfDay(?string $from = null, ?string $to = null): array
    {
        $isSqlite = $this->getDriver() === 'sqlite';

        $hourExpr = $isSqlite
            ? "CAST(strftime('%H', ordered_at) AS INTEGER)"
            : "HOUR(ordered_at)";

        $query = Order::delivered();
        if ($from && $to) {
            $query->inPeriod($from, $to);
        }

        $results = $query
            ->selectRaw("{$hourExpr} as order_hour")
            ->selectRaw("COUNT(*) as total_orders")
            ->selectRaw("SUM(total) as total_revenue")
            ->groupBy('order_hour')
            ->orderBy('order_hour')
            ->get()
            ->keyBy('order_hour');

        $hours = [];
        $peakHour = null;
        $maxOrders = -1;

        for ($h = 0; $h < 24; $h++) {
            $record = $results->get($h);
            $orders = $record ? (int) $record->total_orders : 0;
            $revenue = $record ? round((float) $record->total_revenue, 2) : 0.00;

            if ($orders > $maxOrders) {
                $maxOrders = $orders;
                $peakHour = $h;
            }

            // Classificação do turno
            $shift = match (true) {
                $h >= 6 && $h < 11  => 'Manhã',
                $h >= 11 && $h < 15 => 'Almoço',
                $h >= 15 && $h < 18 => 'Tarde',
                $h >= 18 && $h < 23 => 'Jantar',
                default             => 'Madrugada',
            };

            $hours[] = [
                'hour'            => $h,
                'time_formatted'  => sprintf('%02d:00 - %02d:59', $h, $h),
                'shift'           => $shift,
                'total_orders'    => $orders,
                'total_revenue'   => $revenue,
                'is_peak'         => false,
            ];
        }

        if ($peakHour !== null && isset($hours[$peakHour])) {
            $hours[$peakHour]['is_peak'] = true;
        }

        // Agrupamento por turnos principais
        $shifts = [
            'Almoço (11h-14h)' => 0,
            'Jantar (18h-22h)' => 0,
            'Outros horários'  => 0,
        ];
        foreach ($hours as $item) {
            if ($item['hour'] >= 11 && $item['hour'] <= 14) {
                $shifts['Almoço (11h-14h)'] += $item['total_orders'];
            } elseif ($item['hour'] >= 18 && $item['hour'] <= 22) {
                $shifts['Jantar (18h-22h)'] += $item['total_orders'];
            } else {
                $shifts['Outros horários'] += $item['total_orders'];
            }
        }

        return [
            'hourly'          => array_values(array_filter($hours, fn ($item) => $item['total_orders'] > 0)),
            'peak_hour'       => $peakHour !== null ? sprintf('%02d:00', $peakHour) : null,
            'peak_orders'     => $maxOrders,
            'shift_breakdown' => $shifts,
        ];
    }

    /**
     * Histórico de tendência mensal dos últimos N meses.
     */
    public function getMonthlyTrend(int $months = 12): array
    {
        $isSqlite = $this->getDriver() === 'sqlite';

        $monthExpr = $isSqlite
            ? "strftime('%Y-%m', ordered_at)"
            : "DATE_FORMAT(ordered_at, '%Y-%m')";

        $startDate = Carbon::now()->subMonths($months)->startOfMonth()->toDateTimeString();

        $records = Order::delivered()
            ->where('ordered_at', '>=', $startDate)
            ->selectRaw("{$monthExpr} as year_month")
            ->selectRaw("COUNT(*) as total_orders")
            ->selectRaw("SUM(total) as total_revenue")
            ->selectRaw("AVG(total) as avg_ticket")
            ->groupBy('year_month')
            ->orderBy('year_month', 'asc')
            ->get();

        $trend = [];
        $prevRevenue = null;

        foreach ($records as $record) {
            $rev = round((float) $record->total_revenue, 2);
            $growthPercent = 0.00;
            if ($prevRevenue !== null && $prevRevenue > 0) {
                $growthPercent = round((($rev - $prevRevenue) / $prevRevenue) * 100, 2);
            }

            $dateObj = Carbon::createFromFormat('Y-m', $record->year_month);

            $trend[] = [
                'year_month'     => $record->year_month,
                'month_label'    => $dateObj->locale('pt_BR')->translatedFormat('M/Y'),
                'total_orders'   => (int) $record->total_orders,
                'total_revenue'  => $rev,
                'average_ticket' => round((float) $record->avg_ticket, 2),
                'growth_percent' => $growthPercent,
            ];

            $prevRevenue = $rev;
        }

        return $trend;
    }

    /**
     * Distribuição das formas de pagamento.
     */
    public function getPaymentMethodBreakdown(?string $from = null, ?string $to = null): array
    {
        $query = Order::delivered();
        if ($from && $to) {
            $query->inPeriod($from, $to);
        }

        $records = $query
            ->selectRaw("payment_method")
            ->selectRaw("COUNT(*) as total_orders")
            ->selectRaw("SUM(total) as total_revenue")
            ->groupBy('payment_method')
            ->orderByDesc('total_orders')
            ->get();

        $allOrders = $records->sum('total_orders');

        return $records->map(function ($row) use ($allOrders) {
            $count = (int) $row->total_orders;
            return [
                'payment_method' => $row->payment_method ?? 'não informado',
                'total_orders'   => $count,
                'total_revenue'  => round((float) $row->total_revenue, 2),
                'share_percent'  => $allOrders > 0 ? round(($count / $allOrders) * 100, 1) : 0,
            ];
        })->toArray();
    }
}