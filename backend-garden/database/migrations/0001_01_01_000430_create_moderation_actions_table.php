<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail of moderation consequences — legal retention 2 years
 * (backend-garden/docs/moderacion.md). `restrict_random` lands here when a user
 * accrues 2 confirmed reports on random letters (ADR-0004).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderation_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('type');     // ModerationActionType
            $table->string('source');   // ModerationActionSource
            $table->text('reason')->nullable();
            $table->jsonb('context')->nullable();

            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable(); // null = indefinite
            $table->timestamp('lifted_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'type', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_actions');
    }
};
