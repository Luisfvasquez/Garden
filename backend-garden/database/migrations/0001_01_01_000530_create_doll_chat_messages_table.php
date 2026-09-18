<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Doll chat (docs/api/dolls.md, ADR-0005). `body` is encrypted at the model
 * layer exactly like `letters.body` — it is the most intimate text in the
 * product and never sits in plaintext (spec §seguridad).
 *
 * `sender_id` is nullable because `system` messages have no human author, and
 * because a deleted account must not take the other party's history with it.
 *
 * Rows are purged 90 days after the request closes (PurgeOldDollChatsJob).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doll_chat_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('doll_request_id')->constrained('doll_requests')->cascadeOnDelete();
            $table->foreignUuid('sender_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('type')->default('text');

            // Encrypted at rest (`encrypted` cast) — never queried by content.
            $table->text('body')->nullable();

            // Versioned draft: {title, body: <tiptap doc>, version}.
            $table->jsonb('draft_payload')->nullable();
            $table->unsignedInteger('draft_version')->nullable();
            $table->timestamp('draft_approved_at')->nullable();

            $table->string('attachment_path')->nullable();

            // Set by the PII guard when the message looked like a contact
            // exchange: the message still goes through, both parties see a
            // warning (docs/api/dolls.md § Salvaguardas).
            $table->jsonb('pii_flags')->nullable();

            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // The only read pattern: one request's timeline, oldest first.
            $table->index(['doll_request_id', 'created_at']);

            // Draft versions are unique per request, so a retry can't fork history.
            $table->unique(['doll_request_id', 'draft_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doll_chat_messages');
    }
};
