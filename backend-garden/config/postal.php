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

    // The postal clock alarm (postal:health / postal:stats, docs/runbook.md §1).
    'health' => [
        // Nothing dispatched in this long, while overdue work waits, means the
        // clock stopped. The 15 minutes come from docs/jobs-y-colas.md.
        'dispatch_silence_minutes' => (int) env('POSTAL_HEALTH_DISPATCH_SILENCE_MINUTES', 15),

        // Both ticks run every minute, so a delivery a few seconds past its
        // date is normal, not a stall. Only count it as late beyond this.
        'overdue_grace_minutes' => (int) env('POSTAL_HEALTH_OVERDUE_GRACE_MINUTES', 15),

        // Rows reserved with a `dispatch_batch_id` and still `queued`: the
        // dispatcher filters on `dispatch_batch_id IS NULL`, so it will never
        // look at them again (runbook §1, causa 2).
        'orphaned_batch_minutes' => (int) env('POSTAL_HEALTH_ORPHANED_BATCH_MINUTES', 30),

        // Window for the measured average transit reported by postal:stats.
        'transit_sample_days' => 7,

        // The check runs every 5 min and a stopped clock stays stopped: without
        // a cooldown one incident is hundreds of identical emails. 0 = every run.
        'alert_cooldown_minutes' => (int) env('POSTAL_HEALTH_ALERT_COOLDOWN_MINUTES', 60),

        // Where the alarm rings. Empty = log only, no email.
        'alert_email' => env('OPS_ALERT_EMAIL', ''),
    ],

    // Recurring letters (docs/api/programaciones.md).
    'schedules' => [
        // Upper bound on `occurrences_total` / the length of `custom_dates`.
        'max_occurrences' => 60,
        // Only occurrences within this window are materialised into deliveries.
        'materialize_horizon_days' => 90,
    ],

    // "Bottle at sea" — random recipient (docs/api/botella-al-mar.md, ADR-0004).
    'random' => [
        // Sender quotas.
        'daily_quota' => 3,
        'weekly_quota' => 10,
        'min_account_age_days' => 7,

        // Who the RandomRecipientPicker will consider.
        'recipient_active_within_days' => 30,
        'recipient_default_daily_cap' => 3,
        'same_sender_cooldown_days' => 90,

        // Redis pool refresh + how hard the picker tries before giving up.
        'pool_key' => 'random-pool:recipients',
        'pick_attempts' => 8,
    ],
];
