<?php

namespace App\Repositories;

use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

/**
 * ProductRepository
 *
 * Consultas otimizadas para performance de vendas de produtos e categorias.
 * Inclui detecção de tendências de alta/baixa, participação de faturamento
 * e oportunidades de otimização de ticket médio.
 */
class ProductRepository
{
    /**
     * Retorna os produtos mais vendidos em um período.
     * Pode ordenar por 'quantity' (volume) ou 'revenue' (faturamento).
     */
    public function getTopSellingProducts(
        int $limit = 10,
        ?string $from = null,
        ?string $to = null,
        string $sortBy = 'quantity'
    ): array {
        $query = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.status', Order::STATUS_DELIVERED);

        if ($from && $to) {
            $query->whereBetween('orders.ordered_at', [$from, $to]);
        }

        $orderColumn = $sortBy === 'revenue' ? 'total_revenue' : 'total_quantity';

        $records = $query
            ->select('products.id', 'products.name', 'products.category', 'products.price')
            ->selectRaw('SUM(order_items.quantity) as total_quantity')
            ->selectRaw('SUM(order_items.subtotal) as total_revenue')
            ->selectRaw('COUNT(DISTINCT orders.id) as orders_count')
            ->groupBy('products.id', 'products.name', 'products.category', 'products.price')
            ->orderByDesc($orderColumn)
            ->limit($limit)
            ->get();

        return $records->map(function ($row, $index) {
            return [
                'rank'           => $index + 1,
                'product_id'     => $row->id,
                'name'           => $row->name,
                'category'       => $row->category,
                'current_price'  => round((float) $row->price, 2),
                'total_quantity' => (int) $row->total_quantity,
                'total_revenue'  => round((float) $row->total_revenue, 2),
                'orders_count'   => (int) $row->orders_count,
            ];
        })->toArray();
    }

    /**
     * Identifica produtos com maior queda de vendas comparando dois períodos.
     * Calcula a variação absoluta e percentual tanto em quantidade quanto em faturamento.
     */
    public function getProductsLosingSales(
        string $currentFrom,
        string $currentTo,
        string $previousFrom,
        string $previousTo,
        int $limit = 10
    ): array {
        // Vendas no período anterior
        $previousSales = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.status', Order::STATUS_DELIVERED)
            ->whereBetween('orders.ordered_at', [$previousFrom, $previousTo])
            ->select('products.id', 'products.name', 'products.category')
            ->selectRaw('SUM(order_items.quantity) as prev_qty')
            ->selectRaw('SUM(order_items.subtotal) as prev_rev')
            ->groupBy('products.id', 'products.name', 'products.category')
            ->get()
            ->keyBy('id');

        // Vendas no período atual
        $currentSales = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.status', Order::STATUS_DELIVERED)
            ->whereBetween('orders.ordered_at', [$currentFrom, $currentTo])
            ->select('products.id', 'products.name', 'products.category')
            ->selectRaw('SUM(order_items.quantity) as curr_qty')
            ->selectRaw('SUM(order_items.subtotal) as curr_rev')
            ->groupBy('products.id', 'products.name', 'products.category')
            ->get()
            ->keyBy('id');

        $losingProducts = [];

        foreach ($previousSales as $id => $prev) {
            $curr = $currentSales->get($id);
            $prevQty = (int) $prev->prev_qty;
            $currQty = $curr ? (int) $curr->curr_qty : 0;
            $prevRev = (float) $prev->prev_rev;
            $currRev = $curr ? (float) $curr->curr_rev : 0.0;

            $qtyDiff = $currQty - $prevQty;
            $revDiff = $currRev - $prevRev;

            // Se houve queda em quantidade ou faturamento
            if ($qtyDiff < 0 || $revDiff < 0) {
                $qtyPercent = $prevQty > 0 ? round(($qtyDiff / $prevQty) * 100, 1) : -100.0;
                $revPercent = $prevRev > 0 ? round(($revDiff / $prevRev) * 100, 1) : -100.0;

                $losingProducts[] = [
                    'product_id'             => $id,
                    'name'                   => $prev->name,
                    'category'               => $prev->category,
                    'previous_quantity'      => $prevQty,
                    'current_quantity'       => $currQty,
                    'quantity_difference'    => $qtyDiff,
                    'quantity_change_percent'=> $qtyPercent,
                    'previous_revenue'       => round($prevRev, 2),
                    'current_revenue'        => round($currRev, 2),
                    'revenue_difference'     => round($revDiff, 2),
                    'revenue_change_percent' => $revPercent,
                ];
            }
        }

        // Ordena pelos que tiveram maior queda percentual em quantidade
        usort($losingProducts, fn ($a, $b) => $a['quantity_change_percent'] <=> $b['quantity_change_percent']);

        return array_slice($losingProducts, 0, $limit);
    }

