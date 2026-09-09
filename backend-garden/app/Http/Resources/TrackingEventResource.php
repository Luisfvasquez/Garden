<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\DeliveryEventType;
use App\Models\DeliveryEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One line of the postal-tracking timeline (docs/api/entregas-buzon.md). The
 * label is server-rendered Spanish; only `office` is ever exposed from
 * metadata (a `blocked` outcome must never leak).
 *
 * @mixin DeliveryEvent
 */
class TrackingEventResource extends JsonResource
{
    private const LABELS = [
        'created' => 'Carta redactada',
        'queued' => 'Depositada en el buzón de salida',
        'dispatched' => 'Recogida por el cartero',
        'in_transit' => 'En tránsito',
        'sorting_office' => 'En la oficina de :office',
        'out_for_delivery' => 'En reparto',
        'delivered' => 'Entregada',
        'read' => 'Abierta por el destinatario',
        'cancelled' => 'Envío cancelado',
        'failed' => 'No se pudo entregar',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $office = is_string($this->metadata['office'] ?? null) ? $this->metadata['office'] : null;

        $data = [
            'event' => $this->event->value,
            'occurred_at' => $this->occurred_at->toIso8601ZuluString(),
            'label' => str_replace(':office', (string) $office, self::LABELS[$this->event->value]),
        ];

        if ($this->event === DeliveryEventType::SortingOffice && $office !== null) {
            $data['metadata'] = ['office' => $office];
        }

        return $data;
    }
}
