<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * DeliverySeeder
 *
 * Popula o banco com dados fictícios realistas de uma operação de delivery,
 * com padrões suficientes para a IA identificar:
 *
 *  SAZONALIDADE:
 *   - Picos nas sextas e sábados (+60% do volume normal)
 *   - Domingos com volume médio-alto (+30%)
 *   - Queda em agosto (férias de julho acabaram, menos movimento)
 *   - Crescimento forte em novembro/dezembro (datas comemorativas)
 *   - Janeiro com queda (pós-festas, calor)
 *
 *  COMPORTAMENTO DE CLIENTES:
 *   - 30% clientes recorrentes (compram 2–3x/mês) → receita estável
 *   - 40% clientes ocasionais (1x/mês ou menos)
 *   - 20% clientes inativos há 31–90 dias → alvo de campanha
 *   - 10% clientes perdidos (> 90 dias)
 *
 *  TENDÊNCIAS DE PRODUTOS:
 *   - "Marmita Fit" ganhando mercado nos últimos 3 meses
 *   - "X-Burguer Artesanal" perdendo participação (queda de 23%)
 *   - Bebidas com crescimento constante
 *   - Combos Família: picos nos fins de semana
 *
 *  FINANCEIRO:
 *   - Ticket médio oscila entre R$ 55–75
 *   - Queda de 14% no faturamento no mês de agosto
 *   - Crescimento de 18% em novembro vs outubro
 */
class DeliverySeeder extends Seeder
{
    // ── Data de corte da simulação (congelada para garantir determinismo e reprodutibilidade)
    public const SIMULATED_DATE = '2026-09-05 12:00:00';

    // ── Catálogo fixo de produtos (garante consistência nas análises) ───
    private static array $productCatalog = [
        // Marmitas
        ['name' => 'Marmita Executiva Frango Grelhado', 'category' => 'Marmitas',   'price' => 27.90],
        ['name' => 'Marmita Executiva Carne Moída',     'category' => 'Marmitas',   'price' => 25.90],
        ['name' => 'Marmita Fit Frango com Legumes',    'category' => 'Marmitas',   'price' => 31.90],
        ['name' => 'Marmita Fitness Atum',               'category' => 'Marmitas',   'price' => 33.90],
        ['name' => 'Marmita Vegetariana Proteica',       'category' => 'Marmitas',   'price' => 29.90],
        ['name' => 'Marmita Carne Assada',               'category' => 'Marmitas',   'price' => 28.90],
        // Lanches
        ['name' => 'X-Burguer Artesanal',               'category' => 'Lanches',    'price' => 22.90],
        ['name' => 'X-Bacon Duplo',                     'category' => 'Lanches',    'price' => 28.90],
        ['name' => 'X-Frango Crocante',                 'category' => 'Lanches',    'price' => 21.90],
        ['name' => 'Hot Dog Especial',                   'category' => 'Lanches',    'price' => 16.90],
        ['name' => 'Wrap de Frango Grelhado',            'category' => 'Lanches',    'price' => 19.90],
        // Combos
        ['name' => 'Combo Família (4 marmitas)',         'category' => 'Combos',     'price' => 89.90],
        ['name' => 'Combo Casal (2 marmitas)',           'category' => 'Combos',     'price' => 49.90],
        ['name' => 'Combo Fit Semana (5 un.)',           'category' => 'Combos',     'price' => 119.90],
        ['name' => 'Combo Lanche + Bebida',              'category' => 'Combos',     'price' => 32.90],
        // Bebidas
        ['name' => 'Suco Natural 500ml',                 'category' => 'Bebidas',    'price' => 10.90],
        ['name' => 'Refrigerante Lata 350ml',            'category' => 'Bebidas',    'price' => 6.90],
        ['name' => 'Água Mineral 500ml',                 'category' => 'Bebidas',    'price' => 4.00],
        ['name' => 'Vitamina de Frutas 400ml',           'category' => 'Bebidas',    'price' => 13.90],
        ['name' => 'Isotônico 500ml',                    'category' => 'Bebidas',    'price' => 8.90],
        // Sobremesas
        ['name' => 'Pudim de Leite Condensado',          'category' => 'Sobremesas', 'price' => 10.90],
        ['name' => 'Brownie de Chocolate',               'category' => 'Sobremesas', 'price' => 11.90],
        ['name' => 'Mousse de Maracujá',                 'category' => 'Sobremesas', 'price' => 9.90],
        ['name' => 'Açaí 300ml',                         'category' => 'Sobremesas', 'price' => 16.90],
        ['name' => 'Pote de Sorvete 500ml',              'category' => 'Sobremesas', 'price' => 19.90],
    ];

