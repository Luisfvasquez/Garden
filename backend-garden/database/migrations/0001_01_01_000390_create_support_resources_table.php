<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crisis / support helplines shown when the moderation filter detects a
 * self-harm signal, and always reachable from settings
 * (docs/api/comunidad-notificaciones.md, backend-garden/docs/moderacion.md).
 *
 * `country_code = null` means a global fallback returned alongside any country.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_resources', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->char('country_code', 2)->nullable();
            $table->string('topic')->nullable();

            $table->string('name');
            $table->text('description')->nullable();
            $table->string('phone')->nullable();
            $table->string('sms')->nullable();
            $table->string('url')->nullable();
            $table->string('hours')->nullable();
            $table->jsonb('languages')->nullable();

            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['country_code', 'is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_resources');
    }
};
