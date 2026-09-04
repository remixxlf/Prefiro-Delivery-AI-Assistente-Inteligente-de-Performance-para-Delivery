<?php

namespace App\Repositories;

use App\Models\Customer;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;

/**
 * CustomerRepository
 *
 * Consultas agregadas e métricas analíticas sobre a base de clientes.
 * Focado em retenção, churn, segmentação RFM (Recência, Frequência, Valor)
 * e identificação de público-alvo para campanhas.
 */
class CustomerRepository
{
    /**
     * Retorna os melhores clientes (maior faturamento gerado).
     */
    public function getTopCustomers(int $limit = 10, ?string $from = null, ?string $to = null): array
    {
        $query = DB::table('customers')
            ->join('orders', 'customers.id', '=', 'orders.customer_id')
            ->where('orders.status', Order::STATUS_DELIVERED);

        if ($from && $to) {
            $query->whereBetween('orders.ordered_at', [$from, $to]);
        }

        $records = $query
            ->select('customers.id', 'customers.name', 'customers.phone', 'customers.last_order_at')
            ->selectRaw('COUNT(orders.id) as total_orders')
            ->selectRaw('SUM(orders.total) as total_spent')
            ->selectRaw('AVG(orders.total) as average_ticket')
            ->groupBy('customers.id', 'customers.name', 'customers.phone', 'customers.last_order_at')
            ->orderByDesc('total_spent')
            ->limit($limit)
            ->get();

        return $records->map(function ($row, $index) {
            $daysSinceLastOrder = $row->last_order_at
                ? Carbon::now()->diffInDays(Carbon::parse($row->last_order_at))
                : null;

            return [
                'rank'                  => $index + 1,
                'customer_id'           => $row->id,
                'name'                  => $row->name,
                'phone'                 => $row->phone,
                'total_orders'          => (int) $row->total_orders,
                'total_spent'           => round((float) $row->total_spent, 2),
                'average_ticket'        => round((float) $row->average_ticket, 2),
                'last_order_at'         => $row->last_order_at,
                'days_since_last_order' => $daysSinceLastOrder,
            ];
        })->toArray();
    }

    /**
     * Retorna clientes inativos há mais de $days dias.
     * Utilizado tanto para análises quanto para campanhas.
     */
    public function getInactiveCustomers(int $days = 30, int $limit = 100): array
    {
        $threshold = Carbon::now()->subDays($days);

        $records = Customer::where(function ($q) use ($threshold) {
                $q->where('last_order_at', '<', $threshold)
                  ->orWhereNull('last_order_at');
            })
            ->withCount(['orders' => function ($q) {
                $q->where('status', Order::STATUS_DELIVERED);
            }])
            ->withSum(['orders' => function ($q) {
                $q->where('status', Order::STATUS_DELIVERED);
            }], 'total')
            ->orderBy('last_order_at', 'asc')
            ->limit($limit)
            ->get();

        return $records->map(function (Customer $customer) {
            $daysSinceLastOrder = $customer->last_order_at
                ? Carbon::now()->diffInDays($customer->last_order_at)
                : null;

            $totalSpent = (float) ($customer->orders_sum_total ?? 0);
            $totalOrders = (int) $customer->orders_count;
            $avgTicket = $totalOrders > 0 ? round($totalSpent / $totalOrders, 2) : 0.00;

            return [
                'customer_id'           => $customer->id,
                'name'                  => $customer->name,
                'phone'                 => $customer->phone,
                'first_order_at'        => $customer->first_order_at?->format('Y-m-d H:i:s'),
                'last_order_at'         => $customer->last_order_at?->format('Y-m-d H:i:s'),
                'days_since_last_order' => $daysSinceLastOrder,
                'lifetime_orders'       => $totalOrders,
                'lifetime_spent'        => round($totalSpent, 2),
                'average_ticket'        => $avgTicket,
            ];
        })->toArray();
    }

    /**
     * Contagem exata de clientes inativos há mais de $days dias.
     */
    public function getInactiveCustomersCount(int $days = 30): int
    {
        $threshold = Carbon::now()->subDays($days);

        return Customer::where(function ($q) use ($threshold) {
            $q->where('last_order_at', '<', $threshold)
              ->orWhereNull('last_order_at');
        })->count();
    }

