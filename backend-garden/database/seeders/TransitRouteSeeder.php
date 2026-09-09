<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\TransitRoute;
use Illuminate\Database\Seeder;

class TransitRouteSeeder extends Seeder
{
    /**
     * Sorting offices for the postal-tracking screen. Names from the show's
     * world (docs/api/entregas-buzon.md).
     */
    public function run(): void
    {
        $routes = [
            ['name' => 'Ruta continental norte', 'min' => 120, 'max' => 600, 'waypoints' => ['Leiden', 'Enkidu', 'Roswell']],
            ['name' => 'Ruta del canal', 'min' => 90, 'max' => 480, 'waypoints' => ['Cattleya', 'Gardarik']],
            ['name' => 'Ruta del sur', 'min' => 180, 'max' => 900, 'waypoints' => ['Mainichi', 'Ctrigall', 'Benedict']],
            ['name' => 'Ruta insular', 'min' => 240, 'max' => 1440, 'waypoints' => ['Iberia', 'Kazaly']],
        ];

        foreach ($routes as $route) {
            TransitRoute::query()->updateOrCreate(
                ['name' => $route['name']],
                ['min_minutes' => $route['min'], 'max_minutes' => $route['max'], 'waypoints' => $route['waypoints']],
            );
        }
    }
}
