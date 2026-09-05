<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Newly minted mobile token. `resource` is the plain-text token string; `expiresAt`
 * is passed alongside via `additional`/constructor.
 */
class TokenResource extends JsonResource
{
    public function __construct(string $token, private readonly ?\DateTimeInterface $expiresAt)
    {
        parent::__construct($token);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->resource,
            'expires_at' => $this->expiresAt !== null
                ? Carbon::instance($this->expiresAt)->toIso8601ZuluString()
                : null,
        ];
    }
}
