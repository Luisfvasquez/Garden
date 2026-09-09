<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SupportResourceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string|null $country_code
 * @property string|null $topic
 * @property string $name
 * @property string|null $description
 * @property string|null $phone
 * @property string|null $sms
 * @property string|null $url
 * @property string|null $hours
 * @property list<string>|null $languages
 * @property int $priority
 * @property bool $is_active
 */
class SupportResource extends Model
{
    /** @use HasFactory<SupportResourceFactory> */
    use HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'country_code',
        'topic',
        'name',
        'description',
        'phone',
        'sms',
        'url',
        'hours',
        'languages',
        'priority',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'languages' => 'array',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Active rows for a country plus the global fallback rows (`country_code`
     * null), most prominent first. With no country, only the global rows.
     *
     * @param  Builder<SupportResource>  $query
     */
    public function scopeForCountry(Builder $query, ?string $countryCode): void
    {
        $countryCode = $countryCode !== null ? strtoupper($countryCode) : null;

        $query->where('is_active', true)
            ->where(function (Builder $q) use ($countryCode): void {
                $q->whereNull('country_code');

                if ($countryCode !== null) {
                    $q->orWhere('country_code', $countryCode);
                }
            })
            ->orderByDesc('priority')
            ->orderBy('name');
    }
}
