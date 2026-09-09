<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recurring / future letters: "one letter for every birthday for the next ten
 * years" (docs/api/programaciones.md). The schedule stores intent only — local
 * date + local time + frozen timezone. Occurrences are materialised lazily by
 * `GenerateUpcomingDeliveriesJob`, 90 days out (ADR-0002).
 *
 * Never precompute a decade of UTC instants: zones and DST rules change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letter_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('recipient_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name');

            $table->string('recurrence_type');            // RecurrenceType
            $table->date('anchor_date');                  // first (local) occurrence
            $table->string('local_time', 5);              // "HH:MM", applied in `timezone`
            $table->string('timezone');                   // frozen at creation
            $table->unsignedSmallInteger('occurrences_total')->nullable();
            $table->jsonb('custom_dates')->nullable();    // list<"Y-m-d"> for recurrence_type = custom_dates
            $table->string('leap_day_policy')->default('feb_28');
            $table->string('trigger_type')->default('date');

            // The letter to send. Null = a different letter per occurrence,
            // assigned with PUT /schedules/{id}/occurrences/{date}/letter.
            $table->foreignUuid('letter_id')->nullable()->constrained('letters')->nullOnDelete();

            // Send options mirrored onto each materialised delivery.
            $table->string('tier')->default('standard');
            $table->boolean('is_anonymous')->default(false);

            $table->string('status')->default('active');  // ScheduleStatus
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('last_materialized_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'trigger_type']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_schedules');
    }
};
