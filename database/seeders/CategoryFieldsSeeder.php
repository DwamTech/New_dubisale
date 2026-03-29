<?php

namespace Database\Seeders;

use App\Models\CategoryField;
use Illuminate\Database\Seeder;

class CategoryFieldsSeeder extends Seeder
{
    public function run(): void
    {
        $realEstateFields = [
            [
                'category_slug'  => 'real_estate',
                'field_name'     => 'property_type',
                'display_name'   => 'نوع العقار',
                'display_name_en'=> 'Property Type',
                'type'           => 'string',
                'options'        => ['فيلا', 'شقة', 'أرض', 'استوديو', 'محل تجاري', 'مكتب', 'غير ذلك'],
                'options_en'     => ['Villa', 'Apartment', 'Land', 'Studio', 'Commercial Shop', 'Office', 'Other'],
                'required'       => true,
                'filterable'     => true,
                'sort_order'     => 1,
            ],
            [
                'category_slug'  => 'real_estate',
                'field_name'     => 'contract_type',
                'display_name'   => 'نوع العقد',
                'display_name_en'=> 'Contract Type',
                'type'           => 'string',
                'options'        => ['بيع', 'إيجار', 'غير ذلك'],
                'options_en'     => ['Sale', 'Rent', 'Other'],
                'required'       => true,
                'filterable'     => true,
                'sort_order'     => 2,
            ],
        ];

        $carsRentFields = [
            [
                'category_slug'  => 'cars_rent',
                'field_name'     => 'year',
                'display_name'   => 'السنة',
                'display_name_en'=> 'Year',
                'type'           => 'string',
                'options'        => array_merge(range(2000, 2030), ['غير ذلك']),
                'options_en'     => array_merge(array_map('strval', range(2000, 2030)), ['Other']),
                'required'       => true,
                'filterable'     => true,
                'sort_order'     => 3,
            ],
            [
                'category_slug'  => 'cars_rent',
                'field_name'     => 'driver_option',
                'display_name'   => 'السائق',
                'display_name_en'=> 'Driver Option',
                'type'           => 'string',
                'options'        => ['بدون سائق', 'بسائق', 'غير ذلك'],
                'options_en'     => ['Without Driver', 'With Driver', 'Other'],
                'required'       => true,
                'filterable'     => true,
                'sort_order'     => 4,
            ],
        ];

        $jobsFields = [
            [
                'category_slug'  => 'jobs',
                'field_name'     => 'job_type',
                'display_name'   => 'التصنيف',
                'display_name_en'=> 'Job Type',
                'type'           => 'string',
                'options'        => ['مطلوب للعمل', 'باحث عن عمل'],
                'options_en'     => ['Hiring', 'Job Seeker'],
                'required'       => true,
                'filterable'     => true,
                'sort_order'     => 1,
            ],
            [
                'category_slug'  => 'jobs',
                'field_name'     => 'specialization',
                'display_name'   => 'التخصص',
                'display_name_en'=> 'Specialization',
                'type'           => 'string',
                'options'        => [
                    'محاسب', 'مهندس', 'دكتور', 'صيدلي', 'ممرض', 'مدرس', 'محامي',
                    'مبرمج', 'مصمم جرافيك', 'مسوق', 'مندوب مبيعات', 'سكرتير',
                    'مدير موارد بشرية', 'كهربائي', 'سباك', 'نجار', 'سائق', 'طباخ',
                    'أمن وحراسة', 'خدمة عملاء', 'محلل بيانات', 'موظف إداري',
                    'فني صيانة', 'عامل إنتاج',
                ],
                'options_en'     => [
                    'Accountant', 'Engineer', 'Doctor', 'Pharmacist', 'Nurse', 'Teacher', 'Lawyer',
                    'Programmer', 'Graphic Designer', 'Marketer', 'Sales Representative', 'Secretary',
                    'HR Manager', 'Electrician', 'Plumber', 'Carpenter', 'Driver', 'Cook',
                    'Security Guard', 'Customer Service', 'Data Analyst', 'Administrative Staff',
                    'Maintenance Technician', 'Production Worker',
                ],
                'required'       => true,
                'filterable'     => true,
                'sort_order'     => 2,
            ],
            [
                'category_slug'  => 'jobs',
                'field_name'     => 'salary',
                'display_name'   => 'الراتب',
                'display_name_en'=> 'Salary',
                'type'           => 'decimal',
                'options'        => [],
                'options_en'     => [],
                'required'       => true,
                'filterable'     => false,
                'rules_json'     => ['min:0'],
                'sort_order'     => 3,
            ],
            [
                'category_slug'  => 'jobs',
                'field_name'     => 'contact_via',
                'display_name'   => 'التواصل عبر',
                'display_name_en'=> 'Contact Via',
                'type'           => 'string',
                'options'        => [],
                'options_en'     => [],
                'required'       => true,
                'filterable'     => false,
                'sort_order'     => 4,
            ],
        ];

        $carFields = [
            [
                'category_slug'  => 'cars',
                'field_name'     => 'year',
                'display_name'   => 'السنة',
                'display_name_en'=> 'Year',
                'type'           => 'string',
                'options'        => array_merge(range(1990, 2025), ['غير ذلك']),
                'options_en'     => array_merge(array_map('strval', range(1990, 2025)), ['Other']),
                'required'       => true,
                'filterable'     => true,
                'sort_order'     => 1,
            ],
            [
                'category_slug'  => 'cars',
                'field_name'     => 'kilometers',
                'display_name'   => 'الكيلو متر',
                'display_name_en'=> 'Kilometers',
                'type'           => 'string',
                'options'        => [
                    '0 - 10،000', '10،000 - 50،000', '50،000 - 100،000',
                    '100،000 - 200،000', 'أكثر من 200،000', 'غير ذلك',
                ],
                'options_en'     => [
                    '0 - 10,000', '10,000 - 50,000', '50,000 - 100,000',
                    '100,000 - 200,000', 'More than 200,000', 'Other',
                ],
                'required'       => true,
                'filterable'     => true,
                'sort_order'     => 2,
            ],
            [
                'category_slug'  => 'cars',
                'field_name'     => 'fuel_type',
                'display_name'   => 'نوع الوقود',
                'display_name_en'=> 'Fuel Type',
                'type'           => 'string',
                'options'        => ['بنزين', 'ديزل', 'غاز', 'كهرباء', 'غير ذلك'],
                'options_en'     => ['Petrol', 'Diesel', 'Gas', 'Electric', 'Other'],
                'required'       => true,
                'filterable'     => true,
                'sort_order'     => 3,
            ],
            [
                'category_slug'  => 'cars',
                'field_name'     => 'transmission',
                'display_name'   => 'الفتيس',
                'display_name_en'=> 'Transmission',
                'type'           => 'string',
                'options'        => ['أوتوماتيك', 'مانيوال', 'غير ذلك'],
                'options_en'     => ['Automatic', 'Manual', 'Other'],
                'required'       => true,
                'filterable'     => true,
                'sort_order'     => 4,
            ],
            [
                'category_slug'  => 'cars',
                'field_name'     => 'exterior_color',
                'display_name'   => 'اللون الخارجي',
                'display_name_en'=> 'Exterior Color',
                'type'           => 'string',
                'options'        => ['أبيض', 'أسود', 'أزرق', 'رمادي', 'فضي', 'أحمر', 'غير ذلك'],
                'options_en'     => ['White', 'Black', 'Blue', 'Gray', 'Silver', 'Red', 'Other'],
                'required'       => true,
                'filterable'     => true,
                'sort_order'     => 5,
            ],
            [
                'category_slug'  => 'cars',
                'field_name'     => 'type',
                'display_name'   => 'النوع',
                'display_name_en'=> 'Body Type',
                'type'           => 'string',
                'options'        => ['سيدان', 'هاتشباك', 'SUV', 'كروس أوفر', 'بيك أب', 'كوبيه', 'كشف', 'غير ذلك'],
                'options_en'     => ['Sedan', 'Hatchback', 'SUV', 'Crossover', 'Pickup', 'Coupe', 'Convertible', 'Other'],
                'required'       => true,
                'filterable'     => true,
                'sort_order'     => 6,
            ],
        ];

        $teachersFields = [
            [
                'category_slug'  => 'teachers',
                'field_name'     => 'name',
                'display_name'   => 'الاسم',
                'display_name_en'=> 'Name',
                'type'           => 'string',
                'options'        => [],
                'options_en'     => [],
                'required'       => true,
                'filterable'     => false,
                'sort_order'     => 0,
            ],
            [
                'category_slug'  => 'teachers',
                'field_name'     => 'specialization',
                'display_name'   => 'التخصص',
                'display_name_en'=> 'Specialization',
                'type'           => 'string',
                'options'        => [
                    'رياضيات', 'فيزياء', 'كيمياء', 'أحياء', 'لغة عربية',
                    'لغة إنجليزية', 'لغة فرنسية', 'دراسات اجتماعية', 'حاسب آلي',
                    'علوم شرعية', 'رياض أطفال', 'مرحلة ابتدائية', 'مرحلة إعدادية',
                    'مرحلة ثانوية', 'غير ذلك',
                ],
                'options_en'     => [
                    'Mathematics', 'Physics', 'Chemistry', 'Biology', 'Arabic Language',
                    'English Language', 'French Language', 'Social Studies', 'Computer Science',
                    'Islamic Studies', 'Kindergarten', 'Primary School', 'Middle School',
                    'High School', 'Other',
                ],
                'required'       => true,
                'filterable'     => true,
                'sort_order'     => 1,
            ],
        ];

        $doctorsFields = [
            [
                'category_slug'  => 'doctors',
                'field_name'     => 'name',
                'display_name'   => 'الاسم',
                'display_name_en'=> 'Name',
                'type'           => 'string',
                'options'        => [],
                'options_en'     => [],
                'required'       => true,
                'filterable'     => false,
                'sort_order'     => 0,
            ],
            [
                'category_slug'  => 'doctors',
                'field_name'     => 'specialization',
                'display_name'   => 'التخصص',
                'display_name_en'=> 'Specialization',
                'type'           => 'string',
                'options'        => [
                    'باطنة', 'أطفال', 'قلب وأوعية دموية', 'عظام', 'نساء وتوليد',
                    'أنف وأذن وحنجرة', 'جلدية', 'أسنان', 'عيون', 'مخ وأعصاب',
                    'مسالك بولية', 'جراحة عامة', 'علاج طبيعي', 'تحاليل طبية', 'أشعة', 'غير ذلك',
                ],
                'options_en'     => [
                    'Internal Medicine', 'Pediatrics', 'Cardiology', 'Orthopedics', 'Obstetrics & Gynecology',
                    'ENT', 'Dermatology', 'Dentistry', 'Ophthalmology', 'Neurology',
                    'Urology', 'General Surgery', 'Physical Therapy', 'Laboratory Medicine', 'Radiology', 'Other',
                ],
                'required'       => true,
                'filterable'     => true,
                'sort_order'     => 1,
            ],
        ];

        $allFields = array_merge(
            $realEstateFields,
            $carFields,
            $carsRentFields,
            $jobsFields,
            $teachersFields,
            $doctorsFields,
        );

        $allowedKeys = collect($allFields)
            ->map(fn($f) => $f['category_slug'] . '::' . $f['field_name'])
            ->all();

        CategoryField::all()->each(function (CategoryField $field) use ($allowedKeys) {
            $key = $field->category_slug . '::' . $field->field_name;
            if (!in_array($key, $allowedKeys, true)) {
                $field->delete();
            }
        });

        foreach ($allFields as $field) {
            CategoryField::updateOrCreate(
                [
                    'category_slug' => $field['category_slug'],
                    'field_name'    => $field['field_name'],
                ],
                [
                    'display_name'    => $field['display_name'],
                    'display_name_en' => $field['display_name_en'] ?? null,
                    'type'            => $field['type'] ?? 'string',
                    'options'         => $field['options'] ?? [],
                    'options_en'      => $field['options_en'] ?? [],
                    'required'        => $field['required'] ?? true,
                    'filterable'      => $field['filterable'] ?? true,
                    'is_active'       => true,
                    'sort_order'      => $field['sort_order'] ?? 999,
                    'rules_json'      => $field['rules_json'] ?? null,
                ]
            );
        }
    }
}
