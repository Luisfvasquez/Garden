<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `letter_deliveries.schedule_id` was reserved in the Fase 1 migration; wire its
 * foreign key now that `letter_schedules` exists. A schedule is deleted freely —
 * the deliveries it already produced keep their history, just unlinked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('letter_deliveries', function (Blueprint $table) {
            $table->foreign('schedule_id')->references('id')->on('letter_schedules')->nullOnDelete();
            $table->index(['schedule_id']);
        });
    }

    public function down(): void
    {
        Schema::table('letter_deliveries', function (Blueprint $table) {
            $table->dropForeign(['schedule_id']);
            $table->dropIndex(['schedule_id']);
        });
    }
};
