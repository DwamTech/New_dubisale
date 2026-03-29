<?php

namespace Database\Seeders;

use App\Models\Governorate;
use Illuminate\Database\Seeder;

class GovernorateSeeder extends Seeder
{
    public function run(): void
    {
        $governorates = [
            ['name' => 'القاهرة',      'name_en' => 'Cairo'],
            ['name' => 'الجيزة',       'name_en' => 'Giza'],
            ['name' => 'الإسكندرية',   'name_en' => 'Alexandria'],
            ['name' => 'الدقهلية',     'name_en' => 'Dakahlia'],
            ['name' => 'الشرقية',      'name_en' => 'Sharqia'],
            ['name' => 'القليوبية',    'name_en' => 'Qalyubia'],
            ['name' => 'أسوان',        'name_en' => 'Aswan'],
            ['name' => 'السويس',       'name_en' => 'Suez'],
        ];

        foreach ($governorates as $data) {
            Governorate::updateOrCreate(['name' => $data['name']], $data);
        }
    }
}
