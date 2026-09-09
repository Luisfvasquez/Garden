<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ModerationStatus;
use App\Models\Comment;
use App\Models\PublicPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => PublicPost::factory(),
            'author_id' => User::factory(),
            'parent_id' => null,
            'body' => fake()->sentence(),
            'is_anonymous' => false,
            'moderation_status' => ModerationStatus::Approved,
            'published_at' => now(),
        ];
    }
}
