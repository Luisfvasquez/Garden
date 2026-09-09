<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LetterKind;
use App\Models\Letter;
use App\Models\User;
use App\Support\TiptapContent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Letter>
 */
class LetterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $paragraph = fake()->paragraph();
        $body = [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $paragraph]]],
            ],
        ];
        $plain = TiptapContent::toPlainText($body);

        return [
            'author_id' => User::factory(),
            'title' => fake()->sentence(4),
            'body' => $body,
            'body_plain' => $plain,
            'word_count' => TiptapContent::wordCount($plain),
            'style' => ['paper' => 'parchment', 'font' => 'cormorant', 'ink' => 'sepia'],
            'kind' => LetterKind::Direct,
            'is_locked' => false,
        ];
    }

    public function locked(): static
    {
        return $this->state(fn (array $attributes) => ['is_locked' => true]);
    }
}
