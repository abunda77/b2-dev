<?php

namespace Database\Factories;

use App\Models\Warga;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warga>
 */
class WargaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nik' => $this->faker->unique()->numerify('################'),
            'nama' => $this->faker->name(),
            'alamat' => $this->faker->address(),
            'pas_foto' => 'warga/pas_foto/'.$this->faker->uuid().'.jpg',
            'dokumen' => $this->faker->boolean(50)
                ? 'warga/dokumen/'.$this->faker->uuid().'.jpg'
                : null,
        ];
    }
}
