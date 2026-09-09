<?php

declare(strict_types=1);

namespace App\Services\Postal;

use App\Enums\DeliveryMode;
use App\Enums\DeliveryStatus;
use App\Models\LetterDelivery;
use App\Models\ModerationAction;
use App\Models\User;

/**
 * How many "bottles at sea" a sender has left, and whether they may send at all
 * (docs/api/botella-al-mar.md). Failed / cancelled attempts do not count.
 */
class RandomLetterQuota
{
    /**
     * @return array{
     *   daily_limit: int, daily_used: int, daily_remaining: int,
     *   weekly_limit: int, weekly_used: int, weekly_remaining: int,
     *   eligible: bool, reasons: list<string>
     * }
     */
    public function snapshot(User $user): array
    {
        $dailyLimit = (int) config('postal.random.daily_quota', 3);
        $weeklyLimit = (int) config('postal.random.weekly_quota', 10);

        $dailyUsed = $this->countSince($user, now()->startOfDay());
        $weeklyUsed = $this->countSince($user, now()->subDays(7));

        return [
            'daily_limit' => $dailyLimit,
            'daily_used' => $dailyUsed,
            'daily_remaining' => max(0, $dailyLimit - $dailyUsed),
            'weekly_limit' => $weeklyLimit,
            'weekly_used' => $weeklyUsed,
            'weekly_remaining' => max(0, $weeklyLimit - $weeklyUsed),
            'eligible' => $this->reasons($user) === [],
            'reasons' => $this->reasons($user),
        ];
    }

    /**
     * Machine-readable blockers, empty when the user may send.
     *
     * @return list<string>
     */
    public function reasons(User $user): array
    {
        $reasons = [];

        if ($user->email_verified_at === null) {
            $reasons[] = 'EMAIL_NOT_VERIFIED';
        }

        $minAge = (int) config('postal.random.min_account_age_days', 7);
        if ($user->created_at !== null && $user->created_at->gt(now()->subDays($minAge))) {
            $reasons[] = 'ACCOUNT_TOO_NEW';
        }

        if (ModerationAction::restrictsRandom($user)) {
            $reasons[] = 'RANDOM_RESTRICTED';
        }

        $snapshot = [
            'daily' => [$this->countSince($user, now()->startOfDay()), (int) config('postal.random.daily_quota', 3)],
            'weekly' => [$this->countSince($user, now()->subDays(7)), (int) config('postal.random.weekly_quota', 10)],
        ];
        foreach ($snapshot as [$used, $limit]) {
            if ($used >= $limit) {
                $reasons[] = 'QUOTA_EXCEEDED';
                break;
            }
        }

        return array_values(array_unique($reasons));
    }

    private function countSince(User $user, \DateTimeInterface $since): int
    {
        return LetterDelivery::query()
            ->where('sender_id', $user->getKey())
            ->where('delivery_mode', DeliveryMode::Random)
            ->where('created_at', '>=', $since)
            ->whereNotIn('status', [DeliveryStatus::Failed, DeliveryStatus::Cancelled])
            ->count();
    }
}
