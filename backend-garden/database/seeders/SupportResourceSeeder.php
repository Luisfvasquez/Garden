<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SupportResource;
use Illuminate\Database\Seeder;

/**
 * Real crisis lines for the launch countries plus an international fallback.
 * Numbers verified 2026-09; review periodically (docs/moderacion.md).
 */
class SupportResourceSeeder extends Seeder
{
    public function run(): void
    {
        $resources = [
            [
                'country_code' => null,
                'topic' => 'self_harm',
                'name' => 'Befrienders Worldwide',
                'description' => 'Directorio internacional de líneas de escucha y prevención del suicidio.',
                'phone' => null,
                'url' => 'https://www.befrienders.org',
                'hours' => '24/7',
                'languages' => ['es', 'en'],
                'priority' => 100,
            ],
            [
                'country_code' => 'ES',
                'topic' => 'self_harm',
                'name' => 'Línea 024 de atención a la conducta suicida',
                'description' => 'Atención telefónica gratuita y confidencial del Ministerio de Sanidad.',
                'phone' => '024',
                'url' => 'https://www.sanidad.gob.es/linea024',
                'hours' => '24/7',
                'languages' => ['es', 'ca', 'eu', 'gl'],
                'priority' => 90,
            ],
            [
                'country_code' => 'ES',
                'topic' => 'general',
                'name' => 'Teléfono de la Esperanza',
                'description' => 'Orientación en crisis emocionales.',
                'phone' => '717 003 717',
                'url' => 'https://telefonodelaesperanza.org',
                'hours' => '24/7',
                'languages' => ['es'],
                'priority' => 70,
            ],
            [
                'country_code' => 'MX',
                'topic' => 'self_harm',
                'name' => 'Línea de la Vida',
                'description' => 'Orientación y apoyo psicológico de la Secretaría de Salud.',
                'phone' => '800 911 2000',
                'url' => 'https://www.gob.mx/salud/conadic',
                'hours' => '24/7',
                'languages' => ['es'],
                'priority' => 90,
            ],
            [
                'country_code' => 'AR',
                'topic' => 'self_harm',
                'name' => 'Centro de Asistencia al Suicida (CAS)',
                'description' => 'Línea gratuita de ayuda en crisis.',
                'phone' => '135',
                'url' => 'https://www.asistenciaalsuicida.org.ar',
                'hours' => '24/7',
                'languages' => ['es'],
                'priority' => 90,
            ],
            [
                'country_code' => 'US',
                'topic' => 'self_harm',
                'name' => '988 Suicide & Crisis Lifeline',
                'description' => 'Free and confidential support, 24 hours a day.',
                'phone' => '988',
                'sms' => '988',
                'url' => 'https://988lifeline.org',
                'hours' => '24/7',
                'languages' => ['en', 'es'],
                'priority' => 90,
            ],
        ];

        foreach ($resources as $resource) {
            SupportResource::query()->updateOrCreate(
                ['country_code' => $resource['country_code'], 'name' => $resource['name']],
                [...$resource, 'is_active' => true],
            );
        }
    }
}
