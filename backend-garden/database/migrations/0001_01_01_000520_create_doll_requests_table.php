<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A client's request for help from a verified Doll — the state machine
 * described in docs/api/dolls.md. `client_id`/`doll_id` both reference
 * `users` (not `doll_profiles`): the Doll side is the user who owns the
 * verified profile, matching how the rest of the app addresses people.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doll_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('doll_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status')->default('pending');

            $table->string('occasion');
            $table->text('brief_notes')->nullable();
            $table->string('target_recipient_hint')->nullable();
            $table->jsonb('desired_tone')->default('[]');
            $table->timestamp('deadline_at')->nullable();

            // Only meaningful while pending — ExpireStaleDollRequestsJob clears it on transition.
            $table->timestamp('expires_at')->nullable();

            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // Set once, by the client, after completion (Valoraciones — RecalculateDollRatingsJob).
            $table->unsignedTinyInteger('client_rating')->nullable();
            $table->text('client_rating_comment')->nullable();
            $table->timestamp('rated_at')->nullable();

            $table->timestamps();

            $table->index(['doll_id', 'status']);
            $table->index(['client_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doll_requests');
    }
};
