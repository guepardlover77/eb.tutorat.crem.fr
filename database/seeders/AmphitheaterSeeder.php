<?php

namespace Database\Seeders;

use App\Models\Amphitheater;
use Illuminate\Database\Seeder;

class AmphitheaterSeeder extends Seeder
{
    public function run(): void
    {
        $amphitheaters = [
            ['name' => 'Debré gauche',  'capacity' => 125, 'sort_order' => 1],
            ['name' => 'Debré droit',   'capacity' => 120, 'sort_order' => 2],
            ['name' => 'Debré haut',    'capacity' => 154, 'sort_order' => 3],
            ['name' => 'Côme Bas',      'capacity' => 86,  'sort_order' => 4],
            ['name' => 'Côme Haut',     'capacity' => 86,  'sort_order' => 5],
            ['name' => 'Beauchamps',    'capacity' => 75,  'sort_order' => 6],
            ['name' => 'Rambaud',       'capacity' => 58,  'sort_order' => 7],
            ['name' => 'Tourette',      'capacity' => 78,  'sort_order' => 8],
            ['name' => 'Lefèvre',       'capacity' => 43,  'sort_order' => 9],
        ];

        foreach ($amphitheaters as $data) {
            Amphitheater::firstOrCreate(['name' => $data['name']], $data);
        }
    }
}
