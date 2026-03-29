<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CarMakesModelsSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('models')->truncate();
        DB::table('makes')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // [name_ar => [name_en, models => [[ar, en], ...]]]
        $makes = [
            'هيونداي' => [
                'en' => 'Hyundai',
                'models' => [
                    ['إلنترا',  'Elantra'],
                    ['أكسنت',   'Accent'],
                    ['توسان',   'Tucson'],
                    ['سوناتا',  'Sonata'],
                ],
            ],
            'كيا' => [
                'en' => 'Kia',
                'models' => [
                    ['سيراتو',   'Cerato'],
                    ['سبورتاج',  'Sportage'],
                    ['بيكانتو',  'Picanto'],
                    ['كارنفال',  'Carnival'],
                ],
            ],
            'تويوتا' => [
                'en' => 'Toyota',
                'models' => [
                    ['كورولا', 'Corolla'],
                    ['يارس',   'Yaris'],
                    ['كامري',  'Camry'],
                    ['راف 4',  'RAV4'],
                ],
            ],
            'نيسان' => [
                'en' => 'Nissan',
                'models' => [
                    ['صني',    'Sunny'],
                    ['قشقاي',  'Qashqai'],
                    ['سنترا',  'Sentra'],
                ],
            ],
            'شيفروليه' => [
                'en' => 'Chevrolet',
                'models' => [
                    ['أفيو',    'Aveo'],
                    ['أوبترا',  'Optra'],
                    ['كابتيفا', 'Captiva'],
                ],
            ],
            'بي إم دبليو' => [
                'en' => 'BMW',
                'models' => [
                    ['320i', '320i'],
                    ['X5',   'X5'],
                    ['X3',   'X3'],
                ],
            ],
            'مرسيدس' => [
                'en' => 'Mercedes',
                'models' => [
                    ['C200', 'C200'],
                    ['E200', 'E200'],
                    ['GLC',  'GLC'],
                ],
            ],
            'غير ذلك' => [
                'en' => 'Other',
                'models' => [],
            ],
        ];

        foreach ($makes as $makeAr => $makeData) {
            $makeId = DB::table('makes')->insertGetId([
                'name'       => $makeAr,
                'name_en'    => $makeData['en'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $models = $makeData['models'];
            $models[] = ['غير ذلك', 'Other'];

            foreach ($models as [$modelAr, $modelEn]) {
                DB::table('models')->insert([
                    'make_id'    => $makeId,
                    'name'       => $modelAr,
                    'name_en'    => $modelEn,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
