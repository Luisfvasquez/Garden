<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ModerationActionSource;
use App\Enums\ModerationActionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property ModerationActionType $type
 * @property ModerationActionSource $source
 * @property string|null $reason
 * @property array<string, mixed>|null $context
 * @property string|null $created_by
 * @property Carbon|null $expires_at
 * @property Carbon|null $lifted_at
 */
class ModerationAction extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'source',
        'reason',
        'context',
        'created_by',
        'expires_at',
        'lifted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ModerationActionType::class,
            'source' => ModerationActionSource::class,
            'context' => 'array',
            'expires_at' => 'datetime',
            'lifted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * In force right now: not lifted, not expired.
     *
     * @param  Builder<ModerationAction>  $query
     */
    public function scopeInForce(Builder $query): void
    {
        $query->whereNull('lifted_at')
            ->where(function (Builder $q): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public static function restrictsRandom(User $user): bool
    {
        return self::query()
            ->where('user_id', $user->getKey())
            ->where('type', ModerationActionType::RestrictRandom)
            ->inForce()
            ->exists();
    }
}