    /**
     * Volume de pedidos por mês (relativo — 1.0 = normal).
     * Cobre 15 meses para análises de tendência de longo prazo.
     *
     * Referência: hoje = setembro/2026
     * Meses passados de forma descendente:
     */
    private static array $monthlyVolume = [
        '2025-07' => 0.70,  // Julho 2025 — início da operação, baixo volume
        '2025-08' => 0.80,  // Agosto — crescimento inicial
        '2025-09' => 0.90,  // Setembro — estabilizando
        '2025-10' => 1.00,  // Outubro — referência (mês base)
        '2025-11' => 1.18,  // Novembro — alta (+18%) datas comemorativas
        '2025-12' => 1.25,  // Dezembro — pico de fim de ano (+25%)
        '2026-01' => 0.72,  // Janeiro — queda pós-festas (-28%)
        '2026-02' => 0.85,  // Fevereiro — recuperação gradual
        '2026-03' => 0.95,  // Março — estável
        '2026-04' => 1.05,  // Abril — leve alta
        '2026-05' => 1.10,  // Maio — Dia das Mães (+10%)
        '2026-06' => 1.00,  // Junho — normal
        '2026-07' => 0.88,  // Julho — queda de julho (férias) (-12%)
        '2026-08' => 0.76,  // Agosto — queda mais acentuada (-24%) ← IA deve detectar
        '2026-09' => 0.90,  // Setembro (mês atual, parcial)
    ];

    /**
     * Multiplicador de volume por dia da semana (0=domingo, 6=sábado).
     * Picos no fim de semana, segunda-feira tem menor movimento.
     */
    private static array $dayOfWeekMultiplier = [
        0 => 1.30, // Domingo — alto (+30%)
        1 => 0.70, // Segunda — baixo (-30%)
        2 => 0.85, // Terça
        3 => 0.90, // Quarta
        4 => 1.00, // Quinta — referência
        5 => 1.60, // Sexta — pico (+60%)
        6 => 1.50, // Sábado — pico (+50%)
    ];

    /**
     * Horários de pico (probabilidade cumulativa de o pedido cair nesse slot).
     * Formato: [hora_inicio => peso_relativo]
     */
    private static array $hourlyDistribution = [
        7  => 2,  // Café da manhã / marmita do dia
        11 => 20, // Almoço — maior pico
        12 => 25,
        13 => 18,
        14 => 8,
        17 => 5,  // Lanche da tarde
        18 => 10, // Jantar
        19 => 15,
        20 => 12,
        21 => 8,
        22 => 4,
    ];

    public function run(): void
    {
        // Define seed determinístico para que toda execução produza dados idênticos
        mt_srand(20260905);
        srand(20260905);

        // Verificação de idempotência: se o banco já contém pedidos e clientes, preserva os dados
        if (Order::count() > 0 && Customer::count() >= 100) {
            $this->command->info("ℹ️ Banco de dados já possui registros populados. Mantendo integridade.");
            return;
        }

        $this->command->info('🍽️  Iniciando DeliverySeeder...');

        DB::transaction(function () {
            $this->command->info('  → Criando produtos...');
            $products = $this->seedProducts();

            $this->command->info('  → Criando clientes...');
            $customers = $this->seedCustomers();

            $this->command->info('  → Criando pedidos com sazonalidade...');
            $this->seedOrders($customers, $products);

            $this->command->info('  → Atualizando datas de first/last order dos clientes...');
            $this->updateCustomerOrderDates();
        });

        $totalOrders = Order::count();
        $totalRevenue = Order::where('status', 'delivered')->sum('total');
        $this->command->info("✅ Seeder concluído!");
        $this->command->info("   Clientes: " . Customer::count());
        $this->command->info("   Produtos: " . Product::count());
        $this->command->info("   Pedidos:  {$totalOrders}");
        $this->command->info("   Faturamento total: R$ " . number_format($totalRevenue, 2, ',', '.'));
    }

