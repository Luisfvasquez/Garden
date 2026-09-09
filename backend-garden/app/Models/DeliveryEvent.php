<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeliveryEventType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $letter_delivery_id
 * @property DeliveryEventType $event
 * @property Carbon $occurred_at
 * @property array<string, mixed> $metadata
 * @property-read LetterDelivery $delivery
 */
class DeliveryEvent extends Model
{
    use HasUuids;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = ['event', 'occurred_at', 'metadata'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => DeliveryEventType::class,
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DeliveryEvent $event): void {
            $event->occurred_at ??= now();
            $event->created_at ??= now();
        });
    }

    /**
     * @return BelongsTo<LetterDelivery, $this>
     */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(LetterDelivery::class, 'letter_delivery_id');
    }
}
