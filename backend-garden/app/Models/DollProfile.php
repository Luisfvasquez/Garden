<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DollRateType;
use App\Enums\UserRole;
use Database\Factories\DollProfileFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The capacity, not the role: `users.role` only becomes `doll` once this is
 * verified (docs/api/dolls.md). A user can request this once; staff verify it
 * by hand (`verified_at`) — never automatically.
 *
 * @property string $id
 * @property string $user_id
 * @property string $headline
 * @property string|null $bio
 * @property list<string> $specialties
 * @property list<string> $languages
 * @property list<string> $tone_tags
 * @property DollRateType $rate_type
 * @property int|null $rate_amount
 * @property string|null $currency
 * @property bool $is_available
 * @property int $max_concurrent_requests
 * @property string $rating_avg
 * @property int $rating_count
 * @property int $completed_requests_count
 * @property int|null $response_time_avg_minutes
 * @property array<string, mixed>|null $portfolio
 * @property Carbon|null $verified_at
 * @property-read User $user
 */
class DollProfile extends Model
{
    /** @use HasFactory<DollProfileFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'specialties' => '[]',
        'languages' => '[]',
        'tone_tags' => '[]',
        'rate_type' => 'free',
        'is_available' => false,
        'max_concurrent_requests' => 3,
        'rating_avg' => 0,
        'rating_count' => 0,
        'completed_requests_count' => 0,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'headline', 'bio', 'specialties', 'languages', 'tone_tags',
        'rate_type', 'rate_amount', 'currency', 'is_available', 'portfolio',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'specialties' => 'array',
            'languages' => 'array',
            'tone_tags' => 'array',
            'rate_type' => DollRateType::class,
            'rate_amount' => 'integer',
            'is_available' => 'boolean',
            'max_concurrent_requests' => 'integer',
            'rating_avg' => 'decimal:2',
            'rating_count' => 'integer',
            'completed_requests_count' => 'integer',
            'response_time_avg_minutes' => 'integer',
            'portfolio' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * @param  Builder<DollProfile>  $query
     */
    public function scopeVerified(Builder $query): void
    {
        $query->whereNotNull('verified_at');
    }

    /**
     * Staff-only action (Filament). Flips `users.role` to `doll` — the role
     * activates at verification, never before (docs/00-especificacion-tecnica.md §8.8).
     */
    public function markVerified(): void
    {
        if ($this->isVerified()) {
            return;
        }

        $this->forceFill(['verified_at' => now()])->save();
        $this->user->forceFill(['role' => UserRole::Doll])->save();
    }

    /**
     * Also demotes the user back to `client` — an unverified Doll has no
     * business keeping the elevated role.
     */
    public function markUnverified(): void
    {
        $this->forceFill(['verified_at' => null, 'is_available' => false])->save();

        if ($this->user->role === UserRole::Doll) {
            $this->user->forceFill(['role' => UserRole::Client])->save();
        }
    }

    // Capacity checks against `doll_requests` (`activeRequestsCount()`,
    // `hasCapacity()`) land with that table in the next module.
}
