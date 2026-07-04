<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $filename = $this->faker->slug(3).'.md';

        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'filename' => $filename,
            'disk_path' => 'documents/'.$filename,
            'source' => 'upload',
            'file_size' => $this->faker->numberBetween(100, 50000),
        ];
    }

    /**
     * A document sourced from the project root.
     */
    public function fromProjectRoot(): static
    {
        return $this->state(fn (): array => [
            'source' => 'project_root',
            'disk_path' => 'README.md',
        ]);
    }

    /**
     * A document sourced from the docs/ folder.
     */
    public function fromDocsFolder(): static
    {
        return $this->state(fn (): array => [
            'source' => 'docs_folder',
            'disk_path' => 'docs/example.md',
        ]);
    }
}
