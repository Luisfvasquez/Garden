<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ThemePreference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $user_id
 * @property bool $notify_email
 * @property bool $notify_push
 * @property bool $notify_on_arrival
 * @property bool $notify_on_dispatch_confirm
 * @property bool $notify_on_doll_message
 * @property bool $notify_on_blog_comment
 * @property bool $share_read_receipts
 * @property string|null $quiet_hours_start
 * @property string|null $quiet_hours_end
 * @property ThemePreference $theme
 * @property string|null $preferred_paper_style
 * @property bool $show_transit_countdown
 * @property-read User $user
 */
class UserSettings extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Mirrors the DB column defaults for freshly created, not-yet-reloaded rows.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'notify_email' => true,
        'notify_push' => true,
        'notify_on_arrival' => true,
        'notify_on_dispatch_confirm' => false,
        'notify_on_doll_message' => true,
        'notify_on_blog_comment' => true,
        'share_read_receipts' => false,
        'theme' => 'system',
        'show_transit_countdown' => true,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'notify_email',
        'notify_push',
        'notify_on_arrival',
        'notify_on_dispatch_confirm',
        'notify_on_doll_message',
        'notify_on_blog_comment',
        'share_read_receipts',
        'quiet_hours_start',
        'quiet_hours_end',
        'theme',
        'preferred_paper_style',
        'show_transit_countdown',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notify_email' => 'boolean',
            'notify_push' => 'boolean',
            'notify_on_arrival' => 'boolean',
            'notify_on_dispatch_confirm' => 'boolean',
            'notify_on_doll_message' => 'boolean',
            'notify_on_blog_comment' => 'boolean',
            'share_read_receipts' => 'boolean',
            'quiet_hours_start' => 'string',
            'quiet_hours_end' => 'string',
            'theme' => ThemePreference::class,
            'show_transit_countdown' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
