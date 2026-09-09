<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A reply draft points back at the delivery it answers (docs/api/entregas-buzon.md,
 * `POST /mailbox/{id}/reply`). Added after `letter_deliveries` exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->foreignUuid('in_reply_to_delivery_id')
                ->nullable()
                ->after('doll_request_id')
                ->constrained('letter_deliveries')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('in_reply_to_delivery_id');
        });
    }
};
