<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ConsentStatus;
use App\Enums\ModerationStatus;
use App\Enums\PostType;
use App\Enums\PostVisibility;
use App\Models\PublicPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PublicPost>
 */
class PublicPostFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $text = fake()->paragraph();

        return [
            'author_id' => User::factory(),
            'type' => PostType::Reflection,
            'title' => fake()->sentence(4),
            'body' => ['type' => 'doc', 'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]],
            ]],
            'body_plain' => $text,
            'testimonial' => null,
            'is_anonymous' => false,
            'comments_enabled' => true,
            'visibility' => PostVisibility::Public,
            'moderation_status' => ModerationStatus::Approved,
            'consent_status' => ConsentStatus::NotRequired,
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['published_at' => null]);
    }

    public function flagged(): static
    {
        return $this->state(fn (): array => [
            'moderation_status' => ModerationStatus::Flagged,
            'published_at' => null,
        ]);
    }

    public function anonymous(): static
    {
        return $this->state(fn (): array => ['is_anonymous' => true]);
    }
}
