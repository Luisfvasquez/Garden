<?php

declare(strict_types=1);

/*
 * Content moderation. The app depends on the `ContentModerator` interface only;
 * this file picks the driver behind it (backend-garden/docs/moderacion.md and
 * docs/decisiones/0008-moderacion-driver-agnostica.md).
 *
 * Swap `MODERATION_DRIVER` to `openai` / `perspective` in production without
 * touching a line of application code.
 */
return [

    'driver' => env('MODERATION_DRIVER', 'local'),

    /*
     * LocalModerator: lexicon + regex. Zero external calls, good enough to gate
     * the launch and to run the whole test suite deterministically. The lists
     * below are a starting point — extend them per deployment or move to a
     * hosted classifier.
     */
    'local' => [

        // A verdict at or above `reject` is a hard block (CONTENT_FLAGGED); at or
        // above `flag` it is held for human review; below, it passes.
        'thresholds' => [
            'reject' => (float) env('MODERATION_REJECT_SCORE', 0.85),
            'flag' => (float) env('MODERATION_FLAG_SCORE', 0.45),
        ],

        // Per-category weight added to the score for each distinct match, and
        // whether a single match is enough to force that decision regardless of
        // score. `self_harm` never hard-blocks: the product is about grief and
        // loss, and silencing pain is counter-productive (docs/moderacion.md).
        'categories' => [
            'minor_safety' => ['weight' => 1.0, 'force' => 'reject'],
            'hate' => ['weight' => 0.6, 'force' => null],
            'sexual' => ['weight' => 0.5, 'force' => null],
            'violence' => ['weight' => 0.5, 'force' => null],
            'harassment' => ['weight' => 0.4, 'force' => null],
            'self_harm' => ['weight' => 0.5, 'force' => 'flag'],
            'spam' => ['weight' => 0.35, 'force' => null],
        ],

        // Lowercased, accent-insensitive substring/word matches. Keep phrases
        // specific: false positives on grief content are the main failure mode.
        'lexicons' => [
            'minor_safety' => [
                'menor desnudo', 'nude minor', 'child porn', 'cp trade',
            ],
            'hate' => [
                'subhumano', 'subhuman', 'raza inferior', 'inferior race',
            ],
            'sexual' => [
                'sexo explicito', 'explicit sex', 'nudes', 'send nudes',
            ],
            'violence' => [
                'te voy a matar', 'i will kill you', 'voy a hacerte dano', 'hurt you badly',
            ],
            'harassment' => [
                'eres basura', 'you are trash', 'nadie te quiere', 'nobody wants you',
                'callate para siempre', 'shut up forever',
            ],
            'self_harm' => [
                'quiero suicidarme', 'want to kill myself', 'end my life',
                'no quiero seguir viviendo', "don't want to live",
                'hacerme dano', 'cut myself', 'cortarme',
            ],
            'spam' => [
                'compra ahora', 'buy now', 'click aqui', 'click here',
                'gana dinero rapido', 'make money fast', 'free crypto', 'airdrop gratis',
            ],
        ],
    ],
];
