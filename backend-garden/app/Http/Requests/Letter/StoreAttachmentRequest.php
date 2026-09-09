<?php

declare(strict_types=1);

namespace App\Http\Requests\Letter;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreAttachmentRequest extends FormRequest
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
            'type' => ['required', 'in:image,audio,pressed_flower'],
            'file' => ['required', 'file', 'max:'.(int) config('postal.limits.image_max_kb')],
            'duration_seconds' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('postal.limits.audio_max_seconds')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $file = $this->file('file');
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                return;
            }

            $mime = (string) $file->getMimeType();
            $isImageType = in_array($this->input('type'), ['image', 'pressed_flower'], true);

            if ($isImageType && ! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                $validator->errors()->add('file', 'El adjunto debe ser una imagen jpg, png o webp.');
            }

            if ($this->input('type') === 'audio' && ! str_starts_with($mime, 'audio/')) {
                $validator->errors()->add('file', 'El adjunto debe ser un archivo de audio.');
            }
        });
    }
}
