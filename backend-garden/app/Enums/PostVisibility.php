<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who can reach a published post (docs/api/blog.md). `unlisted` is reachable by
 * direct link only — never in the feed.
 */
enum PostVisibility: string
{
    case Public = 'public';
    case Unlisted = 'unlisted';
}
