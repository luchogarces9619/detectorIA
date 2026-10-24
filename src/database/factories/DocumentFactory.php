<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        $extension = fake()->randomElement(['docx', 'pdf']);

        return [
            'user_id' => User::factory(),
            'original_name' => fake()->slug(3).'.'.$extension,
            'storage_disk' => 'local',
            'storage_path' => 'documents/'.Str::uuid().'.'.$extension,
            'mime_type' => $extension === 'pdf'
                ? 'application/pdf'
                : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'extension' => $extension,
            'size_bytes' => 1024,
            'sha256' => hash('sha256', Str::uuid()->toString()),
            'page_count' => null,
            'word_count' => 0,
            'paragraph_count' => 0,
            'language' => null,
            'status' => DocumentStatus::Uploaded,
            'failure_code' => null,
            'failure_message' => null,
            'metadata' => ['consent' => true],
            'uploaded_at' => now(),
            'processed_at' => null,
        ];
    }

    public function ready(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DocumentStatus::Ready,
            'page_count' => 1,
            'word_count' => 100,
            'paragraph_count' => 2,
            'language' => 'es',
            'processed_at' => now(),
        ]);
    }

    public function failed(string $code = 'extraction_failed'): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DocumentStatus::Failed,
            'failure_code' => $code,
            'failure_message' => 'Fallo simulado de extracción.',
            'processed_at' => now(),
        ]);
    }
}
