<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Free-form tags for the blog (docs/api/blog.md). Max 3 per post, enforced in
 * the FormRequest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('label');
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();
        });

        Schema::create('taggables', function (Blueprint $table) {
            $table->foreignUuid('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->uuidMorphs('taggable');
            $table->primary(['tag_id', 'taggable_id', 'taggable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
    }
};
