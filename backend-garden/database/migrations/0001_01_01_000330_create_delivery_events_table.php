<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable audit of every transition; feeds the postal-tracking timeline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('letter_delivery_id')->constrained('letter_deliveries')->cascadeOnDelete();

            $table->string('event');
            $table->timestamp('occurred_at');
            $table->jsonb('metadata')->default('{}'); // fictional sorting office, applied delay…

            $table->timestamp('created_at')->nullable();

            $table->index(['letter_delivery_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_events');
    }
};
