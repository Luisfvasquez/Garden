<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A letter is content and aesthetics only — no recipient, no status (ADR-0001).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('author_id')->constrained('users')->cascadeOnDelete();

            $table->string('title')->nullable();
            $table->text('body'); // Tiptap JSON, encrypted at rest.
            $table->text('body_plain')->nullable(); // Flattened text for search / moderation.
            $table->unsignedInteger('word_count')->default(0);
            $table->jsonb('style')->default('{}');

            $table->string('kind')->default('direct');
            $table->boolean('is_locked')->default(false);
            $table->uuid('doll_request_id')->nullable(); // FK added with the Dolls module (Fase 3).
            $table->string('moderation_status')->default('approved');

            $table->timestamps();
            $table->softDeletes();

            $table->index('author_id');
            $table->index(['kind', 'moderation_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letters');
    }
};
