<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SupportResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SupportResource
 */
class SupportResourceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'country_code' => $this->country_code,
            'topic' => $this->topic,
            'name' => $this->name,
            'description' => $this->description,
            'phone' => $this->phone,
            'sms' => $this->sms,
            'url' => $this->url,
            'hours' => $this->hours,
            'languages' => $this->languages ?? [],
            'is_global' => $this->country_code === null,
        ];
    }
}
