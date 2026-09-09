<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Fictional postal route (anime-universe office names) used to flavour the
 * tracking timeline.
 *
 * @property string $id
 * @property string $name
 * @property int $min_minutes
 * @property int $max_minutes
 * @property list<string> $waypoints
 */
class TransitRoute extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'min_minutes', 'max_minutes', 'waypoints'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_minutes' => 'integer',
            'max_minutes' => 'integer',
            'waypoints' => 'array',
        ];
    }
}
