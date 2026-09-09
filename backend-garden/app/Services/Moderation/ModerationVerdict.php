<?php

declare(strict_types=1);

namespace App\Services\Moderation;

use App\Enums\ModerationCategory;
use App\Enums\ModerationDecision;

/**
 * The result of `ContentModerator::check()`: a decision, the categories that
 * triggered it and a 0..1 confidence score. Provider-agnostic on purpose —
 * every driver returns this shape (backend-garden/docs/moderacion.md).
 */
final readonly class ModerationVerdict
{
    /**
     * @param  list<ModerationCategory>  $categories
     */
    public function __construct(
        public ModerationDecision $decision,
        public array $categories = [],
        public float $score = 0.0,
        public ?string $driver = null,
    ) {}

    public static function approved(?string $driver = null): self
    {
        return new self(ModerationDecision::Approved, [], 0.0, $driver);
    }

    /**
     * @param  list<ModerationCategory>  $categories
     */
    public static function flagged(array $categories, float $score, ?string $driver = null): self
    {
        return new self(ModerationDecision::Flagged, $categories, $score, $driver);
    }

    /**
     * @param  list<ModerationCategory>  $categories
     */
    public static function rejected(array $categories, float $score, ?string $driver = null): self
    {
        return new self(ModerationDecision::Rejected, $categories, $score, $driver);
    }

    public function isApproved(): bool
    {
        return $this->decision === ModerationDecision::Approved;
    }

    public function blocks(): bool
    {
        return $this->decision->blocks();
    }

    public function hasCategory(ModerationCategory $category): bool
    {
        return in_array($category, $this->categories, true);
    }

    /** A self-harm or minor-safety signal that must escalate outside the queue. */
    public function isCritical(): bool
    {
        foreach ($this->categories as $category) {
            if ($category->isCritical()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'decision' => $this->decision->value,
            'categories' => array_map(static fn (ModerationCategory $c): string => $c->value, $this->categories),
            'score' => $this->score,
            'driver' => $this->driver,
        ];
    }
}
