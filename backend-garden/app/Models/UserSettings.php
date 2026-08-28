<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ThemePreference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSettings extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
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
