<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every push routes through this buffer so two product rules fall out naturally
 * (docs/api/comunidad-notificaciones.md):
 *
 * - quiet_hours: `deliver_after` is pushed to the end of the user's quiet
 *   window, in their local time.
 * - grouping: `DispatchDuePushesJob` collapses several due rows of the same
 *   `type` for the same user into one notification.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_pushes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('type');
            $table->string('title');
            $table->string('body');
            $table->jsonb('data')->nullable();

            $table->timestamp('deliver_after');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['sent_at', 'deliver_after']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_pushes');
    }
};
