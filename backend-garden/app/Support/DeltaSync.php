<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\ApiException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Delta sync for list endpoints: `?updated_since=` (docs/api/_convenciones.md,
 * spec §12.4). Built for the mobile client, which cannot re-download the whole
 * mailbox on every launch.
 *
 * Three things make this correct rather than merely filtered:
 *
 *  1. **The watermark comes from the server, not the client.** `meta.synced_at`
 *     is read BEFORE the query runs and is what the client sends back next
 *     time. A client clock that runs fast would otherwise skip rows forever.
 *     Taking it early means a row written mid-query is re-sent next time;
 *     overlap is harmless, a gap is data loss.
 *
 *  2. **Strictly greater than.** `>=` re-sends the boundary row on every single
 *     sync, which for a quiet account means the delta is never actually empty.
 *
 *  3. **Ordering switches to `updated_at`.** Paging a delta by `delivered_at`
 *     while filtering on `updated_at` gives pages that don't compose: a row
 *     touched mid-pagination jumps between pages. Ordering by the same column
 *     you filter on makes the cursor resumable.
 *
 * An invalid timestamp is rejected loudly. Silently returning everything (or
 * nothing) is how a client ends up quietly out of sync for weeks.
 */
final class DeltaSync
{
    private function __construct(
        public readonly ?Carbon $since,
        public readonly Carbon $syncedAt,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $raw = $request->query('updated_since');

        // Read the watermark before touching the database, never after.
        $syncedAt = Carbon::now('UTC');

        if ($raw === null || $raw === '') {
            return new self(null, $syncedAt);
        }

        if (! is_string($raw)) {
            throw self::invalid();
        }

        try {
            $since = Carbon::parse($raw)->utc();
        } catch (\Throwable) {
            throw self::invalid();
        }

        return new self($since, $syncedAt);
    }

    public function isDelta(): bool
    {
        return $this->since !== null;
    }

    /**
     * Applies the window and, when syncing, the ordering that makes the cursor
     * resumable. `$default` runs instead when this is a plain listing.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  callable(Builder<TModel>): mixed  $default  normal ordering; its
     *                                                     return value is ignored, so a one-line
     *                                                     `fn ($q) => $q->orderBy(...)` is fine
     * @return Builder<TModel>
     */
    public function apply(Builder $query, callable $default, string $column = 'updated_at'): Builder
    {
        if (! $this->isDelta()) {
            $default($query);

            return $query;
        }

        $table = $query->getModel()->getTable();

        return $query
            ->where("{$table}.{$column}", '>', $this->since)
            ->reorder()
            ->orderBy("{$table}.{$column}")
            ->orderBy("{$table}.id");
    }

    /**
     * The `meta` block. `synced_at` is always present so a first, full sync
     * gives the client its starting watermark without a second round trip.
     *
     * @param  list<string>  $deletedIds  tombstones, for models that can vanish
     * @return array<string, mixed>
     */
    public function meta(array $deletedIds = []): array
    {
        return [
            'synced_at' => $this->syncedAt->toIso8601ZuluString(),
            'is_delta' => $this->isDelta(),
            // Only meaningful during a delta: on a full sync, whatever the
            // client doesn't receive is deleted by definition.
            'deleted_ids' => $this->isDelta() ? $deletedIds : [],
        ];
    }

    private static function invalid(): ApiException
    {
        return new ApiException(
            'El parámetro `updated_since` debe ser una fecha ISO-8601 (por ejemplo 2026-09-17T10:00:00Z).',
            'INVALID_UPDATED_SINCE',
            422,
        );
    }
}
