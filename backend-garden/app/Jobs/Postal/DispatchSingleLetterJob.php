<?php

declare(strict_types=1);

namespace App\Jobs\Postal;

use App\Enums\DeliveryEventType;
use App\Enums\DeliveryStatus;
use App\Events\LetterDispatched;
use App\Events\LetterFailed;
use App\Models\Block;
use App\Models\LetterDelivery;
use App\Services\Postal\PostalRoutes;
use App\Services\Postal\TransitCalculator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Dispatch one delivery. Idempotent: if it is no longer `queued`, it exits
 * quietly. Order of checks per backend-garden/docs/jobs-y-colas.md.
 */
class DispatchSingleLetterJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly string $deliveryId)
    {
        $this->onQueue('postal');
    }

    public function handle(TransitCalculator $calculator, PostalRoutes $routes): void
    {
        $delivery = LetterDelivery::with(['sender', 'recipient'])->find($this->deliveryId);

        if ($delivery === null || $delivery->status !== DeliveryStatus::Queued) {
            return; // already handled
        }

        $recipient = $delivery->recipient;

        // Direct mode only for now; random assignment arrives with the bottle at sea (Fase 2).
        if ($recipient === null || ! $recipient->status->canAuthenticate()) {
            $delivery->markFailed('recipient_unavailable');
            LetterFailed::dispatch($delivery);

            return;
        }

        if ($delivery->sender_id !== null && Block::exists($recipient->getKey(), $delivery->sender_id)) {
            $delivery->markBlocked(); // silent — sender still sees "delivered"

            return;
        }

        $plan = $calculator->dispatchPlan($delivery->tier, $calculator->crossBorderFor($delivery));

        $delivery->markInTransit($plan->transitMinutes, $plan->deliveredAt, $plan->estimatedDeliveryAt);

        $this->recordItinerary($delivery, $routes);

        LetterDispatched::dispatch($delivery);
    }

    /**
     * Fictional sorting-office stops spread across the transit window, so the
     * tracking timeline reads naturally when the recipient checks it later.
     */
    private function recordItinerary(LetterDelivery $delivery, PostalRoutes $routes): void
    {
        $from = $delivery->dispatched_at;
        $to = $delivery->delivered_at;
        if ($from === null || $to === null) {
            return;
        }

        $offices = $routes->itinerary(random_int(1, 2));
        $slots = count($offices) + 2;

        foreach ($offices as $i => $office) {
            $at = $from->copy()->addSeconds((int) ($from->diffInSeconds($to) * ($i + 1) / $slots));
            $delivery->recordEvent(DeliveryEventType::SortingOffice, ['office' => $office], $at);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $delivery = LetterDelivery::find($this->deliveryId);

        // Never leave a delivery stuck in an intermediate state.
        if ($delivery !== null && $delivery->status === DeliveryStatus::Queued) {
            $delivery->markFailed('dispatch_error');
            LetterFailed::dispatch($delivery);
        }
    }
}
