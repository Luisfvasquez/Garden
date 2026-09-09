<?php

declare(strict_types=1);

/*
 * The "postal clock": transit tiers, the cancellation grace window and the
 * jitter applied to the delivery estimate shown to the sender.
 * See docs/api/entregas-buzon.md and backend-garden/docs/jobs-y-colas.md.
 */
return [

    // A letter still "in the sorting office": the sender can pull it back for
    // this many minutes after it was actually dispatched. After that it is gone.
    'grace_period_minutes' => (int) env('POSTAL_GRACE_PERIOD_MINUTES', 15),

    // Never deliver faster than this, whatever the tier or geography say.
    'minimum_transit_minutes' => 30,

    // Transit duration windows per tier, in minutes. The calculator picks a
    // value in the range, applies a geographic factor, then a random jitter.
    'tiers' => [
        'express' => ['min' => 30, 'max' => 120, 'monthly_quota' => 3],
        'standard' => ['min' => 120, 'max' => 720, 'monthly_quota' => null],
        'slow' => ['min' => 1440, 'max' => 4320, 'monthly_quota' => null],
    ],

    // ± fraction of noise added to the transit time, and a separate, larger
    // ± fraction used only for the estimate reported to the sender ("the
    // surprise is the product").
    'transit_jitter' => 0.15,
    'estimate_jitter' => 0.20,

    // Multiplier applied when sender and recipient are in different countries.
    'geographic_factor' => [
        'same_country' => 1.0,
        'cross_border' => 1.35,
    ],

    'limits' => [
        'body_max_chars' => 20000,
        'max_open_drafts' => 50,
        'max_recipients' => 10,
        'attachments_per_letter' => 3,
        'image_max_kb' => 5 * 1024,
        'audio_max_seconds' => 60,
    ],

    // Recurring letters (docs/api/programaciones.md).
    'schedules' => [
        // Upper bound on `occurrences_total` / the length of `custom_dates`.
        'max_occurrences' => 60,
        // Only occurrences within this window are materialised into deliveries.
        'materialize_horizon_days' => 90,
    ],
];
