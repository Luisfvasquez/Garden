<?php

declare(strict_types=1);

namespace App\Jobs\Postal;

use App\Enums\DeliveryEventType;
use App\Enums\DeliveryStatus;
use App\Events\LetterDelivered;
use App\Models\LetterDelivery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * The arrival tick: every in-transit delivery whose target time has passed
 * lands in the mailbox. The transition is guarded and idempotent.
 */
class DeliverArrivedLettersJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 300;

    public function __construct()
    {
        $this->onQueue('postal');
    }

    public function handle(): void
    {
        LetterDelivery::query()
            ->where('status', DeliveryStatus::InTransit)
            ->where('delivered_at', '<=', now())
            ->cursor()
            ->each(function (LetterDelivery $delivery): void {
                if ($delivery->status === DeliveryStatus::InTransit) {
                    $delivery->recordEvent(DeliveryEventType::OutForDelivery);
                    $delivery->markDelivered();
                    LetterDelivered::dispatch($delivery);
                }
            });
    }
}