    // ── Produtos ────────────────────────────────────────────────────────

    private function seedProducts(): array
    {
        $products = [];
        foreach (self::$productCatalog as $data) {
            $products[] = Product::create($data);
        }
        return $products;
    }

    // ── Clientes ────────────────────────────────────────────────────────

    private function seedCustomers(): array
    {
        $customers = [];

        // Nomes brasileiros — lista garantida sem duplicatas de telefone
        $names = $this->getBrazilianNames(100);
        shuffle($names);

        $phones = $this->generateUniquePhones(100);

        foreach ($names as $i => $name) {
            $customers[] = Customer::create([
                'name'           => $name,
                'phone'          => $phones[$i],
                'first_order_at' => null, // atualizado depois
                'last_order_at'  => null,
            ]);
        }

        return $customers;
    }

    // ── Pedidos com sazonalidade ────────────────────────────────────────

    private function seedOrders(array $customers, array $products): void
    {
        // Base: 35 pedidos por mês em volume normal (1.0)
        // Resultado esperado: ~600–700 pedidos no total
        $baseOrdersPerMonth = 38;

        // Produtos indexados para seleção ponderada
        $productsByCategory = collect($products)->groupBy('category');

        foreach (self::$monthlyVolume as $yearMonth => $volumeMultiplier) {
            [$year, $month] = explode('-', $yearMonth);
            $year  = (int) $year;
            $month = (int) $month;

            $ordersThisMonth = (int) round($baseOrdersPerMonth * $volumeMultiplier);

            // Gera as datas dos pedidos deste mês (com distribuição dia-da-semana)
            $dates = $this->generateDatesForMonth($year, $month, $ordersThisMonth);

            foreach ($dates as $orderedAt) {
                // Seleciona cliente: 70% clientes recorrentes, 30% qualquer
                $customer = $this->selectCustomer($customers, $orderedAt);

                // Cria o pedido
                $order = $this->createOrder($customer, $orderedAt, $year, $month);

                // Cria 1–4 itens por pedido (média ~2.2 itens)
                $this->createOrderItems($order, $products, $productsByCategory, $year, $month);
            }
        }
    }

    /**
     * Gera datas distribuídas no mês com bias para fim de semana.
     */
    private function generateDatesForMonth(int $year, int $month, int $count): array
    {
        $dates = [];
        $start = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $end   = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        // Limita à data de corte da simulação para manter determinismo temporal
        $simulatedDate = Carbon::parse(self::SIMULATED_DATE);
        if ($start->format('Y-m') === $simulatedDate->format('Y-m')) {
            $end = $simulatedDate->copy()->subDay();
            if ($end->lt($start)) {
                return [];
            }
        } elseif ($start->gt($simulatedDate)) {
            return [];
        }

        // Gera dias candidatos ponderados por dia da semana
        $weightedDays = [];
        $current = $start->copy();
        while ($current->lte($end)) {
            $dayOfWeek = $current->dayOfWeek; // 0=dom, 6=sab
            $weight    = (int) round(self::$dayOfWeekMultiplier[$dayOfWeek] * 10);
            for ($i = 0; $i < $weight; $i++) {
                $weightedDays[] = $current->copy();
            }
            $current->addDay();
        }

        if (empty($weightedDays)) {
            return [];
        }

        // Sorteia $count dias (com repetição permitida = múltiplos pedidos/dia)
        for ($i = 0; $i < $count; $i++) {
            $day   = $weightedDays[array_rand($weightedDays)];
            $hour  = $this->weightedHour();
            $minute = rand(0, 59);

            $dates[] = $day->copy()->setTime($hour, $minute);
        }

        sort($dates);
        return $dates;
    }