    /**
     * Vendas consolidadas por categoria em um período.
     */
    public function getSalesByCategory(string $from, string $to): array
    {
        $records = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.status', Order::STATUS_DELIVERED)
            ->whereBetween('orders.ordered_at', [$from, $to])
            ->select('products.category')
            ->selectRaw('SUM(order_items.quantity) as total_quantity')
            ->selectRaw('SUM(order_items.subtotal) as total_revenue')
            ->selectRaw('COUNT(DISTINCT orders.id) as orders_count')
            ->groupBy('products.category')
            ->orderByDesc('total_revenue')
            ->get();

        $totalRevenueAll = $records->sum('total_revenue');

        return $records->map(function ($row) use ($totalRevenueAll) {
            $rev = round((float) $row->total_revenue, 2);
            $share = $totalRevenueAll > 0 ? round(($rev / $totalRevenueAll) * 100, 1) : 0.0;

            return [
                'category'       => $row->category,
                'total_quantity' => (int) $row->total_quantity,
                'total_revenue'  => $rev,
                'orders_count'   => (int) $row->orders_count,
                'share_percent'  => $share,
            ];
        })->toArray();
    }

    /**
     * Compara performance de categorias entre dois períodos.
     * Identifica qual categoria teve a maior queda ou maior crescimento.
     */
    public function compareCategories(
        string $currentFrom,
        string $currentTo,
        string $previousFrom,
        string $previousTo
    ): array {
        $current = collect($this->getSalesByCategory($currentFrom, $currentTo))->keyBy('category');
        $previous = collect($this->getSalesByCategory($previousFrom, $previousTo))->keyBy('category');

        $allCategories = $current->keys()->merge($previous->keys())->unique();

        $comparison = [];
        foreach ($allCategories as $cat) {
            $curr = $current->get($cat, ['total_revenue' => 0.0, 'total_quantity' => 0]);
            $prev = $previous->get($cat, ['total_revenue' => 0.0, 'total_quantity' => 0]);

            $revDiff = $curr['total_revenue'] - $prev['total_revenue'];
            $revGrowth = $prev['total_revenue'] > 0
                ? round(($revDiff / $prev['total_revenue']) * 100, 1)
                : 0.0;

            $qtyDiff = $curr['total_quantity'] - $prev['total_quantity'];
            $qtyGrowth = $prev['total_quantity'] > 0
                ? round(($qtyDiff / $prev['total_quantity']) * 100, 1)
                : 0.0;

            $comparison[] = [
                'category'               => $cat,
                'current_revenue'        => $curr['total_revenue'],
                'previous_revenue'       => $prev['total_revenue'],
                'revenue_diff'           => round($revDiff, 2),
                'revenue_growth_percent' => $revGrowth,
                'current_quantity'       => $curr['total_quantity'],
                'previous_quantity'      => $prev['total_quantity'],
                'quantity_growth_percent'=> $qtyGrowth,
            ];
        }

        usort($comparison, fn ($a, $b) => $a['revenue_growth_percent'] <=> $b['revenue_growth_percent']);

        return [
            'categories'     => $comparison,
            'biggest_drop'   => !empty($comparison) ? $comparison[0] : null,
            'biggest_growth' => !empty($comparison) ? end($comparison) : null,
        ];
    }

    /**
     * Identifica produtos com alto potencial de alavancagem de ticket médio
     * (produtos de maior valor agregado como combos e itens familiares).
     */
    public function getHighTicketPotentialProducts(): array
    {
        return DB::table('products')
            ->where('is_active', true)
            ->where('price', '>=', 40.00)
            ->orderByDesc('price')
            ->select('id', 'name', 'category', 'price')
            ->get()
            ->map(fn ($p) => [
                'product_id' => $p->id,
                'name'       => $p->name,
                'category'   => $p->category,
                'price'      => round((float) $p->price, 2),
            ])
            ->toArray();
    }
}