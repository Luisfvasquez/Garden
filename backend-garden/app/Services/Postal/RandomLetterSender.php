<?php

declare(strict_types=1);

namespace App\Services\Postal;

use App\Enums\DeliveryEventType;
use App\Enums\DeliveryMode;
use App\Enums\DeliveryStatus;
use App\Enums\LetterKind;
use App\Enums\ModerationStatus;
use App\Enums\ModerationSurface;
use App\Enums\TransitTier;
use App\Exceptions\ApiException;
use App\Models\Letter;
use App\Models\LetterDelivery;
use App\Models\User;
use App\Services\Moderation\ContentModerator;
use App\Services\Moderation\CriticalAlertDispatcher;
use App\Services\Moderation\ModerationContext;
use App\Support\TiptapContent;
use Illuminate\Support\Facades\DB;

/**
 * Sends a "bottle at sea": one queued delivery with no recipient — the stranger
 * is resolved at dispatch and never revealed to the sender
 * (docs/api/botella-al-mar.md, ADR-0004).
 *
 * The order of gates is the contract: verified → account age → not restricted →
 * quota → no attachments → moderation → pool not empty.
 */
class RandomLetterSender
{
    /**
     * @var array<string, int> reason code → HTTP status
     */
    private const REASON_STATUS = [
        'EMAIL_NOT_VERIFIED' => 422,
        'ACCOUNT_TOO_NEW' => 422,
        'RANDOM_RESTRICTED' => 403,
        'QUOTA_EXCEEDED' => 422,
    ];

    public function __construct(
        private readonly RandomLetterQuota $quota,
        private readonly RandomRecipientPool $pool,
        private readonly ContentModerator $moderator,
        private readonly TransitCalculator $calculator,
        private readonly CriticalAlertDispatcher $alerts,
    ) {}

    public function send(Letter $letter, User $sender, TransitTier $tier): LetterDelivery
    {
        $this->assertDraft($letter);
        $this->assertNoAttachments($letter);
        $this->assertEligible($sender);

        $verdict = $this->moderator->check(
            (string) $letter->body_plain,
            new ModerationContext(
                ModerationSurface::RandomLetter,
                authorId: $sender->getKey(),
                authorCountryCode: $sender->country_code,
                locale: $sender->locale,
            ),
        );

        if ($verdict->decision->value === 'rejected') {
            throw new ApiException(
                'La carta no ha pasado el filtro de contenido.',
                'CONTENT_FLAGGED',
                422,
                meta: ['categories' => $verdict->toArray()['categories']],
            );
        }

        // A flagged (not rejected) verdict — typically a self-harm signal — is
        // held for human review before it can reach a stranger. Never a silent
        // block; the sender is told and pointed to support resources.
        $held = $verdict->blocks();

        if ($this->pool->count() === 0 && ! $held) {
            throw new ApiException(
                'Ahora mismo no hay nadie esperando una carta. Inténtalo más tarde.',
                'NO_RANDOM_RECIPIENT',
                409,
            );
        }

        return DB::transaction(function () use ($letter, $sender, $tier, $held, $verdict): LetterDelivery {
            $letter->forceFill([
                'kind' => LetterKind::Random,
                'moderation_status' => $held ? ModerationStatus::Flagged : ModerationStatus::Approved,
            ])->save();

            $delivery = new LetterDelivery([
                'sender_id' => $sender->getKey(),
                'recipient_id' => null,
                'delivery_mode' => DeliveryMode::Random,
                'tier' => $tier,
                'scheduled_for' => now(),
                'is_anonymous' => true,
            ]);
            $delivery->letter()->associate($letter);

            if ($held) {
                $delivery->status = DeliveryStatus::Held;
                $delivery->held_at = now();
            } else {
                $delivery->estimated_delivery_at = $this->calculator
                    ->provisionalEstimate(now(), $tier, false);
            }
            $delivery->save();

            $delivery->recordEvent(DeliveryEventType::Created);
            if (! $held) {
                $delivery->recordEvent(DeliveryEventType::Queued);
                $letter->lock();
            }

            if ($held) {
                $this->alerts->alertIfCritical(
                    'Random letter held for review',
                    $verdict,
                    "{$sender->postal_handle}'s random letter was held for review.",
                    ['delivery_id' => $delivery->id, 'user_id' => $sender->getKey()],
                );
            }

            return $delivery;
        });
    }