    /**
     * Sorteia uma hora do dia conforme distribuição de picos.
     */
    private function weightedHour(): int
    {
        $total  = array_sum(self::$hourlyDistribution);
        $rand   = rand(1, $total);
        $cumul  = 0;
        foreach (self::$hourlyDistribution as $hour => $weight) {
            $cumul += $weight;
            if ($rand <= $cumul) {
                return $hour;
            }
        }
        return 12;
    }

    /**
     * Seleciona cliente com bias para clientes frequentes.
     * 60% do volume vem dos top-40 clientes (clientes recorrentes).
     */
    private function selectCustomer(array $customers, Carbon $date): Customer
    {
        // Divide clientes em "frequentes" (primeiros 40) e "ocasionais"
        $cutoff   = min(40, count($customers) - 1);
        $frequent = array_slice($customers, 0, $cutoff);
        $others   = array_slice($customers, $cutoff);

        if (rand(1, 100) <= 60 && !empty($frequent)) {
            return $frequent[array_rand($frequent)];
        }

        return !empty($others)
            ? $others[array_rand($others)]
            : $frequent[array_rand($frequent)];
    }

    private function createOrder(Customer $customer, Carbon $orderedAt, int $year, int $month): Order
    {
        // 7% dos pedidos são cancelados
        $status = rand(1, 100) <= 7 ? 'cancelled' : 'delivered';

        // Desconto: 35% dos pedidos têm desconto (R$ 2–15)
        $hasDiscount = rand(1, 100) <= 35;
        $discount    = $hasDiscount ? round(rand(200, 1500) / 100, 2) : 0.00;

        // Taxa de entrega: R$ 3–12
        $deliveryFee = round(rand(300, 1200) / 100, 2);

        // Método de pagamento ponderado
        $paymentMethods = ['pix', 'pix', 'pix', 'pix', 'pix',
                           'cartao_credito', 'cartao_credito', 'cartao_credito',
                           'cartao_debito', 'cartao_debito',
                           'dinheiro'];
        $paymentMethod = $paymentMethods[array_rand($paymentMethods)];

        return Order::create([
            'customer_id'    => $customer->id,
            'ordered_at'     => $orderedAt,
            'status'         => $status,
            'discount'       => $discount,
            'delivery_fee'   => $deliveryFee,
            'total'          => 0.00, // recalculado abaixo
            'payment_method' => $paymentMethod,
        ]);
    }

    /**
     * Cria os itens do pedido e atualiza o total.
     *
     * Lógica de tendências de produtos:
     *  - "Marmita Fit" cresce 40% nos últimos 3 meses vs início
     *  - "X-Burguer Artesanal" perde 23% nos últimos 6 meses
     *  - Combos Família têm pico nos fins de semana (dayOfWeek 5,6,0)
     *  - Bebidas acompanham ~60% dos pedidos
     */
    private function createOrderItems(
        Order $order,
        array $products,
        $productsByCategory,
        int $year,
        int $month
    ): void {
        $orderedAt  = Carbon::parse($order->ordered_at);
        $dayOfWeek  = $orderedAt->dayOfWeek;
        $monthsAgo  = Carbon::parse(self::SIMULATED_DATE)->diffInMonths($orderedAt);

        // ── Seleciona produto principal ────────────────────────────────
        $mainProduct = $this->selectMainProduct(
            $products, $productsByCategory, $monthsAgo, $dayOfWeek
        );

        $itemsData = [];
        $usedProductIds = [];

        // Item principal (1 unidade geralmente)
        $mainQty = rand(1, 2);
        $itemsData[] = [
            'product_id' => $mainProduct->id,
            'quantity'   => $mainQty,
            'unit_price' => $mainProduct->price,
        ];
        $usedProductIds[] = $mainProduct->id;

        // Adiciona 0–3 itens extras com probabilidade decrescente
        // ~60% dos pedidos têm bebida, ~30% têm sobremesa
        $extraCount = $this->weightedExtraCount();

        if ($extraCount >= 1 && $productsByCategory->has('Bebidas')) {
            $bebida = $productsByCategory->get('Bebidas')->random();
            if (!in_array($bebida->id, $usedProductIds)) {
                $itemsData[] = ['product_id' => $bebida->id, 'quantity' => rand(1, 2), 'unit_price' => $bebida->price];
                $usedProductIds[] = $bebida->id;
            }
        }

        if ($extraCount >= 2 && rand(1, 100) <= 30 && $productsByCategory->has('Sobremesas')) {
            $sobremesa = $productsByCategory->get('Sobremesas')->random();
            if (!in_array($sobremesa->id, $usedProductIds)) {
                $itemsData[] = ['product_id' => $sobremesa->id, 'quantity' => 1, 'unit_price' => $sobremesa->price];
                $usedProductIds[] = $sobremesa->id;
            }
        }

        if ($extraCount >= 3) {
            // Item adicional qualquer
            $extra = collect($products)->whereNotIn('id', $usedProductIds)->random();
            if ($extra) {
                $itemsData[] = ['product_id' => $extra->id, 'quantity' => 1, 'unit_price' => $extra->price];
            }
        }

        // ── Persiste os itens e recalcula o total ──────────────────────
        $subtotalTotal = 0;
        foreach ($itemsData as $item) {
            $subtotal = round($item['quantity'] * $item['unit_price'], 2);
            OrderItem::create([
                'order_id'   => $order->id,
                'product_id' => $item['product_id'],
                'quantity'   => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'subtotal'   => $subtotal,
            ]);
            $subtotalTotal += $subtotal;
        }

        // Recalcula total do pedido
        $total = round(($subtotalTotal - $order->discount) + $order->delivery_fee, 2);
        $total = max($total, $order->delivery_fee); // nunca negativo
        $order->update(['total' => $total]);
    }

