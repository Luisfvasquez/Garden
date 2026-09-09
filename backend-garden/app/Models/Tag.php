<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $slug
 * @property string $label
 * @property int $usage_count
 */
class Tag extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = ['slug', 'label', 'usage_count'];

    protected function casts(): array
    {
        return ['usage_count' => 'integer'];
    }

    public static function fromLabel(string $label): self
    {
        $slug = Str::slug((string) Str::of($label)->ascii()->limit(40, ''));

        return static::firstOrCreate(['slug' => $slug], ['label' => trim($label)]);
    }
}
