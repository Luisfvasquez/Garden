<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $type
 * @property string $title
 * @property string $body
 * @property array<string, mixed>|null $data
 * @property Carbon $deliver_after
 * @property Carbon|null $sent_at
 */
class ScheduledPush extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'type', 'title', 'body', 'data', 'deliver_after', 'sent_at'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'deliver_after' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<ScheduledPush>  $query
     */
    public function scopeDue(Builder $query): void
    {
        $query->whereNull('sent_at')->where('deliver_after', '<=', now());
    }
}
