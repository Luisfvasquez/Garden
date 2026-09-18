<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `letters.doll_request_id` was created nullable back in 000300 with the FK
 * deferred until the Dolls module existed. It exists now.
 *
 * `nullOnDelete`: a purged request must never take the client's letter with it.
 * The letter belongs to the client (author_id = client_id) — the request is
 * only the credit trail (docs/api/dolls.md § Aprobar y cerrar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->foreign('doll_request_id')
                ->references('id')
                ->on('doll_requests')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->dropForeign(['doll_request_id']);
        });
    }
};
