<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Bottle at sea" bookkeeping on the delivery row (docs/api/botella-al-mar.md):
 * the held-for-review timestamp, the single anonymous reply, and the two-sided
 * handshake to open real correspondence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('letter_deliveries', function (Blueprint $table) {
            $table->timestamp('held_at')->nullable()->after('failure_reason');

            // The one reply the recipient is allowed to send back down the same
            // anonymous channel.
            $table->uuid('anonymous_reply_delivery_id')->nullable()->after('held_at');

            // Both parties must opt in before handles are revealed.
            $table->timestamp('open_correspondence_sender_at')->nullable();
            $table->timestamp('open_correspondence_recipient_at')->nullable();
            $table->timestamp('correspondence_opened_at')->nullable();

            $table->index(['delivery_mode', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('letter_deliveries', function (Blueprint $table) {
            $table->dropIndex(['delivery_mode', 'status']);
            $table->dropColumn([
                'held_at',
                'anonymous_reply_delivery_id',
                'open_correspondence_sender_at',
                'open_correspondence_recipient_at',
                'correspondence_opened_at',
            ]);
        });
    }
};