    /**
     * The recipient's one anonymous reply, sent straight back to the original
     * (still-anonymous) sender. Consumes the single-reply allowance on the
     * original delivery.
     *
     * @param  array<string, mixed>  $rawBody
     */
    public function replyAnonymously(
        LetterDelivery $original,
        User $replier,
        array $rawBody,
        TransitTier $tier,
    ): LetterDelivery {
        if ($original->delivery_mode !== DeliveryMode::Random) {
            throw new ApiException('Esta carta no admite respuesta anónima.', 'INVALID_TARGET', 422);
        }

        if ($original->hasAnonymousReply()) {
            throw new ApiException('Ya has usado tu única respuesta por este canal.', 'CHANNEL_CLOSED', 409);
        }

        if ($original->sender_id === null) {
            throw new ApiException('No se puede responder a esta carta.', 'INVALID_TARGET', 422);
        }

        $body = TiptapContent::sanitize($rawBody);
        $plain = TiptapContent::toPlainText($body);

        $verdict = $this->moderator->check(
            $plain,
            new ModerationContext(
                ModerationSurface::RandomLetter,
                authorId: $replier->getKey(),
                authorCountryCode: $replier->country_code,
                locale: $replier->locale,
            ),
        );

        if ($verdict->decision->value === 'rejected') {
            throw new ApiException('La respuesta no ha pasado el filtro de contenido.', 'CONTENT_FLAGGED', 422);
        }

        $held = $verdict->blocks();

        return DB::transaction(function () use ($original, $replier, $body, $plain, $tier, $held, $verdict): LetterDelivery {
            $letter = new Letter([
                'body' => $body,
                'body_plain' => $plain,
                'word_count' => TiptapContent::wordCount($plain),
                'kind' => LetterKind::Random,
                'in_reply_to_delivery_id' => $original->getKey(),
            ]);
            $letter->author_id = $replier->getKey();
            $letter->moderation_status = $held ? ModerationStatus::Flagged : ModerationStatus::Approved;
            $letter->save();

            $reply = new LetterDelivery([
                'sender_id' => $replier->getKey(),
                'recipient_id' => $original->sender_id,
                'delivery_mode' => DeliveryMode::Random,
                'tier' => $tier,
                'scheduled_for' => now(),
                'is_anonymous' => true,
            ]);
            $reply->letter()->associate($letter);

            if ($held) {
                $reply->status = DeliveryStatus::Held;
                $reply->held_at = now();
            } else {
                $reply->estimated_delivery_at = $this->calculator->provisionalEstimate(now(), $tier, false);
            }
            $reply->save();

            $reply->recordEvent(DeliveryEventType::Created);
            if (! $held) {
                $reply->recordEvent(DeliveryEventType::Queued);
                $letter->lock();
            } else {
                $this->alerts->alertIfCritical(
                    'Anonymous reply held for review',
                    $verdict,
                    "{$replier->postal_handle}'s anonymous reply was held for review.",
                    ['delivery_id' => $reply->id, 'user_id' => $replier->getKey()],
                );
            }

            $original->linkAnonymousReply($reply->getKey());

            return $reply;
        });
    }

    private function assertDraft(Letter $letter): void
    {
        if ($letter->is_locked) {
            throw new ApiException('La carta ya fue enviada, no se puede editar.', 'LETTER_LOCKED', 409);
        }
    }

    private function assertNoAttachments(Letter $letter): void
    {
        if ($letter->attachments()->exists()) {
            throw new ApiException('Las cartas aleatorias no admiten adjuntos.', 'ATTACHMENTS_NOT_ALLOWED', 422);
        }
    }

    private function assertEligible(User $sender): void
    {
        $reasons = $this->quota->reasons($sender);
        if ($reasons === []) {
            return;
        }

        $code = $reasons[0];
        throw new ApiException(
            $this->messageFor($code),
            $code,
            self::REASON_STATUS[$code] ?? 422,
        );
    }

    private function messageFor(string $code): string
    {
        return match ($code) {
            'EMAIL_NOT_VERIFIED' => 'Verifica tu correo antes de enviar una botella al mar.',
            'ACCOUNT_TOO_NEW' => 'Tu cuenta es demasiado nueva para enviar cartas a desconocidos.',
            'RANDOM_RESTRICTED' => 'No puedes enviar cartas aleatorias por ahora.',
            'QUOTA_EXCEEDED' => 'Has agotado tu cuota de botellas al mar.',
            default => 'No puedes enviar una botella al mar ahora mismo.',
        };
    }
}