    /**
     * Seleciona produto principal com bias de tendência:
     *  - "Marmita Fit" ganha share nos últimos 3 meses (IA deve detectar crescimento)
     *  - "X-Burguer" perde share nos últimos 6 meses (IA deve detectar queda)
     *  - Combos têm pico no fim de semana (IA deve detectar padrão sazonal)
     */
    private function selectMainProduct($products, $productsByCategory, int $monthsAgo, int $dayOfWeek): Product
    {
        // Fim de semana (sex/sab/dom): 25% de chance de Combo Família
        if (in_array($dayOfWeek, [5, 6, 0]) && rand(1, 100) <= 25) {
            if ($productsByCategory->has('Combos')) {
                return $productsByCategory->get('Combos')->random();
            }
        }

        // Últimos 3 meses: "Marmita Fit" tem 35% de chance de ser escolhida
        if ($monthsAgo <= 3 && rand(1, 100) <= 35 && $productsByCategory->has('Marmitas')) {
            $marmitaFit = collect($products)->firstWhere('name', 'Marmita Fit Frango com Legumes');
            if ($marmitaFit) {
                return $marmitaFit;
            }
        }

        // Mais de 6 meses atrás: X-Burguer tinha mais participação (30%)
        // Nos últimos 6 meses: participação caiu para 10%
        $xBurguer = collect($products)->firstWhere('name', 'X-Burguer Artesanal');
        if ($xBurguer) {
            $xBurguerChance = $monthsAgo > 6 ? 30 : 10; // queda de 23%+
            if (rand(1, 100) <= $xBurguerChance) {
                return $xBurguer;
            }
        }

        // Default: sorteia entre Marmitas (50%), Lanches (30%), Combos (20%)
        $rand = rand(1, 100);
        if ($rand <= 50 && $productsByCategory->has('Marmitas')) {
            return $productsByCategory->get('Marmitas')->random();
        } elseif ($rand <= 80 && $productsByCategory->has('Lanches')) {
            return $productsByCategory->get('Lanches')->random();
        } elseif ($productsByCategory->has('Combos')) {
            return $productsByCategory->get('Combos')->random();
        }

        return collect($products)->random();
    }

    /**
     * Número de itens extras (além do principal).
     * 0 extras: 30% | 1 extra: 35% | 2 extras: 25% | 3 extras: 10%
     */
    private function weightedExtraCount(): int
    {
        $rand = rand(1, 100);
        if ($rand <= 30) return 0;
        if ($rand <= 65) return 1;
        if ($rand <= 90) return 2;
        return 3;
    }

