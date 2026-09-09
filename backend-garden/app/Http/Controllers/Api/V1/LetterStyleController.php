<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\LetterStyleCatalogResource;
use App\Services\Postal\LetterStyleCatalog;

class LetterStyleController extends Controller
{
    /**
     * GET /api/v1/letters/styles — the aesthetic catalogue. Cacheable.
     */
    public function index(LetterStyleCatalog $catalog): LetterStyleCatalogResource
    {
        return new LetterStyleCatalogResource($catalog->all());
    }
}
