<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Letter\StoreAttachmentRequest;
use App\Http\Resources\LetterAttachmentResource;
use App\Models\Letter;
use App\Models\LetterAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    private const DISK = 'public';

    public function store(StoreAttachmentRequest $request, Letter $letter): JsonResponse
    {
        $this->authorize('update', $letter);
        $this->assertUnlocked($letter);

        if (! $letter->kind->allowsAttachments()) {
            throw new ApiException('Este tipo de carta no admite adjuntos.', 'ATTACHMENTS_NOT_ALLOWED', 422);
        }

        if ($letter->attachments()->count() >= (int) config('postal.limits.attachments_per_letter')) {
            throw new ApiException('La carta ya tiene el máximo de adjuntos.', 'ATTACHMENT_LIMIT_REACHED', 422);
        }

        $file = $request->file('file');
        $type = (string) $request->input('type');
        $path = $file->store("letters/{$letter->id}", self::DISK);

        $attachment = $letter->attachments()->create([
            'type' => $type,
            'disk' => self::DISK,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType() ?: (string) $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'metadata' => $this->metadataFor($type, $file->getRealPath(), $request->integer('duration_seconds')),
        ]);

        return (new LetterAttachmentResource($attachment))->response()->setStatusCode(201);
    }

    public function destroy(Letter $letter, LetterAttachment $attachment): Response
    {
        $this->authorize('update', $letter);
        $this->assertUnlocked($letter);

        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataFor(string $type, string|false $realPath, int $durationSeconds): array
    {
        if (in_array($type, ['image', 'pressed_flower'], true) && $realPath !== false) {
            $size = @getimagesize($realPath);
            if ($size !== false) {
                return ['width' => $size[0], 'height' => $size[1]];
            }
        }

        if ($type === 'audio' && $durationSeconds > 0) {
            return ['duration_seconds' => $durationSeconds];
        }

        return [];
    }

    private function assertUnlocked(Letter $letter): void
    {
        if ($letter->is_locked) {
            throw new ApiException('La carta ya fue enviada, no se puede editar.', 'LETTER_LOCKED', 409);
        }
    }
}
