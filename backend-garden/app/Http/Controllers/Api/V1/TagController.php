<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TagController extends Controller
{
    /**
     * GET /api/v1/tags — public. Most-used first.
     */
    public function index(): AnonymousResourceCollection
    {
        return TagResource::collection(
            Tag::query()->where('usage_count', '>', 0)->orderByDesc('usage_count')->orderBy('label')->limit(100)->get(),
        );
    }
}
