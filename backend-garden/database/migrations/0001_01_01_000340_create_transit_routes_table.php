<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fictional postal routes and holidays — flavour for the tracking screen
 * (office names from the anime's universe).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transit_routes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->unsignedInteger('min_minutes');
            $table->unsignedInteger('max_minutes');
            $table->jsonb('waypoints')->default('[]'); // ["Leiden", "Roswell", …]
            $table->timestamps();
        });

        Schema::create('postal_holidays', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('date');
            $table->string('region', 2)->nullable(); // ISO country, null = global
            $table->string('name');
            $table->timestamps();

            $table->unique(['date', 'region']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postal_holidays');
        Schema::dropIfExists('transit_routes');
    }
};
