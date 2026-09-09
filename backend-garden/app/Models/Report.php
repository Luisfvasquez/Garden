<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReportableType;
use App\Enums\ReportCategory;
use App\Enums\ReportSeverity;
use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $reporter_id
 * @property ReportableType $reportable_type
 * @property string $reportable_id
 * @property ReportCategory $category
 * @property string|null $details
 * @property ReportStatus $status
 * @property ReportSeverity $severity
 * @property Carbon $created_at
 * @property-read User $reporter
 */
class Report extends Model
{
    use HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'open',
        'severity' => 'low',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reporter_id',
        'reportable_type',
        'reportable_id',
        'category',
        'details',
        'severity',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reportable_type' => ReportableType::class,
            'category' => ReportCategory::class,
            'status' => ReportStatus::class,
            'severity' => ReportSeverity::class,
            'handled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}