    // ── Atualiza first_order_at e last_order_at ─────────────────────────

    private function updateCustomerOrderDates(): void
    {
        $aggregates = DB::table('orders')
            ->where('status', '!=', 'cancelled')
            ->groupBy('customer_id')
            ->select('customer_id', DB::raw('MIN(ordered_at) as first_order'), DB::raw('MAX(ordered_at) as last_order'))
            ->get();

        foreach ($aggregates as $row) {
            DB::table('customers')->where('id', $row->customer_id)->update([
                'first_order_at' => $row->first_order,
                'last_order_at'  => $row->last_order,
            ]);
        }
    }

    // ── Helpers de dados ────────────────────────────────────────────────

    private function getBrazilianNames(int $count): array
    {
        $firstNames = [
            'Ana', 'Carlos', 'Fernanda', 'João', 'Mariana', 'Pedro', 'Juliana',
            'Rafael', 'Camila', 'Lucas', 'Beatriz', 'Mateus', 'Larissa', 'Thiago',
            'Amanda', 'Gustavo', 'Priscila', 'Felipe', 'Vanessa', 'Diego',
            'Roberta', 'Eduardo', 'Patrícia', 'Henrique', 'Aline', 'Leonardo',
            'Renata', 'Bruno', 'Carla', 'Fábio', 'Sônia', 'André', 'Cláudia',
            'Rodrigo', 'Daniela', 'Marcelo', 'Tatiane', 'Alexandre', 'Simone',
            'Vinícius', 'Karina', 'Paulo', 'Letícia', 'Leandro', 'Gabriela',
            'Márcio', 'Cristiane', 'Sandro', 'Natália', 'Adriano', 'Eliane',
            'Roberto', 'Cintia', 'Wander', 'Andréia', 'Cleiton', 'Sabrina',
            'Flávio', 'Mônica', 'Celso', 'Bruna', 'Éverton', 'Luciana',
            'Wendell', 'Alessandra', 'Deivid', 'Rosane', 'Fabrício', 'Bianca',
            'Edson', 'Vera', 'Gilson', 'Ariane', 'Eder', 'Fernandinha',
        ];

        $lastNames = [
            'Silva', 'Santos', 'Oliveira', 'Souza', 'Lima', 'Pereira', 'Costa',
            'Ferreira', 'Rodrigues', 'Almeida', 'Nascimento', 'Carvalho', 'Gomes',
            'Martins', 'Araújo', 'Melo', 'Barbosa', 'Ribeiro', 'Rocha', 'Pinto',
            'Cardoso', 'Teixeira', 'Moreira', 'Nunes', 'Correia', 'Medeiros',
            'Freitas', 'Castro', 'Monteiro', 'Cavalcanti', 'Campos', 'Lopes',
            'Miranda', 'Fonseca', 'Guimarães', 'Cunha', 'Borges', 'Ramos',
            'Mendes', 'Andrade', 'Batista', 'Vieira', 'Dias', 'Marques',
        ];

        $names = [];
        $used  = [];
        $attempts = 0;

        while (count($names) < $count && $attempts < $count * 3) {
            $name = $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
            if (!in_array($name, $used)) {
                $names[] = $name;
                $used[]  = $name;
            }
            $attempts++;
        }

        // Preenche com nomes numerados se necessário
        while (count($names) < $count) {
            $names[] = 'Cliente ' . (count($names) + 1);
        }

        return $names;
    }

    private function generateUniquePhones(int $count): array
    {
        $ddds   = ['11', '21', '31', '41', '51', '61', '71', '81', '85', '92', '62', '67'];
        $phones = [];
        $used   = [];

        while (count($phones) < $count) {
            $ddd    = $ddds[array_rand($ddds)];
            $number = '9' . str_pad(rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
            $phone  = "({$ddd}) {$number}";
            if (!in_array($phone, $used)) {
                $phones[] = $phone;
                $used[]   = $phone;
            }
        }

        return $phones;
    }
}