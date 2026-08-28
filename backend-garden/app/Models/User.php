<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property string $id
 * @property string $name
 * @property string|null $pen_name
 * @property string $postal_handle
 * @property Carbon|null $postal_handle_rotated_at
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property UserRole $role
 * @property UserStatus $status
 * @property string|null $avatar_path
 * @property string|null $bio
 * @property string|null $country_code
 * @property string $timezone
 * @property string $locale
 * @property bool $accepts_random_letters
 * @property int $random_letters_daily_cap
 * @property Carbon|null $last_active_at
 * @property Carbon|null $deactivated_at
 * @property Carbon|null $deletes_at
 * @property-read UserSettings|null $settings
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'pen_name',
        'email',
        'password',
        'bio',
        'country_code',
        'timezone',
        'locale',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'postal_handle_rotated_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'accepts_random_letters' => 'boolean',
            'random_letters_daily_cap' => 'integer',
            'last_active_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'deletes_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            $user->postal_handle ??= self::generatePostalHandle($user->name);
            $user->role ??= UserRole::Client;
            $user->status ??= UserStatus::Active;
        });
    }

    /**
     * Build a `name-XXXX` postal handle (4 hex), retrying on the rare collision.
     */
    public static function generatePostalHandle(string $name): string
    {
        $base = Str::slug((string) Str::of($name)->ascii()->limit(24, ''));
        $base = $base !== '' ? $base : 'doll';

        do {
            $handle = $base.'-'.bin2hex(random_bytes(2));
        } while (static::withTrashed()->where('postal_handle', $handle)->exists());

        return $handle;
    }

    /**
     * @return HasOne<UserSettings, $this>
     */
    public function settings(): HasOne
    {
        return $this->hasOne(UserSettings::class);
    }

    public function isStaff(): bool
    {
        return $this->role->isStaff();
    }
}
