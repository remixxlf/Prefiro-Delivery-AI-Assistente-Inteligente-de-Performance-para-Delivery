<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * ProductFactory
 *
 * Gera produtos realistas de um restaurante delivery brasileiro.
 * Os produtos reais são definidos no seeder (DeliverySeeder),
 * que usa listas fixas para garantir consistência de categorias e preços.
 *
 * Esta factory é usada para testes unitários com produtos aleatórios.
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /** Catálogo de produtos por categoria para uso nos testes */
    private static array $catalog = [
        'Marmitas' => [
            ['name' => 'Marmita Executiva Frango Grelhado', 'price_range' => [24.90, 32.90]],
            ['name' => 'Marmita Executiva Carne Moída',     'price_range' => [22.90, 29.90]],
            ['name' => 'Marmita Fit Frango com Legumes',    'price_range' => [26.90, 34.90]],
            ['name' => 'Marmita Fitness Atum',              'price_range' => [28.90, 36.90]],
            ['name' => 'Marmita Veg Proteica',              'price_range' => [23.90, 30.90]],
        ],
        'Lanches' => [
            ['name' => 'X-Burguer Artesanal',   'price_range' => [18.90, 26.90]],
            ['name' => 'X-Bacon Duplo',          'price_range' => [24.90, 32.90]],
            ['name' => 'X-Frango Crocante',      'price_range' => [19.90, 25.90]],
            ['name' => 'Hot Dog Especial',        'price_range' => [14.90, 19.90]],
            ['name' => 'Wrap de Frango',          'price_range' => [16.90, 22.90]],
        ],
        'Combos' => [
            ['name' => 'Combo Família (4 marmitas)',   'price_range' => [79.90, 99.90]],
            ['name' => 'Combo Casal (2 marmitas)',     'price_range' => [44.90, 56.90]],
            ['name' => 'Combo Fit Semana (5 un.)',     'price_range' => [109.90, 139.90]],
            ['name' => 'Combo Lanche + Bebida',        'price_range' => [28.90, 38.90]],
            ['name' => 'Combo Kids',                   'price_range' => [19.90, 25.90]],
        ],
        'Bebidas' => [
            ['name' => 'Suco Natural 500ml',     'price_range' => [8.90, 13.90]],
            ['name' => 'Refrigerante Lata 350ml','price_range' => [5.90, 8.90]],
            ['name' => 'Água Mineral 500ml',     'price_range' => [3.50, 5.00]],
            ['name' => 'Isotônico 500ml',        'price_range' => [7.90, 10.90]],
            ['name' => 'Vitamina de Frutas',     'price_range' => [11.90, 15.90]],
        ],
        'Sobremesas' => [
            ['name' => 'Pudim de Leite Condensado', 'price_range' => [8.90, 12.90]],
            ['name' => 'Brownie de Chocolate',       'price_range' => [9.90, 13.90]],
            ['name' => 'Mousse de Maracujá',         'price_range' => [8.90, 11.90]],
            ['name' => 'Açaí 300ml',                 'price_range' => [14.90, 19.90]],
            ['name' => 'Pote de Sorvete 500ml',      'price_range' => [16.90, 22.90]],
        ],
    ];

    public function definition(): array
    {
        $category = $this->faker->randomElement(array_keys(self::$catalog));
        $product  = $this->faker->randomElement(self::$catalog[$category]);

        return [
            'name'      => $product['name'],
            'category'  => $category,
            'price'     => $this->faker->randomFloat(
                2,
                $product['price_range'][0],
                $product['price_range'][1]
            ),
            'is_active' => true,
        ];
    }

    /** Produto desativado (saiu do cardápio) */
    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /** Produto de uma categoria específica */
    public function ofCategory(string $category): static
    {
        $product = $this->faker->randomElement(self::$catalog[$category] ?? [
            ['name' => 'Produto ' . $category, 'price_range' => [10, 50]],
        ]);

        return $this->state(fn () => [
            'category' => $category,
            'name'     => $product['name'],
            'price'    => $this->faker->randomFloat(2, $product['price_range'][0], $product['price_range'][1]),
        ]);
    }
}