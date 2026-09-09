<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blog comments (docs/api/blog.md). One level of nesting only — a reply points
 * at a top-level comment and nothing deeper. Anonymous is allowed; the real
 * author is always recorded for moderation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('post_id')->constrained('public_posts')->cascadeOnDelete();
            $table->foreignUuid('author_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('parent_id')->nullable();

            $table->text('body');
            $table->boolean('is_anonymous')->default(false);
            $table->string('moderation_status')->default('approved');

            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['post_id', 'published_at']);
        });

        // Self-referencing FK added after the table exists (one level of nesting).
        Schema::table('comments', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('comments')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
