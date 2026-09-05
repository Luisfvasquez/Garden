<?php

declare(strict_types=1);

namespace App\Http\Requests\Me;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notify_email' => ['sometimes', 'boolean'],
            'notify_push' => ['sometimes', 'boolean'],
            'notify_on_arrival' => ['sometimes', 'boolean'],
            'notify_on_dispatch_confirm' => ['sometimes', 'boolean'],
            'notify_on_doll_message' => ['sometimes', 'boolean'],
            'notify_on_blog_comment' => ['sometimes', 'boolean'],
            'share_read_receipts' => ['sometimes', 'boolean'],
            'quiet_hours_start' => ['nullable', 'date_format:H:i', 'required_with:quiet_hours_end'],
            'quiet_hours_end' => ['nullable', 'date_format:H:i', 'required_with:quiet_hours_start'],
            'theme' => ['sometimes', 'in:light,dark,system'],
            'preferred_paper_style' => ['sometimes', 'nullable', 'string', 'max:60'],
            'show_transit_countdown' => ['sometimes', 'boolean'],
            'accepts_random_letters' => ['sometimes', 'boolean'],
            'random_letters_daily_cap' => ['sometimes', 'integer', 'between:0,10'],
        ];
    }
}
