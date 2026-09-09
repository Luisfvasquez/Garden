<?php

declare(strict_types=1);

namespace App\Services\Postal;

use App\Enums\DeliveryEventType;
use App\Enums\DeliveryMode;
use App\Enums\TransitTier;
use App\Enums\UserStatus;
use App\Exceptions\ApiException;
use App\Models\Letter;
use App\Models\LetterDelivery;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns a letter + the sender's choices into one queued delivery per recipient
 * (ADR-0001). The letter is locked; the postal clock takes over from here.
 */
class LetterSender
{
    private const EXPRESS_MONTHLY_QUOTA = 3;

    public function __construct(private readonly TransitCalculator $calculator) {}

    /**
     * @return Collection<int, LetterDelivery>
     */
    public function send(Letter $letter, User $sender, SendOptions $options): Collection
    {
        $recipients = $this->resolveRecipients($sender, $options->recipientHandles);

        $this->assertExpressQuota($sender, $options->tier, $recipients->count());

        return DB::transaction(function () use ($letter, $sender, $options, $recipients): Collection {
            $deliveries = $recipients->map(function (User $recipient) use ($letter, $sender, $options): LetterDelivery {
                $crossBorder = $sender->country_code !== null
                    && $recipient->country_code !== null
                    && $sender->country_code !== $recipient->country_code;

                $scheduledFor = $this->calculator->scheduledFor($options->arriveAt, $options->tier, $crossBorder);

                $delivery = new LetterDelivery([
                    'sender_id' => $sender->getKey(),
                    'recipient_id' => $recipient->getKey(),
                    'delivery_mode' => DeliveryMode::Direct,
                    'tier' => $options->tier,
                    'scheduled_for' => $scheduledFor,
                    'is_anonymous' => $options->isAnonymous,
                    'allow_read_receipt' => $options->allowReadReceipt,
                    'reveal_sender_at' => $options->revealSenderAt,
                ]);
                $delivery->letter()->associate($letter);
                $delivery->estimated_delivery_at = $this->calculator
                    ->provisionalEstimate($scheduledFor, $options->tier, $crossBorder);
                $delivery->save();

                $delivery->recordEvent(DeliveryEventType::Created);
                $delivery->recordEvent(DeliveryEventType::Queued);

                $delivery->setRelation('recipient', $recipient);

                return $delivery;
            });

            $letter->lock();

            return $deliveries;
        });
    }

    /**
     * @param  list<string>  $handles
     * @return Collection<int, User>
     */
    private function resolveRecipients(User $sender, array $handles): Collection
    {
        $handles = array_values(array_unique($handles));

        if (in_array($sender->postal_handle, $handles, true)) {
            throw ValidationException::withMessages([
                'recipients' => 'No puedes enviarte una carta a ti mismo.',
            ]);
        }

        $users = User::query()
            ->whereIn('postal_handle', $handles)
            ->where('status', UserStatus::Active)
            ->get();

        $missing = array_values(array_diff($handles, $users->pluck('postal_handle')->all()));
        if ($missing !== []) {
            throw (new ApiException('Algún destinatario no existe.', 'RECIPIENT_NOT_FOUND', 422))
                ->withErrors(['recipients' => array_map(
                    static fn (string $h): string => "El identificador «{$h}» no existe.",
                    $missing,
                )]);
        }

        return $users->values();
    }

    private function assertExpressQuota(User $sender, TransitTier $tier, int $count): void
    {
        if ($tier !== TransitTier::Express) {
            return;
        }

        $used = LetterDelivery::query()
            ->where('sender_id', $sender->getKey())
            ->where('tier', TransitTier::Express)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        if ($used + $count > self::EXPRESS_MONTHLY_QUOTA) {
            throw new ApiException(
                'Has agotado los envíos exprés de este mes.',
                'QUOTA_EXCEEDED',
                422,
            );
        }
    }
}
