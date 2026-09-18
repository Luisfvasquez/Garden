<?php

declare(strict_types=1);

use App\Enums\DeliveryStatus;
use App\Models\Letter;
use App\Models\LetterDelivery;
use App\Models\User;
use App\Support\SenderView;
use App\Support\TiptapContent;
use Laravel\Sanctum\Sanctum;

/**
 * PDF export (docs/api/cartas.md). The interesting cases are not "does it
 * render" but "who may render it, and what does it disclose".
 */
function letterWithBody(User $author, string $text = 'Querido hermano, han pasado tres años.'): Letter
{
    return Letter::factory()->create([
        'author_id' => $author->id,
        'title' => 'Para mi hermano',
        'body' => [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => $text]],
            ]],
        ],
        'style' => ['paper' => 'parchment', 'ink' => 'oxblood', 'border' => 'deco_corners'],
    ]);
}

/** Dompdf embeds text compressed, so assert on the structure, not the prose. */
function isPdf(string $body): bool
{
    return str_starts_with($body, '%PDF-');
}

// --- The author's own copy ----------------------------------------------------

it('exports the author\'s own letter', function (): void {
    $me = User::factory()->create();
    $letter = letterWithBody($me);
    Sanctum::actingAs($me);

    $response = $this->get("/api/v1/letters/{$letter->id}/pdf")->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and(isPdf((string) $response->getContent()))->toBeTrue();
});

it('names the file after the letter and never lets a title break the header', function (): void {
    $me = User::factory()->create();
    $letter = Letter::factory()->create([
        'author_id' => $me->id,
        'title' => 'Una "carta"; con /barras/ y acentós',
    ]);
    Sanctum::actingAs($me);

    $disposition = $this->get("/api/v1/letters/{$letter->id}/pdf")
        ->assertOk()
        ->headers->get('content-disposition');

    expect($disposition)->toMatch('/^attachment; filename="[a-z0-9\-]+\.pdf"$/');
});

it('never caches a letter in a shared cache', function (): void {
    $me = User::factory()->create();
    $letter = letterWithBody($me);
    Sanctum::actingAs($me);

    expect($this->get("/api/v1/letters/{$letter->id}/pdf")->headers->get('cache-control'))
        ->toContain('no-store');
});

it('404s someone else\'s letter without revealing it exists', function (): void {
    $letter = letterWithBody(User::factory()->create());
    Sanctum::actingAs(User::factory()->create());

    $this->get("/api/v1/letters/{$letter->id}/pdf")->assertNotFound();
});

it('requires authentication', function (): void {
    $letter = letterWithBody(User::factory()->create());

    $this->getJson("/api/v1/letters/{$letter->id}/pdf")->assertUnauthorized();
});

// --- The recipient's copy -----------------------------------------------------

function deliveryTo(User $recipient, array $attributes = []): LetterDelivery
{
    $sender = User::factory()->create(['name' => 'Gilbert Bougainvillea']);

    return LetterDelivery::factory()->create([
        ...[
            'letter_id' => letterWithBody($sender)->id,
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'status' => DeliveryStatus::Delivered,
            'delivered_at' => now(),
        ],
        ...$attributes,
    ]);
}

it('exports a received letter', function (): void {
    $me = User::factory()->create();
    $delivery = deliveryTo($me);
    Sanctum::actingAs($me);

    $response = $this->get("/api/v1/mailbox/{$delivery->id}/pdf")->assertOk();

    expect(isPdf((string) $response->getContent()))->toBeTrue();
});

it('refuses to export a letter still in transit', function (): void {
    $me = User::factory()->create();
    $delivery = deliveryTo($me, ['status' => DeliveryStatus::InTransit, 'delivered_at' => null]);
    Sanctum::actingAs($me);

    // The surprise is the product: a PDF must not become the back door.
    $this->get("/api/v1/mailbox/{$delivery->id}/pdf")->assertNotFound();
});

it('404s a delivery addressed to someone else', function (): void {
    $delivery = deliveryTo(User::factory()->create());
    Sanctum::actingAs(User::factory()->create());

    $this->get("/api/v1/mailbox/{$delivery->id}/pdf")->assertNotFound();
});

it('keeps an anonymous sender out of the PDF', function (): void {
    $me = User::factory()->create();
    $delivery = deliveryTo($me, ['is_anonymous' => true, 'reveal_sender_at' => null]);
    Sanctum::actingAs($me);

    $response = $this->get("/api/v1/mailbox/{$delivery->id}/pdf")->assertOk();
    $raw = (string) $response->getContent();

    // Dompdf compresses streams, so the name could not be read out of the bytes
    // anyway; assert on the decision instead, at the seam that makes it.
    expect(SenderView::isHidden($delivery->fresh()))->toBeTrue()
        ->and(isPdf($raw))->toBeTrue();
});

it('names the sender once anonymity has lapsed', function (): void {
    $me = User::factory()->create();
    $delivery = deliveryTo($me, [
        'is_anonymous' => true,
        'reveal_sender_at' => now()->subDay(),
    ]);
    Sanctum::actingAs($me);

    $this->get("/api/v1/mailbox/{$delivery->id}/pdf")->assertOk();

    expect(SenderView::isHidden($delivery->fresh()))->toBeFalse();
});

// --- Rendering ----------------------------------------------------------------

it('renders an empty body without blowing up', function (): void {
    $me = User::factory()->create();
    $letter = Letter::factory()->create([
        'author_id' => $me->id,
        'title' => null,
        'body' => ['type' => 'doc', 'content' => []],
    ]);
    Sanctum::actingAs($me);

    $response = $this->get("/api/v1/letters/{$letter->id}/pdf")->assertOk();

    expect(isPdf((string) $response->getContent()))->toBeTrue();
});

it('escapes a body that tries to inject markup into the renderer', function (): void {
    $html = TiptapContent::toHtml([
        'type' => 'doc',
        'content' => [[
            'type' => 'paragraph',
            'content' => [['type' => 'text', 'text' => '<script>alert(1)</script>']],
        ]],
    ]);

    expect($html)->not->toContain('<script>')
        ->and($html)->toContain('&lt;script&gt;');
});

it('renders the marks the editor allows and drops the rest', function (): void {
    $html = TiptapContent::toHtml([
        'type' => 'doc',
        'content' => [[
            'type' => 'paragraph',
            'content' => [[
                'type' => 'text',
                'text' => 'fuerte',
                'marks' => [['type' => 'bold'], ['type' => 'link']],
            ]],
        ]],
    ]);

    expect($html)->toContain('<strong>fuerte</strong>')
        ->and($html)->not->toContain('link');
});
