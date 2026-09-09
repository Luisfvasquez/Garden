<?php

declare(strict_types=1);

use App\Enums\LetterKind;
use App\Models\Letter;
use App\Models\LetterAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => Storage::fake('public'));

it('attaches an image and records its dimensions', function (): void {
    $letter = Letter::factory()->create();
    Sanctum::actingAs($letter->author);

    $response = $this->postJson("/api/v1/letters/{$letter->id}/attachments", [
        'type' => 'image',
        'file' => UploadedFile::fake()->image('photo.png', 640, 480),
    ])->assertCreated();

    $response
        ->assertJsonPath('data.type', 'image')
        ->assertJsonPath('data.metadata.width', 640)
        ->assertJsonPath('data.metadata.height', 480);

    expect($letter->attachments()->count())->toBe(1);
    Storage::disk('public')->assertExists($letter->attachments()->first()->path);
});

it('accepts audio with a client-supplied duration', function (): void {
    $letter = Letter::factory()->create();
    Sanctum::actingAs($letter->author);

    $this->postJson("/api/v1/letters/{$letter->id}/attachments", [
        'type' => 'audio',
        'file' => UploadedFile::fake()->create('voice.mp3', 200, 'audio/mpeg'),
        'duration_seconds' => 42,
    ])
        ->assertCreated()
        ->assertJsonPath('data.metadata.duration_seconds', 42);
});

it('rejects a mismatched mime type', function (): void {
    $letter = Letter::factory()->create();
    Sanctum::actingAs($letter->author);

    $this->postJson("/api/v1/letters/{$letter->id}/attachments", [
        'type' => 'image',
        'file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
    ])->assertStatus(422)->assertJsonValidationErrors('file');
});

it('caps attachments at three per letter', function (): void {
    $letter = Letter::factory()->create();
    LetterAttachment::query()->insert(collect(range(1, 3))->map(fn () => [
        'id' => Str::uuid()->toString(),
        'letter_id' => $letter->id,
        'type' => 'image',
        'disk' => 'public',
        'path' => 'x.png',
        'original_name' => 'x.png',
        'mime_type' => 'image/png',
        'size_bytes' => 1,
        'metadata' => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ])->all());
    Sanctum::actingAs($letter->author);

    $this->postJson("/api/v1/letters/{$letter->id}/attachments", [
        'type' => 'image',
        'file' => UploadedFile::fake()->image('four.png'),
    ])->assertStatus(422)->assertJsonPath('error_code', 'ATTACHMENT_LIMIT_REACHED');
});

it('forbids attachments on random letters', function (): void {
    $letter = Letter::factory()->create(['kind' => LetterKind::Random]);
    Sanctum::actingAs($letter->author);

    $this->postJson("/api/v1/letters/{$letter->id}/attachments", [
        'type' => 'image',
        'file' => UploadedFile::fake()->image('x.png'),
    ])->assertStatus(422)->assertJsonPath('error_code', 'ATTACHMENTS_NOT_ALLOWED');
});

it('refuses attachments on a sent letter', function (): void {
    $letter = Letter::factory()->locked()->create();
    Sanctum::actingAs($letter->author);

    $this->postJson("/api/v1/letters/{$letter->id}/attachments", [
        'type' => 'image',
        'file' => UploadedFile::fake()->image('x.png'),
    ])->assertStatus(409)->assertJsonPath('error_code', 'LETTER_LOCKED');
});

it('deletes an attachment and its file, scoped to the letter', function (): void {
    $letter = Letter::factory()->create();
    Sanctum::actingAs($letter->author);

    $path = $this->postJson("/api/v1/letters/{$letter->id}/attachments", [
        'type' => 'image',
        'file' => UploadedFile::fake()->image('x.png'),
    ])->json('data.id');

    $this->deleteJson("/api/v1/letters/{$letter->id}/attachments/{$path}")->assertNoContent();
    expect($letter->attachments()->count())->toBe(0);
});

it('404s adding an attachment to a letter you do not own', function (): void {
    $letter = Letter::factory()->create();
    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/v1/letters/{$letter->id}/attachments", [
        'type' => 'image',
        'file' => UploadedFile::fake()->image('x.png'),
    ])->assertNotFound();
});
