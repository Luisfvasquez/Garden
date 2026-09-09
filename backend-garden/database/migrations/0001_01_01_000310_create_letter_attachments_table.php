<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letter_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('letter_id')->constrained('letters')->cascadeOnDelete();

            $table->string('type'); // image | audio | pressed_flower
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedInteger('size_bytes');
            $table->jsonb('metadata')->default('{}'); // dimensions, duration…

            $table->timestamps();

            $table->index('letter_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_attachments');
    }
};
