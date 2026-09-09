<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per occurrence that has *something* attached to it: a letter assigned
 * ahead of time, and/or a materialised delivery. Occurrences with neither stay
 * purely virtual — computed on the fly from the schedule (ADR-0002).
 *
 * The unique (schedule_id, occurrence_date) makes materialisation idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letter_schedule_occurrences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('schedule_id')->constrained('letter_schedules')->cascadeOnDelete();
            $table->date('occurrence_date');

            $table->foreignUuid('letter_id')->nullable()->constrained('letters')->nullOnDelete();
            $table->foreignUuid('delivery_id')->nullable()->constrained('letter_deliveries')->nullOnDelete();

            $table->timestamp('materialized_at')->nullable();

            $table->timestamps();

            $table->unique(['schedule_id', 'occurrence_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_schedule_occurrences');
    }
};
