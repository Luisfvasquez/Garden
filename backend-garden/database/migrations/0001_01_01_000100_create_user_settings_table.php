<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->foreignUuid('user_id')->primary()->constrained()->cascadeOnDelete();

            $table->boolean('notify_email')->default(true);
            $table->boolean('notify_push')->default(true);
            $table->boolean('notify_on_arrival')->default(true);
            $table->boolean('notify_on_dispatch_confirm')->default(false);
            $table->boolean('notify_on_doll_message')->default(true);
            $table->boolean('notify_on_blog_comment')->default(true);
            $table->boolean('share_read_receipts')->default(false);

            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();

            $table->string('theme')->default('system');
            $table->string('preferred_paper_style')->nullable();
            $table->boolean('show_transit_countdown')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
