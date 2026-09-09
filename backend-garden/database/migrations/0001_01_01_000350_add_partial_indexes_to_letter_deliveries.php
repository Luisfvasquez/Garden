<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Partial indexes for the postal clock — documented and standalone (ADR-0006,
 * backend-garden/docs/migraciones.md). The working set stays tiny even as
 * `letter_deliveries` grows without bound.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE INDEX idx_deliveries_dispatch ON letter_deliveries (status, scheduled_for) WHERE status = 'queued'");
        DB::statement("CREATE INDEX idx_deliveries_arrival ON letter_deliveries (status, delivered_at) WHERE status = 'in_transit'");
        DB::statement('CREATE INDEX idx_deliveries_mailbox ON letter_deliveries (recipient_id, status, delivered_at DESC)');
        DB::statement('CREATE INDEX idx_deliveries_sender ON letter_deliveries (sender_id, created_at DESC)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_deliveries_dispatch');
        DB::statement('DROP INDEX IF EXISTS idx_deliveries_arrival');
        DB::statement('DROP INDEX IF EXISTS idx_deliveries_mailbox');
        DB::statement('DROP INDEX IF EXISTS idx_deliveries_sender');
    }
};
