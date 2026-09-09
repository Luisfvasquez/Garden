<?php

declare(strict_types=1);

use App\Services\Moderation\PiiScanner;

beforeEach(fn () => $this->scanner = new PiiScanner);

it('detects emails', function (): void {
    expect($this->scanner->scan('hola violet.evergarden@example.com adios'))->toContain('email');
});

it('detects urls', function (): void {
    expect($this->scanner->scan('mira esto https://t.me/violet'))->toContain('url')
        ->and($this->scanner->scan('visita www.telegram.me/violet'))->toContain('url');
});

it('detects social handles', function (): void {
    expect($this->scanner->scan('sígueme en @violet_doll'))->toContain('social_handle');
});

it('detects phone numbers with 9+ digits', function (): void {
    expect($this->scanner->scan('llámame al +34 612 345 678'))->toContain('phone');
});

it('does not mistake dates and years for phone numbers', function (): void {
    expect($this->scanner->hasPii('Nos vimos el 12 de abril de 2027, hará 3 años.'))->toBeFalse();
});

it('returns nothing for clean prose', function (): void {
    expect($this->scanner->scan('Querida Ann, espero que este año te traiga calma.'))->toBe([]);
});
