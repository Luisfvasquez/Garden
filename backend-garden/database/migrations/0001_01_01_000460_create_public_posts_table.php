<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The community blog (docs/api/blog.md). Bodies are public, so — unlike letters
 * — they are NOT encrypted; `body_plain` feeds a Postgres tsvector for search
 * (Meilisearch in Fase 4).
 *
 * A `shared_letter` is created with `consent_status = pending` and
 * `published_at = null`; it only goes live once the original author agrees.
 * Real authorship is always stored, even when `is_anonymous`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('author_id')->constrained('users')->cascadeOnDelete();

            $table->string('type');
            $table->string('slug')->unique();
            $table->string('title');
            $table->jsonb('body');
            $table->text('body_plain');
            $table->text('testimonial')->nullable();

            $table->boolean('is_anonymous')->default(false);
            $table->boolean('comments_enabled')->default(true);
            $table->string('visibility')->default('public');
            $table->string('moderation_status')->default('approved');

            // Consent (shared_letter only).
            $table->foreignUuid('source_delivery_id')->nullable()->constrained('letter_deliveries')->nullOnDelete();
            $table->string('consent_status')->default('not_required');
            $table->timestamp('consent_responded_at')->nullable();
            $table->timestamp('consent_denied_until')->nullable();

            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['visibility', 'published_at']);
            $table->index(['type', 'published_at']);
            $table->index(['author_id', 'created_at']);
        });

        DB::statement('ALTER TABLE public_posts ADD COLUMN search_vector tsvector');
        DB::statement('CREATE INDEX idx_public_posts_search ON public_posts USING GIN (search_vector)');
        DB::statement(<<<'SQL'
            CREATE TRIGGER public_posts_search_vector_update
            BEFORE INSERT OR UPDATE OF title, body_plain ON public_posts
            FOR EACH ROW EXECUTE FUNCTION
            tsvector_update_trigger(search_vector, 'pg_catalog.simple', title, body_plain)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('public_posts');
    }
};
