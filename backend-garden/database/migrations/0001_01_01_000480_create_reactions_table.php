<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Post reactions: heart | tear | flower | candle (docs/api/blog.md). One row
 * per (user, post, type); counts are never exposed publicly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('post_id')->constrained('public_posts')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type');
            $table->timestamps();

            $table->unique(['post_id', 'user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reactions');
    }
};
