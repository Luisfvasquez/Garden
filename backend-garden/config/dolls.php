<?php

declare(strict_types=1);

/*
 * Auto Memory Dolls (docs/api/dolls.md, ADR-0005, ADR-0012).
 */
return [

    /*
     * How long a closed request's chat transcript survives before
     * PurgeOldDollChatsJob deletes it. Declared in the terms — changing this
     * number is a policy change, not a tuning knob.
     */
    'chat_retention_days' => (int) env('DOLL_CHAT_RETENTION_DAYS', 90),

    /*
     * A Doll with fewer than this many ratings shows "sin valoraciones
     * suficientes" instead of an average. One 5-star review is not a rating,
     * it is an anecdote, and showing it as "5.0" misleads the next client.
     */
    'min_ratings_to_display' => (int) env('DOLL_MIN_RATINGS_TO_DISPLAY', 3),

];
