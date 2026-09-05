<?php

namespace Database\Factories;

use App\Models\OrderItem;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * OrderItemFactory
 *
 * Gera itens de pedido com subtotal calculado automaticamente.
 * O unit_price é copiado do produto no momento do pedido (snapshot),
 * garantindo imutabilidade histórica mesmo com mudanças de preço futuras.
 *
 * Usado principalmente nos testes; a criação real de itens é feita
 * pelo DeliverySeeder com controle de produto e quantidade.
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $product  = Product::inRandomOrder()->first() ?? Product::factory()->create();
        $quantity = $this->faker->numberBetween(1, 4);
        $price    = (float) $product->price;
        $subtotal = round($quantity * $price, 2);

        return [
            'order_id'   => Order::factory(),
            'product_id' => $product->id,
            'quantity'   => $quantity,
            'unit_price' => $price,
            'subtotal'   => $subtotal,
        ];
    }

    /** Define o produto explicitamente */
    public function forProduct(Product $product): static
    {
        return $this->state(function () use ($product) {
            $quantity = $this->faker->numberBetween(1, 5);
            $price    = (float) $product->price;
            return [
                'product_id' => $product->id,
                'quantity'   => $quantity,
                'unit_price' => $price,
                'subtotal'   => round($quantity * $price, 2),
            ];
        });
    }

    /** Define a quantidade explicitamente */
    public function withQuantity(int $qty): static
    {
        return $this->state(function (array $attrs) use ($qty) {
            $subtotal = round($qty * (float) $attrs['unit_price'], 2);
            return [
                'quantity' => $qty,
                'subtotal' => $subtotal,
            ];
        });
    }
}