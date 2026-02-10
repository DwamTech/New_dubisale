<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategoriesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            // 28 categories total - restructured as per client requirements
            
            // Keep as-is (5 categories)
            ['slug' => 'cars',             'name' => 'مبيعات السيارات',             'name_en' => 'Car Sales',               'icon' => 'السيارات.png'],
            ['slug' => 'cars_rent',        'name' => 'إيجار السيارات',             'name_en' => 'Car Rental',               'icon' => 'ايجار_السيارات.png'],
            ['slug' => 'spare-parts',      'name' => 'قطع غيار السيارات',           'name_en' => 'Car Spare Parts',          'icon' => 'قطع_غيار_سيارات.png'],
            ['slug' => 'real_estate',      'name' => 'العقارات',                  'name_en' => 'Real Estate',              'icon' => 'العقارات.png'],
            ['slug' => 'jobs',             'name' => 'الوظائف',                   'name_en' => 'Jobs',                     'icon' => 'الوظائف.png'],
            
            // Unified structure (16 categories)
            ['slug' => 'car-services',     'name' => 'خدمات وصيانة السيارات',      'name_en' => 'Car Services & Maintenance', 'icon' => 'خدمات_صيانه_السيارات.png'],
            ['slug' => 'special-numbers',  'name' => 'أرقام مميزة',                'name_en' => 'Special Numbers',          'icon' => 'ارقام_مميزة.png'],
            ['slug' => 'car-accessories',  'name' => 'اكسسوارات السيارات',         'name_en' => 'Car Accessories',          'icon' => 'اكسسوارات_السيارات.png'],
            ['slug' => 'motorcycles',      'name' => 'دراجات نارية',               'name_en' => 'Motorcycles',              'icon' => 'دراجات_نارية.png'],
            ['slug' => 'mobiles-tablets',  'name' => 'موبايلات وتابليت',           'name_en' => 'Mobiles & Tablets',        'icon' => 'موبايلات_تابليت.png'],
            ['slug' => 'computers',        'name' => 'كمبيوتر ولابتوب',            'name_en' => 'Computers & Laptops',      'icon' => 'كمبيوتر_لابتوب.png'],
            ['slug' => 'electronics',      'name' => 'الإلكترونيات والأجهزة المنزلية', 'name_en' => 'Electronics & Home Appliances', 'icon' => 'الاكترونيات.png'],
            ['slug' => 'furniture',        'name' => 'اثاث ومفروشات والإضاءة',     'name_en' => 'Furniture & Lighting',     'icon' => 'اثاث_ومفروشات.png'],
            ['slug' => 'restaurants',      'name' => 'المطاعم',                    'name_en' => 'Restaurants',              'icon' => 'المطاعم.png'],
            ['slug' => 'watches-jewelry',  'name' => 'الساعات والمجوهرات',         'name_en' => 'Watches & Jewelry',        'icon' => 'الساعات_المجوهرات.png'],
            ['slug' => 'glasses',          'name' => 'النظارات الطبية والشمسية',   'name_en' => 'Medical & Sunglasses',     'icon' => 'نظارات.png'],
            ['slug' => 'shoes',            'name' => 'الأحذية الجلدية والرياضية',  'name_en' => 'Leather & Sports Shoes',   'icon' => 'احذية.png'],
            ['slug' => 'business-services','name' => 'خدمات رجال الأعمال',         'name_en' => 'Business Services',        'icon' => 'خدمات_اعمال.png'],
            ['slug' => 'general-services', 'name' => 'المهن العامة والخدمات',      'name_en' => 'General Professions & Services', 'icon' => 'المهن_الحره_الخدمات.png'],
            ['slug' => 'tools',            'name' => 'عدد وأدوات',                 'name_en' => 'Tools & Equipments',       'icon' => 'عدد_مستلزمات.png'],
            ['slug' => 'garden-camping',   'name' => 'الحديقة والتخييم',           'name_en' => 'Garden & Camping',         'icon' => 'حديقة_تخييم.png'],
            
            // Placeholder categories (7 categories)
            ['slug' => 'empty-1',          'name' => 'فارغ 1',                     'name_en' => 'Empty 1',                  'icon' => 'فارغ.png'],
            ['slug' => 'empty-2',          'name' => 'فارغ 2',                     'name_en' => 'Empty 2',                  'icon' => 'فارغ.png'],
            ['slug' => 'empty-3',          'name' => 'فارغ 3',                     'name_en' => 'Empty 3',                  'icon' => 'فارغ.png'],
            ['slug' => 'empty-4',          'name' => 'فارغ 4',                     'name_en' => 'Empty 4',                  'icon' => 'فارغ.png'],
            ['slug' => 'empty-5',          'name' => 'فارغ 5',                     'name_en' => 'Empty 5',                  'icon' => 'فارغ.png'],
            ['slug' => 'empty-6',          'name' => 'فارغ 6',                     'name_en' => 'Empty 6',                  'icon' => 'فارغ.png'],
            ['slug' => 'empty-7',          'name' => 'فارغ 7',                     'name_en' => 'Empty 7',                  'icon' => 'فارغ.png'],
        ];

        foreach ($categories as $i => $cat) {
            DB::table('categories')->updateOrInsert(
                ['slug' => $cat['slug']],  // الشرط
                [
                    'name'        => $cat['name'],
                    'name_en'     => $cat['name_en'],
                    'icon'        => $cat['icon'],
                    'sort_order'  => $i + 1,       // 👈 الترتيب كما في الريسبونس
                ]
            );
        }
    }
}
