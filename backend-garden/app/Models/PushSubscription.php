<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PushSubscriptionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $platform
 * @property string|null $endpoint
 * @property string|null $public_key
 * @property string|null $auth_token
 * @property string|null $device_token
 * @property string|null $device_name
 * @property Carbon|null $last_used_at
 */
class PushSubscription extends Model
{
    /** @use HasFactory<PushSubscriptionFactory> */
    use HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id', 'platform', 'endpoint', 'public_key', 'auth_token',
        'device_token', 'device_name', 'last_used_at',
    ];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isWeb(): bool
    {
        return $this->platform === 'web';
    }
}
