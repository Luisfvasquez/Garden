<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\FeatureFlag;
use Illuminate\Database\Seeder;

class FeatureFlagSeeder extends Seeder
{
    /**
     * Every module is born behind a flag (../CLAUDE.md rule 5). Default: off.
     */
    public function run(): void
    {
        $flags = [
            'letters' => 'Redacción y envío de cartas',
            'mailbox' => 'Buzón del destinatario',
            'schedules' => 'Envíos recurrentes programados',
            'blog' => 'Blog público de la comunidad',
            'bottle_at_sea' => 'Botella al mar (destinatario aleatorio)',
            'dolls' => 'Auto Memory Dolls y su chat',
            'web_push' => 'Notificaciones Web Push',
        ];

        foreach ($flags as $key => $description) {
            FeatureFlag::query()->updateOrCreate(
                ['key' => $key],
                ['description' => $description],
            );
        }
    }
}