    /**
     * Identifica clientes que compraram no período anterior mas
     * NÃO compraram no período atual (ex: churn recente).
     * Exigência expressa do exemplo do PDF:
     * "37 clientes que compraram no mês anterior ainda não realizaram pedidos neste mês."
     */
    public function getCustomersBoughtInPreviousButNotInCurrent(
        string $currentFrom,
        string $currentTo,
        string $previousFrom,
        string $previousTo
    ): array {
        // Clientes com pedidos entregues no período anterior
        $previousCustomerIds = DB::table('orders')
            ->where('status', Order::STATUS_DELIVERED)
            ->whereBetween('ordered_at', [$previousFrom, $previousTo])
            ->distinct()
            ->pluck('customer_id');

        // Clientes com pedidos entregues no período atual
        $currentCustomerIds = DB::table('orders')
            ->where('status', Order::STATUS_DELIVERED)
            ->whereBetween('ordered_at', [$currentFrom, $currentTo])
            ->distinct()
            ->pluck('customer_id');

        // Churn recente = no período anterior mas não no atual
        $churnedIds = $previousCustomerIds->diff($currentCustomerIds);

        // Agregação de impacto desses clientes
        $churnedStats = DB::table('orders')
            ->whereIn('customer_id', $churnedIds)
            ->whereBetween('ordered_at', [$previousFrom, $previousTo])
            ->where('status', Order::STATUS_DELIVERED)
            ->selectRaw('SUM(total) as lost_revenue')
            ->selectRaw('AVG(total) as average_ticket')
            ->first();

        return [
            'churned_customers_count' => $churnedIds->count(),
            'previous_active_count'   => $previousCustomerIds->count(),
            'current_active_count'    => $currentCustomerIds->count(),
            'estimated_lost_revenue'  => round((float) ($churnedStats->lost_revenue ?? 0), 2),
            'average_ticket'          => round((float) ($churnedStats->average_ticket ?? 0), 2),
        ];
    }

    /**
     * Métricas de aquisição de novos clientes vs clientes recorrentes.
     */
    public function getAcquisitionAndRecurrence(string $from, string $to): array
    {
        // Clientes que compraram no período
        $activeCustomerIds = DB::table('orders')
            ->where('status', Order::STATUS_DELIVERED)
            ->whereBetween('ordered_at', [$from, $to])
            ->distinct()
            ->pluck('customer_id');

        $totalActive = $activeCustomerIds->count();

        // Novos clientes no período (primeiro pedido dentro do intervalo)
        $newCustomersCount = Customer::whereBetween('first_order_at', [$from, $to])
            ->whereIn('id', $activeCustomerIds)
            ->count();

        // Clientes recorrentes no período (primeiro pedido anterior ao início do período)
        $recurringCustomersCount = Customer::where('first_order_at', '<', $from)
            ->whereIn('id', $activeCustomerIds)
            ->count();

        $recurrenceRate = $totalActive > 0
            ? round(($recurringCustomersCount / $totalActive) * 100, 1)
            : 0.0;

        return [
            'total_active_customers'    => $totalActive,
            'new_customers'             => $newCustomersCount,
            'recurring_customers'       => $recurringCustomersCount,
            'recurrence_rate_percent'   => $recurrenceRate,
        ];
    }

    /**
     * Diagnóstico da base de clientes em 4 faixas de saúde (RFM Simplificado):
     *  - Ativos: última compra <= 15 dias
     *  - Em risco: última compra entre 16 e 30 dias
     *  - Inativos: última compra entre 31 e 90 dias
     *  - Perdidos: última compra > 90 dias ou sem compras
     */
    public function getCustomerHealthSegmentation(): array
    {
        $now = Carbon::now();
        $totalCustomers = Customer::count();

        $activeCount = Customer::where('last_order_at', '>=', $now->copy()->subDays(15))->count();

        $atRiskCount = Customer::whereBetween('last_order_at', [
            $now->copy()->subDays(30),
            $now->copy()->subDays(15),
        ])->count();

        $inactiveCount = Customer::whereBetween('last_order_at', [
            $now->copy()->subDays(90),
            $now->copy()->subDays(31),
        ])->count();

        $lostCount = Customer::where('last_order_at', '<', $now->copy()->subDays(90))
            ->orWhereNull('last_order_at')
            ->count();

        return [
            'total_customers' => $totalCustomers,
            'segments' => [
                [
                    'label'         => 'Ativos (<= 15 dias)',
                    'count'         => $activeCount,
                    'percentage'    => $totalCustomers > 0 ? round(($activeCount / $totalCustomers) * 100, 1) : 0,
                    'status_color'  => 'green',
                ],
                [
                    'label'         => 'Em Risco (16 a 30 dias)',
                    'count'         => $atRiskCount,
                    'percentage'    => $totalCustomers > 0 ? round(($atRiskCount / $totalCustomers) * 100, 1) : 0,
                    'status_color'  => 'yellow',
                ],
                [
                    'label'         => 'Inativos (31 a 90 dias)',
                    'count'         => $inactiveCount,
                    'percentage'    => $totalCustomers > 0 ? round(($inactiveCount / $totalCustomers) * 100, 1) : 0,
                    'status_color'  => 'orange',
                    'action_needed' => 'Disparar campanha de reativação imediata',
                ],
                [
                    'label'         => 'Perdidos (> 90 dias)',
                    'count'         => $lostCount,
                    'percentage'    => $totalCustomers > 0 ? round(($lostCount / $totalCustomers) * 100, 1) : 0,
                    'status_color'  => 'red',
                ],
            ],
        ];
    }
}