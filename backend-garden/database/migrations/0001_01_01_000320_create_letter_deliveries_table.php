<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The heart of the system: one row = one letter delivered to one person on one
 * date. Status and timestamps live here, never on `letters` (ADR-0001).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letter_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('letter_id')->constrained('letters')->cascadeOnDelete();

            // Denormalised for fast "my outbox" / "my mailbox" queries.
            $table->foreignUuid('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('recipient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_email')->nullable(); // Off-platform invite (Fase 2).
            $table->uuid('schedule_id')->nullable(); // FK added with letter_schedules (Fase 2).

            $table->string('status')->default('queued');
            $table->string('delivery_mode')->default('direct');
            $table->string('tier')->default('standard'); // Chosen at send, used at dispatch.

            $table->timestamp('scheduled_for'); // When it must LEAVE the office.
            $table->timestamp('dispatched_at')->nullable(); // When it actually left.
            $table->unsignedInteger('transit_duration_minutes')->nullable();
            $table->timestamp('estimated_delivery_at')->nullable(); // Shown to the sender (fuzzy).
            $table->timestamp('delivered_at')->nullable(); // Target while in_transit, real on delivered.
            $table->timestamp('read_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);

            $table->boolean('is_anonymous')->default(false);
            $table->boolean('is_favorite')->default(false);
            $table->boolean('allow_read_receipt')->default(true);
            $table->timestamp('reveal_sender_at')->nullable();
            $table->uuid('dispatch_batch_id')->nullable(); // Idempotent batch claim.

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_deliveries');
    }
};
