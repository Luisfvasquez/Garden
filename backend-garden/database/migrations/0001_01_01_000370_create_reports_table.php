<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * User reports on letters, posts, comments, users and Doll chat messages
 * (docs/api/comunidad-notificaciones.md). `minor_safety` / `self_harm` at
 * `critical` severity skip the queue and alert the team.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('reporter_id')->constrained('users')->cascadeOnDelete();

            $table->string('reportable_type');
            $table->uuid('reportable_id');

            $table->string('category');
            $table->text('details')->nullable();
            $table->string('status')->default('open');
            $table->string('severity')->default('low');

            $table->foreignUuid('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->timestamp('handled_at')->nullable();

            $table->timestamps();

            $table->unique(['reporter_id', 'reportable_type', 'reportable_id']);
            $table->index(['reportable_type', 'reportable_id']);
            $table->index(['status', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
