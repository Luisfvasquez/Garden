<?php

declare(strict_types=1);

use App\Enums\ModerationCategory;
use App\Enums\ModerationDecision;
use App\Enums\ModerationSurface;
use App\Services\Moderation\ContentModerator;
use App\Services\Moderation\Drivers\LocalModerator;
use App\Services\Moderation\ModerationContext;
use App\Services\Moderation\PiiScanner;

beforeEach(function (): void {
    $this->mod = new LocalModerator(new PiiScanner, config('moderation.local'));
});

it('is resolved from the container as the configured driver', function (): void {
    expect(app(ContentModerator::class))->toBeInstanceOf(LocalModerator::class);
});

it('approves ordinary grief writing', function (): void {
    $text = 'Te echo de menos cada día desde que te fuiste. Ojalá pudiera abrazarte una vez más.';

    $verdict = $this->mod->check($text, ModerationContext::for(ModerationSurface::BlogPost));

    expect($verdict->decision)->toBe(ModerationDecision::Approved)
        ->and($verdict->categories)->toBe([])
        ->and($verdict->driver)->toBe('local');
});

it('rejects explicit threats of violence with a high score', function (): void {
    $text = 'Te voy a matar cuando te encuentre, no puedes esconderte.';

    $verdict = $this->mod->check($text, ModerationContext::for(ModerationSurface::RandomLetter));

    expect($verdict->blocks())->toBeTrue()
        ->and($verdict->hasCategory(ModerationCategory::Violence))->toBeTrue();
});

it('flags a self-harm signal for human review but never hard-blocks it', function (): void {
    $text = 'A veces pienso que no quiero seguir viviendo con este dolor.';

    $verdict = $this->mod->check($text, ModerationContext::for(ModerationSurface::BlogPost));

    expect($verdict->decision)->toBe(ModerationDecision::Flagged)
        ->and($verdict->isCritical())->toBeTrue()
        ->and($verdict->hasCategory(ModerationCategory::SelfHarm))->toBeTrue();
});

it('hard-rejects minor-safety content regardless of score', function (): void {
    $verdict = $this->mod->check('child porn', ModerationContext::for(ModerationSurface::BlogComment));

    expect($verdict->decision)->toBe(ModerationDecision::Rejected)
        ->and($verdict->isCritical())->toBeTrue();
});

it('blocks contact exchange in a random letter', function (): void {
    $text = 'Escríbeme a maria.tenshi@example.com para seguir hablando.';

    $verdict = $this->mod->check($text, ModerationContext::for(ModerationSurface::RandomLetter));

    expect($verdict->decision)->toBe(ModerationDecision::Rejected)
        ->and($verdict->hasCategory(ModerationCategory::Pii))->toBeTrue();
});

it('ignores PII in Doll chat — that surface only warns, handled by the caller', function (): void {
    $text = 'Mi correo es maria.tenshi@example.com';

    $verdict = $this->mod->check($text, new ModerationContext(ModerationSurface::DollChat));

    expect($verdict->hasCategory(ModerationCategory::Pii))->toBeFalse()
        ->and($verdict->decision)->toBe(ModerationDecision::Approved);
});

it('is accent- and case-insensitive', function (): void {
    $verdict = $this->mod->check('COMPRA AHORA y GANA DINERO RÁPIDO con cripto', ModerationContext::for(ModerationSurface::BlogPost));

    expect($verdict->hasCategory(ModerationCategory::Spam))->toBeTrue()
        ->and($verdict->decision)->toBe(ModerationDecision::Flagged);
});
