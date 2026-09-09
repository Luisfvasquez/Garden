<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReactionType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $post_id
 * @property string $user_id
 * @property ReactionType $type
 */
class Reaction extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = ['post_id', 'user_id', 'type'];

    protected function casts(): array
    {
        return ['type' => ReactionType::class];
    }

    /**
     * @return BelongsTo<PublicPost, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(PublicPost::class, 'post_id');
    }
}
