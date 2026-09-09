<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * The `meta` block for cursor-paginated collections
 * (docs/api/_convenciones.md).
 */
final class CursorPage
{
    /**
     * @template TValue
     *
     * @param  CursorPaginator<int, TValue>  $page
     * @return array{per_page: int, next_cursor: string|null, has_more: bool}
     */
    public static function meta(CursorPaginator $page): array
    {
        return [
            'per_page' => $page->perPage(),
            'next_cursor' => $page->nextCursor()?->encode(),
            'has_more' => $page->hasMorePages(),
        ];
    }

    public static function perPage(int $requested, int $default = 20, int $max = 50): int
    {
        return max(1, min($requested > 0 ? $requested : $default, $max));
    }
}
