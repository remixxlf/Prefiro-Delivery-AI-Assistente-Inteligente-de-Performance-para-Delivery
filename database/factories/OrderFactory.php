<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

/**
 * OrderFactory
 *
 * A lógica de sazonalidade e distribuição temporal está no
 * DeliverySeeder, que controla quando cada pedido é gerado.
 *
 * Esta factory gera pedidos individuais com dados financeiros
 * realistas. O campo ordered_at é passado pelo seeder.
 *
 * Fórmula de total:
 *   total = (subtotal_items - discount) + delivery_fee
 *   O total aqui é um placeholder; o seeder recalcula após
 *   criar os order_items.
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    private static array $paymentMethods = [
        'pix'             => 45, // 45% das vendas via Pix
        'cartao_credito'  => 30, // 30% cartão crédito
        'cartao_debito'   => 15, // 15% cartão débito
        'dinheiro'        => 10, // 10% dinheiro
    ];

    public function definition(): array
    {
        $paymentMethod = $this->weightedRandom(self::$paymentMethods);

        // Desconto: 40% dos pedidos têm algum desconto
        $hasDiscount = $this->faker->boolean(40);
        $discount    = $hasDiscount
            ? $this->faker->randomFloat(2, 2.00, 15.00)
            : 0.00;

        // Taxa de entrega: varia por bairro/distância (simulado)
        $deliveryFee = $this->faker->randomFloat(2, 3.00, 12.00);

        return [
            'customer_id'    => Customer::factory(),
            'ordered_at'     => $this->faker->dateTimeBetween('-14 months', 'now'),
            'status'         => 'delivered', // padrão; seeder varia alguns
            'discount'       => $discount,
            'delivery_fee'   => $deliveryFee,
            'total'          => 0.00, // recalculado após order_items
            'payment_method' => $paymentMethod,
            'notes'          => $this->faker->boolean(15)
                ? $this->faker->randomElement([
                    'Sem cebola, por favor.',
                    'Pode deixar na portaria.',
                    'Ligar ao chegar.',
                    'Sem pimenta.',
                    'Talheres descartáveis, por favor.',
                ])
                : null,
        ];
    }

    // ── States ─────────────────────────────────────────────────────────

    /** Pedido entregue (usado para análise de faturamento) */
    public function delivered(): static
    {
        return $this->state(fn () => ['status' => Order::STATUS_DELIVERED]);
    }

    /** Pedido cancelado (excluído das análises financeiras) */
    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => Order::STATUS_CANCELLED]);
    }

    /** Pedido com desconto generoso */
    public function withDiscount(float $amount): static
    {
        return $this->state(fn () => ['discount' => $amount]);
    }

    /** Pedido em data específica */
    public function atDate(Carbon $date): static
    {
        return $this->state(fn () => ['ordered_at' => $date]);
    }

    // ── Helpers ────────────────────────────────────────────────────────

    /**
     * Sorteia um elemento com base em pesos (probabilidades percentuais).
     * Ex.: ['pix' => 45, 'dinheiro' => 10] → sorteia proporcionalmente.
     */
    private function weightedRandom(array $weights): string
    {
        $total = array_sum($weights);
        $rand  = $this->faker->numberBetween(1, $total);
        $cumulative = 0;

        foreach ($weights as $item => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                return $item;
            }
        }

        return array_key_first($weights);
    }
}