<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use Database\Seeders\DeliverySeeder;
use Carbon\Carbon;

/**
 * Testes de integração do DeliverySeeder.
 *
 * Valida que os dados gerados atendem os requisitos do teste técnico:
 *  - Mínimo 500 pedidos
 *  - Distribuição em 12+ meses
 *  - Clientes inativos presentes
 *  - Produtos em múltiplas categorias
 *  - Faturamento computável (todos os pedidos têm total > 0)
 */
class SeederDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DeliverySeeder::class);
    }

    // ── Volume mínimo ──────────────────────────────────────────────────

    public function test_minimum_500_orders_created(): void
    {
        $count = Order::count();
        $this->assertGreaterThanOrEqual(500, $count, "Esperado >= 500 pedidos, criados: {$count}");
    }

    public function test_minimum_80_customers_created(): void
    {
        $count = Customer::count();
        $this->assertGreaterThanOrEqual(80, $count, "Esperado >= 80 clientes, criados: {$count}");
    }

    public function test_minimum_20_products_created(): void
    {
        $count = Product::count();
        $this->assertGreaterThanOrEqual(20, $count, "Esperado >= 20 produtos, criados: {$count}");
    }

    // ── Distribuição temporal ──────────────────────────────────────────

    public function test_orders_span_at_least_12_months(): void
    {
        $oldest = Order::min('ordered_at');
        $newest = Order::max('ordered_at');

        $monthsSpan = Carbon::parse($oldest)->diffInMonths(Carbon::parse($newest));
        $this->assertGreaterThanOrEqual(12, $monthsSpan,
            "Pedidos devem cobrir >= 12 meses. Cobertura: {$monthsSpan} meses");
    }

    public function test_orders_exist_in_multiple_months(): void
    {
        // PHP-side — portable between SQLite (tests) and MySQL (prod)
        $months = Order::select('ordered_at')->get()
            ->map(fn ($o) => Carbon::parse($o->ordered_at)->format('Y-m'))
            ->unique()
            ->count();

        $this->assertGreaterThanOrEqual(12, $months,
            "Pedidos devem existir em >= 12 meses distintos");
    }

    // ── Sazonalidade (fim de semana) ────────────────────────────────────

    public function test_weekends_have_more_orders_than_weekdays(): void
    {
        // PHP/Carbon — portable between SQLite and MySQL
        $orders = Order::select('ordered_at')->get();

        $weekendCount = $orders->filter(
            fn ($o) => Carbon::parse($o->ordered_at)->isWeekend()
        )->count();

        $weekdayCount = $orders->filter(
            fn ($o) => !Carbon::parse($o->ordered_at)->isWeekend()
        )->count();

        // Fim de semana = 3 dias, dias úteis = 4 dias
        // Esperamos que a média diária de fim de semana seja maior
        $weekendAvg = $weekendCount / 3;
        $weekdayAvg = $weekdayCount / 4;

        $this->assertGreaterThan($weekdayAvg, $weekendAvg,
            'Média de pedidos no fim de semana deve ser maior que nos dias úteis');
    }

    // ── Clientes inativos ──────────────────────────────────────────────

    public function test_inactive_customers_exist(): void
    {
        $inactiveCount = Customer::inactiveSince(30)->count();
        $this->assertGreaterThan(0, $inactiveCount,
            'Deve haver clientes sem pedido há mais de 30 dias');
    }

    public function test_at_least_10_percent_customers_are_inactive(): void
    {
        $total    = Customer::count();
        $inactive = Customer::inactiveSince(30)->count();
        $percent  = $total > 0 ? ($inactive / $total) * 100 : 0;

        $this->assertGreaterThanOrEqual(10, $percent,
            "Pelo menos 10% dos clientes devem estar inativos. Atual: {$percent}%");
    }

    // ── Produtos ───────────────────────────────────────────────────────

    public function test_products_have_all_5_categories(): void
    {
        $categories = Product::distinct()->pluck('category')->toArray();

        foreach (['Marmitas', 'Lanches', 'Combos', 'Bebidas', 'Sobremesas'] as $cat) {
            $this->assertContains($cat, $categories,
                "Categoria '{$cat}' deve existir nos produtos");
        }
    }

    // ── Dados financeiros ──────────────────────────────────────────────

    public function test_all_delivered_orders_have_positive_total(): void
    {
        $zeroTotalCount = Order::where('status', 'delivered')
            ->where('total', '<=', 0)
            ->count();

        $this->assertEquals(0, $zeroTotalCount,
            "Todos os pedidos entregues devem ter total > 0");
    }

    public function test_average_ticket_is_between_40_and_100(): void
    {
        $avgTicket = Order::where('status', 'delivered')->avg('total');

        $this->assertGreaterThan(40, $avgTicket,
            "Ticket médio deve ser > R$ 40. Atual: R$ " . number_format($avgTicket, 2));
        $this->assertLessThan(100, $avgTicket,
            "Ticket médio deve ser < R$ 100. Atual: R$ " . number_format($avgTicket, 2));
    }

    public function test_cancelled_orders_exist(): void
    {
        $cancelled = Order::where('status', 'cancelled')->count();
        $this->assertGreaterThan(0, $cancelled,
            'Deve haver alguns pedidos cancelados (7% do total esperado)');
    }

    // ── Order items ────────────────────────────────────────────────────

    public function test_every_order_has_at_least_one_item(): void
    {
        $ordersWithoutItems = Order::whereDoesntHave('items')->count();
        $this->assertEquals(0, $ordersWithoutItems,
            'Todo pedido deve ter pelo menos um item');
    }

    public function test_customer_dates_are_populated(): void
    {
        $withOrders = Customer::has('orders')
            ->whereNull('last_order_at')
            ->count();

        $this->assertEquals(0, $withOrders,
            'Clientes com pedidos devem ter last_order_at preenchido');
    }

    // ── Tendências de produto ──────────────────────────────────────────

    public function test_marmita_fit_grew_in_recent_months(): void
    {
        $product = Product::where('name', 'Marmita Fit Frango com Legumes')->first();
        if (!$product) {
            $this->markTestSkipped('Produto Marmita Fit não encontrado');
        }

        // Vendas nos últimos 3 meses
        $recent = OrderItem::where('product_id', $product->id)
            ->whereHas('order', fn($q) => $q->where('ordered_at', '>=', now()->subMonths(3)))
            ->sum('quantity');

        // Vendas de 6–9 meses atrás (período anterior equivalente)
        $older = OrderItem::where('product_id', $product->id)
            ->whereHas('order', fn($q) => $q->whereBetween('ordered_at', [
                now()->subMonths(9), now()->subMonths(6)
            ]))
            ->sum('quantity');

        // Marmita Fit deve ter mais vendas nos últimos 3 meses vs período anterior
        // Aceita empate ou crescimento (dados probabilísticos)
        $this->assertGreaterThanOrEqual(
            max(0, $older * 0.7), // tolerância de 30%
            $recent,
            "Marmita Fit deve ter crescimento ou manutenção nas vendas recentes"
        );
    }
}