<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

/**
 * CustomerFactory
 *
 * Gera clientes com histórico variado:
 *  - Clientes ativos (compraram recentemente)
 *  - Clientes em risco (15–30 dias sem comprar)
 *  - Clientes inativos (31–90 dias sem comprar) — alvo de campanhas
 *  - Clientes perdidos (> 90 dias sem comprar)
 *
 * first_order_at e last_order_at são definidos nos seeders,
 * após os pedidos serem criados, para refletir dados reais.
 * Aqui usamos valores placeholder que serão sobrescritos.
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /** Nomes brasileiros para realismo */
    private static array $firstNames = [
        'Ana', 'Carlos', 'Fernanda', 'João', 'Mariana', 'Pedro', 'Juliana',
        'Rafael', 'Camila', 'Lucas', 'Beatriz', 'Mateus', 'Larissa', 'Thiago',
        'Amanda', 'Gustavo', 'Priscila', 'Felipe', 'Vanessa', 'Diego',
        'Roberta', 'Eduardo', 'Patrícia', 'Henrique', 'Aline', 'Leonardo',
        'Renata', 'Bruno', 'Carla', 'Fábio', 'Sônia', 'André', 'Cláudia',
        'Rodrigo', 'Daniela', 'Marcelo', 'Tatiane', 'Alexandre', 'Simone',
        'Vinícius', 'Karina', 'Paulo', 'Letícia', 'Leandro', 'Gabriela',
        'Márcio', 'Cristiane', 'Sandro', 'Natália', 'Adriano',
    ];

    private static array $lastNames = [
        'Silva', 'Santos', 'Oliveira', 'Souza', 'Lima', 'Pereira', 'Costa',
        'Ferreira', 'Rodrigues', 'Almeida', 'Nascimento', 'Carvalho', 'Gomes',
        'Martins', 'Araújo', 'Melo', 'Barbosa', 'Ribeiro', 'Rocha', 'Pinto',
        'Cardoso', 'Teixeira', 'Moreira', 'Nunes', 'Correia', 'Medeiros',
        'Freitas', 'Castro', 'Monteiro', 'Cavalcanti',
    ];

    public function definition(): array
    {
        $firstName = $this->faker->randomElement(self::$firstNames);
        $lastName  = $this->faker->randomElement(self::$lastNames);

        // Gera DDD realistas de cidades brasileiras
        $ddd    = $this->faker->randomElement(['11', '21', '31', '41', '51', '61', '71', '81', '85', '92']);
        $number = '9' . $this->faker->numerify('####-####');
        $phone  = "({$ddd}) {$number}";

        return [
            'name'           => "{$firstName} {$lastName}",
            'phone'          => $phone,
            // Datas serão atualizadas pelo seeder após criar os pedidos
            'first_order_at' => null,
            'last_order_at'  => null,
        ];
    }

    // ── States para diferentes perfis de cliente ───────────────────────

    /** Cliente ativo: pedido nos últimos 15 dias */
    public function active(): static
    {
        return $this->state(fn () => [
            'last_order_at' => $this->faker->dateTimeBetween('-15 days', 'now'),
        ]);
    }

    /** Cliente em risco: sem pedido há 15–30 dias */
    public function atRisk(): static
    {
        return $this->state(fn () => [
            'last_order_at' => $this->faker->dateTimeBetween('-30 days', '-16 days'),
        ]);
    }

    /** Cliente inativo: sem pedido há 31–90 dias (alvo de campanha) */
    public function inactive(): static
    {
        return $this->state(fn () => [
            'last_order_at' => $this->faker->dateTimeBetween('-90 days', '-31 days'),
        ]);
    }

    /** Cliente perdido: sem pedido há mais de 90 dias */
    public function lost(): static
    {
        return $this->state(fn () => [
            'last_order_at' => $this->faker->dateTimeBetween('-18 months', '-91 days'),
        ]);
    }
}