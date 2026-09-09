<?php

declare(strict_types=1);

/*
 * The catalogue served by GET /api/v1/letters/styles. Kept in config so new
 * papers/stamps can ship without an app release (docs/api/cartas.md). `locked`
 * is reserved for styles a user has yet to unlock.
 */
return [

    'papers' => [
        ['key' => 'parchment', 'name' => 'Pergamino', 'texture' => 'linen'],
        ['key' => 'cream_laid', 'name' => 'Crema verjurado', 'texture' => 'laid'],
        ['key' => 'ivory_smooth', 'name' => 'Marfil liso', 'texture' => 'smooth'],
        ['key' => 'kraft', 'name' => 'Kraft', 'texture' => 'rough'],
    ],

    'fonts' => [
        ['key' => 'cormorant', 'name' => 'Cormorant', 'css_family' => "'Cormorant Garamond', serif"],
        ['key' => 'eb_garamond', 'name' => 'EB Garamond', 'css_family' => "'EB Garamond', serif"],
        ['key' => 'lora', 'name' => 'Lora', 'css_family' => "'Lora', serif"],
    ],

    'inks' => [
        ['key' => 'sepia', 'name' => 'Sepia', 'hex' => '#6b4423'],
        ['key' => 'ink_black', 'name' => 'Negro tinta', 'hex' => '#2a231b'],
        ['key' => 'oxblood', 'name' => 'Sangre de toro', 'hex' => '#6d1e2a'],
        ['key' => 'prussian', 'name' => 'Azul de Prusia', 'hex' => '#1f3a4d'],
    ],

    'seals' => [
        ['key' => 'wax_burgundy', 'name' => 'Lacre burdeos', 'hex' => '#8b2635'],
        ['key' => 'wax_forest', 'name' => 'Lacre bosque', 'hex' => '#3f5a3a'],
        ['key' => 'wax_gold', 'name' => 'Lacre oro viejo', 'hex' => '#a1815c'],
    ],

    'sigils' => [
        ['key' => 'violet', 'name' => 'Violeta'],
        ['key' => 'lily', 'name' => 'Azucena'],
        ['key' => 'feather', 'name' => 'Pluma'],
        ['key' => 'compass', 'name' => 'Brújula'],
    ],

    'stamps' => [
        ['key' => 'lavender_field', 'name' => 'Campo de lavanda'],
        ['key' => 'lighthouse', 'name' => 'Faro'],
        ['key' => 'northern_birds', 'name' => 'Aves del norte'],
    ],

    'borders' => [
        ['key' => 'none', 'name' => 'Sin marco'],
        ['key' => 'art_nouveau_thin', 'name' => 'Art nouveau fino'],
        ['key' => 'deco_corners', 'name' => 'Esquinas déco'],
    ],
];
