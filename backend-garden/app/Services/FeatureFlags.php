<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FeatureFlag;
use Illuminate\Support\Facades\Cache;

/**
 * Single source of truth for feature-flag state.
 *
 * Every new module ships behind a flag (see ../CLAUDE.md rule 5). Unknown keys
 * resolve to `false` so a missing row means "off", never a crash.
 */
class FeatureFlags
{
    private const CACHE_KEY = 'feature_flags:map';

    private const TTL_SECONDS = 60;

    public function enabled(string $key): bool
    {
        return $this->all()[$key] ?? false;
    }

    /**
     * @return array<string, bool>
     */
    public function all(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            self::TTL_SECONDS,
            static fn (): array => FeatureFlag::query()
                ->orderBy('key')
                ->pluck('enabled', 'key')
                ->map(static fn ($enabled): bool => (bool) $enabled)
                ->all(),
        );
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
