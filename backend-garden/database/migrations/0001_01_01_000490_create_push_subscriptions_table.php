<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per device a user granted push permission on
 * (docs/api/comunidad-notificaciones.md). Web uses endpoint + keys; native
 * mobile uses a device token instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('platform')->default('web'); // web | ios | android

            // Web Push (RFC 8030 / VAPID).
            $table->text('endpoint')->nullable();
            $table->string('public_key')->nullable();
            $table->string('auth_token')->nullable();

            // Native push.
            $table->string('device_token')->nullable();

            $table->string('device_name')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'endpoint']);
            $table->unique(['user_id', 'device_token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
