<?php

namespace Database\Factories;

use App\Models\Faktur;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faktur>
 */
class FakturFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'nomor_faktur' => 'INV-'.$this->faker->unique()->numerify('######'),
            'nama' => $this->faker->name(),
            'nominal' => $this->faker->numberBetween(50000, 5000000),
            'items' => [
                ['description' => 'Jasa konsultasi', 'qty' => 1, 'price' => 150000, 'subtotal' => 150000],
                ['description' => 'Biaya administrasi', 'qty' => 1, 'price' => 50000, 'subtotal' => 50000],
            ],
            'terbilang' => 'Seratus ribu rupiah',
            'memo' => $this->faker->optional()->sentence(),
            'paper_size' => $this->faker->randomElement(['a4', 'half_a4', 'third_a4']),
            'logo_path' => null,
            'pdf_path' => 'faktur/dummy.pdf',
        ];
    }
}
