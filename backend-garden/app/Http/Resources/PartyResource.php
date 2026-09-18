<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The minimal public identity of someone on the other side of a transaction:
 * the handle, the name they chose to show, and an avatar. Never an email,
 * never an id (docs/api/_convenciones.md).
 *
 * Extracted because the Dolls module repeated this exact triple in four
 * places — and because an inline array inside a `when()` closure is something
 * Scramble cannot infer through a relation, so the generated OpenAPI claimed
 * `avatar_url: null` instead of `string | null`. A named resource fixes both.
 *
 * @mixin User
 */
class PartyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'postal_handle' => $this->postal_handle,
            'display_name' => $this->displayName(),
            'avatar_url' => $this->avatarUrl(),
        ];
    }
}
