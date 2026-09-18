<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DollChatMessageType;
use App\Models\DollChatMessage;
use App\Models\DollRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DollChatMessage>
 */
class DollChatMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'doll_request_id' => DollRequest::factory()->inProgress(),
            'sender_id' => User::factory(),
            'type' => DollChatMessageType::Text,
            'body' => fake()->sentence(),
        ];
    }

    public function draft(int $version = 1): static
    {
        return $this->state(fn (): array => [
            'type' => DollChatMessageType::Draft,
            'body' => null,
            'draft_version' => $version,
            'draft_payload' => [
                'title' => fake()->sentence(3),
                'body' => [
                    'type' => 'doc',
                    'content' => [[
                        'type' => 'paragraph',
                        'content' => [['type' => 'text', 'text' => fake()->paragraph()]],
                    ]],
                ],
                'style' => [],
            ],
        ]);
    }

    /** Platform-written line: no human sender. */
    public function system(): static
    {
        return $this->state(fn (): array => [
            'type' => DollChatMessageType::System,
            'sender_id' => null,
        ]);
    }
}
