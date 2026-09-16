<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The capacity that makes a user an Auto Memory Doll (docs/api/dolls.md,
 * ADR-0005). `role` on `users` flips to `doll` only once `verified_at` is set
 * by staff — the row can exist, pending review, before that happens.
 *
 * Payment columns are reserved but unused: Dolls launch as volunteers
 * (`rate_type = free`); Stripe Connect is deliberately deferred (ADR-0012).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doll_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();

            $table->string('headline');
            $table->text('bio')->nullable();
            $table->jsonb('specialties')->default('[]');
            $table->jsonb('languages')->default('[]');
            $table->jsonb('tone_tags')->default('[]');

            $table->string('rate_type')->default('free');
            $table->unsignedInteger('rate_amount')->nullable(); // minor currency units
            $table->char('currency', 3)->nullable();

            $table->boolean('is_available')->default(false);
            $table->unsignedTinyInteger('max_concurrent_requests')->default(3);

            // Denormalised from doll_requests — recomputed by RecalculateDollRatingsJob.
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->unsignedInteger('completed_requests_count')->default(0);
            $table->unsignedInteger('response_time_avg_minutes')->nullable();

            $table->jsonb('portfolio')->nullable(); // public samples, with consent

            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['verified_at', 'is_available']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doll_profiles');
    }
};
