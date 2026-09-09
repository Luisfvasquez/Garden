<?php

declare(strict_types=1);

namespace App\Services\Postal;

use App\Enums\DeliveryMode;
use App\Enums\DeliveryStatus;
use App\Enums\ModerationActionType;
use App\Enums\UserStatus;
use App\Models\Block;
use App\Models\LetterDelivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who may receive a "bottle at sea". Split in two: the sender-independent
 * conditions (used to build the Redis pool) and the per-pick verification the
 * picker runs against the DB, since the pool can be up to 15 minutes stale
 * (docs/api/botella-al-mar.md).
 */
class RandomRecipientQuery
{
    /**
     * Sender-independent eligibility: active, verified, opted in, recently seen,
     * not restricted.
     *
     * @return Builder<User>
     */
    public function baseEligible(): Builder
    {
        $activeWithin = now()->subDays((int) config('postal.random.recipient_active_within_days', 30));

        return User::query()
            ->where('status', UserStatus::Active)
            ->whereNotNull('email_verified_at')
            ->where('accepts_random_letters', true)
            ->where('last_active_at', '>=', $activeWithin)
            ->whereNotExists(function ($q): void {
                $q->select('id')
                    ->from('moderation_actions')
                    ->whereColumn('moderation_actions.user_id', 'users.id')
                    ->where('type', ModerationActionType::RestrictRandom->value)
                    ->whereNull('lifted_at')
                    ->where(function ($q2): void {
                        $q2->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    });
            });
    }

    /**
     * Everything in {@see baseEligible()} plus the sender-specific gates.
     */
    public function isEligible(User $recipient, User $sender): bool
    {
        if ($recipient->getKey() === $sender->getKey()) {
            return false;
        }

        if (! $this->baseEligible()->whereKey($recipient->getKey())->exists()) {
            return false;
        }

        // No block in either direction.
        if (Block::exists($recipient->getKey(), $sender->getKey())
            || Block::exists($sender->getKey(), $recipient->getKey())) {
            return false;
        }

        // Not already at their daily cap of incoming random letters.
        $cap = $recipient->random_letters_daily_cap
            ?? (int) config('postal.random.recipient_default_daily_cap', 3);

        $todayCount = LetterDelivery::query()
            ->where('recipient_id', $recipient->getKey())
            ->where('delivery_mode', DeliveryMode::Random)
            ->where('created_at', '>=', now()->startOfDay())
            ->whereNotIn('status', [DeliveryStatus::Failed, DeliveryStatus::Cancelled])
            ->count();

        if ($todayCount >= $cap) {
            return false;
        }

        // Not from this same sender within the cooldown window.
        $cooldownDays = (int) config('postal.random.same_sender_cooldown_days', 90);
        $recentFromSender = LetterDelivery::query()
            ->where('recipient_id', $recipient->getKey())
            ->where('sender_id', $sender->getKey())
            ->where('delivery_mode', DeliveryMode::Random)
            ->where('created_at', '>=', now()->subDays($cooldownDays))
            ->exists();

        return ! $recentFromSender;
    }
}
