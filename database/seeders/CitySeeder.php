<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Governorate;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $govCities = [
            'القاهرة' => [
                ['name' => 'مدينة نصر',   'name_en' => 'Nasr City'],
                ['name' => 'مصر الجديدة', 'name_en' => 'Heliopolis'],
                ['name' => 'حلوان',        'name_en' => 'Helwan'],
                ['name' => 'المعادي',      'name_en' => 'Maadi'],
            ],
            'الجيزة' => [
                ['name' => 'الدقي',         'name_en' => 'Dokki'],
                ['name' => '6 أكتوبر',      'name_en' => '6th of October'],
                ['name' => 'الهرم',          'name_en' => 'Haram'],
                ['name' => 'الشيخ زايد',    'name_en' => 'Sheikh Zayed'],
            ],
            'الإسكندرية' => [
                ['name' => 'حي وسط الإسكندرية', 'name_en' => 'Alexandria Downtown'],
                ['name' => 'العجمي',              'name_en' => 'Agami'],
                ['name' => 'سموحة',               'name_en' => 'Smouha'],
                ['name' => 'برج العرب',            'name_en' => 'Borg El Arab'],
            ],
            'الدقهلية' => [
                ['name' => 'المنصورة',    'name_en' => 'Mansoura'],
                ['name' => 'ميت غمر',     'name_en' => 'Mit Ghamr'],
                ['name' => 'طلخا',         'name_en' => 'Talha'],
                ['name' => 'السنبلاوين',  'name_en' => 'Sinbillawin'],
            ],
            'الشرقية' => [
                ['name' => 'الزقازيق',           'name_en' => 'Zagazig'],
                ['name' => 'العاشر من رمضان',    'name_en' => '10th of Ramadan'],
                ['name' => 'بلبيس',               'name_en' => 'Bilbeis'],
                ['name' => 'منيا القمح',          'name_en' => 'Minya Al Qamh'],
            ],
            'القليوبية' => [
                ['name' => 'بنها',          'name_en' => 'Banha'],
                ['name' => 'شبرا الخيمة',  'name_en' => 'Shubra El Kheima'],
                ['name' => 'قليوب',          'name_en' => 'Qalyub'],
                ['name' => 'الخانكة',       'name_en' => 'Khanka'],
            ],
            'أسوان' => [
                ['name' => 'أسوان',    'name_en' => 'Aswan City'],
                ['name' => 'إدفو',     'name_en' => 'Edfu'],
                ['name' => 'كوم أمبو', 'name_en' => 'Kom Ombo'],
                ['name' => 'دراو',     'name_en' => 'Daraw'],
            ],
            'السويس' => [
                ['name' => 'السويس',   'name_en' => 'Suez City'],
                ['name' => 'الجناين',  'name_en' => 'Al Janayeen'],
                ['name' => 'عتاقة',    'name_en' => 'Ataka'],
                ['name' => 'فيصل',     'name_en' => 'Faisal'],
            ],
        ];

        foreach ($govCities as $govName => $cities) {
            $governorate = Governorate::where('name', $govName)->first();
            if (!$governorate) continue;

            // إضافة "غير ذلك"
            $cities[] = ['name' => 'غير ذلك', 'name_en' => 'Other'];

            foreach ($cities as $cityData) {
                City::updateOrCreate(
                    ['governorate_id' => $governorate->id, 'name' => $cityData['name']],
                    ['name_en' => $cityData['name_en']]
                );
            }
        }

        // محافظة "غير ذلك"
        $other = Governorate::updateOrCreate(
            ['name' => 'غير ذلك'],
            ['name_en' => 'Other']
        );
        City::updateOrCreate(
            ['governorate_id' => $other->id, 'name' => 'غير ذلك'],
            ['name_en' => 'Other']
        );
    }
}
