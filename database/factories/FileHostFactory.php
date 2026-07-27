<?php

namespace Database\Factories;

use App\Models\FileHost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FileHost>
 */
class FileHostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ext = $this->faker->randomElement(['jpg', 'png', 'pdf', 'zip', 'mp4']);
        $originalName = $this->faker->word().'.'.$ext;

        return [
            'nama' => $this->faker->sentence(3),
            'original_name' => $originalName,
            'mime_type' => $this->faker->randomElement([
                'image/jpeg',
                'image/png',
                'application/pdf',
                'application/zip',
                'video/mp4',
            ]),
            'size' => $this->faker->numberBetween(1024, 1073741824),
            'path' => 'file-host/'.$this->faker->uuid().'.'.$ext,
            'disk' => 'b2',
        ];
    }
}
