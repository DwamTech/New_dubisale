<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CategoryMainSection;
use App\Models\CategorySubSection;
use Illuminate\Database\Seeder;

class CategorySectionsSeeder extends Seeder
{
    public function run(): void
    {
        // Format: 'slug' => [ 'Main Name' => ['en' => 'Main EN', 'subs' => [['ar','en'],...]] ]
        $sections = $this->getSections();

        foreach ($sections as $slug => $mainSubs) {
            $category = Category::where('slug', $slug)->first();
            if (!$category) continue;

            $mainSort = 1;

            foreach ($mainSubs as $mainAr => $mainData) {
                $mainEn  = $mainData['en'] ?? null;
                $subList = $mainData['subs'] ?? [];

                $main = CategoryMainSection::updateOrCreate(
                    ['category_id' => $category->id, 'name' => $mainAr],
                    ['name_en' => $mainEn, 'sort_order' => $mainSort++, 'is_active' => true]
                );

                if (!collect($subList)->contains(fn($s) => $s[0] === 'غير ذلك')) {
                    $subList[] = ['غير ذلك', 'Other'];
                }

                $subOrder = 1;
                foreach ($subList as [$subAr, $subEn]) {
                    CategorySubSection::updateOrCreate(
                        ['category_id' => $category->id, 'main_section_id' => $main->id, 'name' => $subAr],
                        ['name_en' => $subEn, 'sort_order' => $subAr === 'غير ذلك' ? 9999 : $subOrder++, 'is_active' => true]
                    );
                }
            }

            // قسم رئيسي "غير ذلك" لكل category
            $otherMain = CategoryMainSection::updateOrCreate(
                ['category_id' => $category->id, 'name' => 'غير ذلك'],
                ['name_en' => 'Other', 'sort_order' => 9999, 'is_active' => true]
            );
            CategorySubSection::updateOrCreate(
                ['category_id' => $category->id, 'main_section_id' => $otherMain->id, 'name' => 'غير ذلك'],
                ['name_en' => 'Other', 'sort_order' => 1, 'is_active' => true]
            );
        }
    }

    private function getSections(): array
    {
        return [
            'other' => [
                'أخرى' => ['en' => 'Other', 'subs' => [['غير ذلك', 'Other']]],
            ],

            'stores' => [
                'سوبر ماركت' => ['en' => 'Supermarket', 'subs' => [
                    ['بقالة عامة', 'General Grocery'], ['ميني ماركت', 'Mini Market'],
                    ['هايبر ماركت صغير', 'Small Hypermarket'], ['سوبر ماركت حي', 'Neighborhood Supermarket'],
                ]],
                'مول تجاري' => ['en' => 'Shopping Mall', 'subs' => [
                    ['مول متكامل', 'Full Mall'], ['سنتر تجاري', 'Commercial Center'], ['سوق مفتوح', 'Open Market'],
                ]],
                'متاجر ملابس' => ['en' => 'Clothing Stores', 'subs' => [
                    ['ملابس رجالي', "Men's Clothing"], ['ملابس حريمي', "Women's Clothing"],
                    ['ملابس أطفال', "Children's Clothing"], ['أحذية وحقائب', 'Shoes & Bags'],
                ]],
                'متاجر متخصصة' => ['en' => 'Specialty Stores', 'subs' => [
                    ['صيدلية', 'Pharmacy'], ['محل عطور', 'Perfume Shop'],
                    ['محل أدوات تجميل', 'Beauty Shop'], ['محل هدايا وإكسسوارات', 'Gifts & Accessories'],
                ]],
            ],

            'restaurants' => [
                'مطاعم وجبات سريعة' => ['en' => 'Fast Food', 'subs' => [
                    ['برجر', 'Burger'], ['شاورما', 'Shawarma'], ['بيتزا', 'Pizza'], ['فرايد تشيكن', 'Fried Chicken'],
                ]],
                'مطاعم شرقية' => ['en' => 'Eastern Restaurants', 'subs' => [
                    ['مشويات', 'Grills'], ['مأكولات مصرية', 'Egyptian Food'],
                    ['مأكولات سورية', 'Syrian Food'], ['مأكولات خليجية', 'Gulf Food'],
                ]],
                'مطاعم غربية' => ['en' => 'Western Restaurants', 'subs' => [
                    ['إيطالي', 'Italian'], ['فرنسي', 'French'], ['أمريكي', 'American'],
                ]],
                'محلات كافيه وحلويات' => ['en' => 'Cafes & Sweets', 'subs' => [
                    ['كافيه', 'Cafe'], ['حلويات شرقية', 'Oriental Sweets'],
                    ['حلويات غربية', 'Western Sweets'], ['آيس كريم', 'Ice Cream'],
                ]],
            ],

            'groceries' => [
                'محلات بقالة' => ['en' => 'Grocery Stores', 'subs' => [
                    ['بقالة حي', 'Neighborhood Grocery'], ['ميني ماركت', 'Mini Market'], ['محل تموين', 'Provisions Store'],
                ]],
                'محلات خضار وفاكهة' => ['en' => 'Vegetables & Fruits', 'subs' => [
                    ['خضار فقط', 'Vegetables Only'], ['فاكهة فقط', 'Fruits Only'], ['خضار وفاكهة', 'Vegetables & Fruits'],
                ]],
                'محلات لحوم ودواجن' => ['en' => 'Meat & Poultry', 'subs' => [
                    ['جزارة لحوم', 'Butcher'], ['محل دواجن', 'Poultry Shop'],
                    ['لحوم مجمدة', 'Frozen Meat'], ['دواجن مجمدة', 'Frozen Poultry'],
                ]],
                'محلات منتجات ألبان' => ['en' => 'Dairy Products', 'subs' => [
                    ['ألبان طازجة', 'Fresh Dairy'], ['جبن وألبان', 'Cheese & Dairy'], ['ألبان ومنتجاتها', 'Dairy & Products'],
                ]],
            ],

            'food-products' => [
                'ألبان ومنتجاتها' => ['en' => 'Dairy & Products', 'subs' => [
                    ['جبن', 'Cheese'], ['لبن', 'Yogurt'], ['زبادي', 'Zabadi'], ['قشطة وسمن', 'Cream & Ghee'],
                ]],
                'مجمدات' => ['en' => 'Frozen Foods', 'subs' => [
                    ['خضروات مجمدة', 'Frozen Vegetables'], ['لحوم مجمدة', 'Frozen Meat'],
                    ['دواجن مجمدة', 'Frozen Poultry'], ['أسماك مجمدة', 'Frozen Fish'],
                ]],
                'معلبات' => ['en' => 'Canned Foods', 'subs' => [
                    ['خضار معلب', 'Canned Vegetables'], ['تونة وسردين', 'Tuna & Sardines'],
                    ['صلصات ومعجون طماطم', 'Sauces & Tomato Paste'], ['بقوليات معلبة', 'Canned Legumes'],
                ]],
                'مخبوزات وحلويات' => ['en' => 'Bakery & Sweets', 'subs' => [
                    ['خبز ومعجنات', 'Bread & Pastries'], ['بسكويت وسناكس', 'Biscuits & Snacks'], ['حلويات مغلفة', 'Packaged Sweets'],
                ]],
            ],

            'electronics' => [
                'أجهزة محمولة' => ['en' => 'Mobile Devices', 'subs' => [
                    ['هواتف محمولة', 'Mobile Phones'], ['تابلت', 'Tablets'], ['سماعات وإكسسوارات', 'Headphones & Accessories'],
                ]],
                'أجهزة كمبيوتر' => ['en' => 'Computers', 'subs' => [
                    ['كمبيوتر مكتبي', 'Desktop PC'], ['لابتوب', 'Laptop'],
                    ['شاشات كمبيوتر', 'Monitors'], ['اكسسوارات كمبيوتر', 'Computer Accessories'],
                ]],
                'أجهزة ترفيهية' => ['en' => 'Entertainment Devices', 'subs' => [
                    ['شاشات تلفزيون', 'TV Screens'], ['أجهزة استقبال', 'Receivers'], ['أجهزة ألعاب', 'Gaming Consoles'],
                ]],
                'شبكات وأمن' => ['en' => 'Networks & Security', 'subs' => [
                    ['راوتر ومقويات إشارة', 'Routers & Signal Boosters'],
                    ['أنظمة كاميرات مراقبة', 'CCTV Systems'],
                    ['أجهزة حضور وانصراف', 'Attendance Devices'],
                ]],
            ],

            'home-appliances' => [
                'أجهزة كهربائية كبيرة' => ['en' => 'Large Appliances', 'subs' => [
                    ['ثلاجات', 'Refrigerators'], ['غسالات', 'Washing Machines'],
                    ['بوتاجازات', 'Gas Cookers'], ['ديب فريزر', 'Deep Freezers'],
                ]],
                'أجهزة كهربائية صغيرة' => ['en' => 'Small Appliances', 'subs' => [
                    ['ميكروويف', 'Microwave'], ['غلاية', 'Kettle'],
                    ['خلاط وعجان', 'Blender & Mixer'], ['مكاوي', 'Irons'],
                ]],
                'أجهزة تكييف وتهوية' => ['en' => 'AC & Ventilation', 'subs' => [
                    ['تكييف', 'Air Conditioner'], ['مراوح', 'Fans'], ['شفاطات', 'Exhaust Fans'],
                ]],
            ],

            'home-tools' => [
                'أدوات مطبخ' => ['en' => 'Kitchen Tools', 'subs' => [
                    ['طقم حلل', 'Cookware Set'], ['أدوات تقديم', 'Serving Tools'], ['أدوات تقطيع وتحضير', 'Cutting & Prep Tools'],
                ]],
                'أدوات تنظيف' => ['en' => 'Cleaning Tools', 'subs' => [
                    ['مكانس يدوية', 'Hand Brooms'], ['أدوات تنظيف الأرضيات', 'Floor Cleaning Tools'], ['إكسسوارات تنظيف', 'Cleaning Accessories'],
                ]],
                'أدوات تخزين وتنظيم' => ['en' => 'Storage & Organization', 'subs' => [
                    ['علب تخزين', 'Storage Boxes'], ['منظمات أدراج', 'Drawer Organizers'], ['صناديق بلاستيك', 'Plastic Containers'],
                ]],
            ],

            'furniture' => [
                'أثاث غرف نوم' => ['en' => 'Bedroom Furniture', 'subs' => [
                    ['غرف نوم كاملة', 'Complete Bedroom Sets'], ['دواليب', 'Wardrobes'], ['سرائر', 'Beds'],
                ]],
                'أثاث غرف معيشة' => ['en' => 'Living Room Furniture', 'subs' => [
                    ['ركنة', 'Corner Sofa'], ['أنتريه', 'Entrance Hall'], ['طقم صالون', 'Salon Set'], ['مكتبات وتلفزيونات', 'Bookshelves & TV Units'],
                ]],
                'أثاث مطابخ وسفرات' => ['en' => 'Kitchen & Dining Furniture', 'subs' => [
                    ['سفرات', 'Dining Sets'], ['كراسي منفردة', 'Single Chairs'], ['وحدات تخزين مطبخ', 'Kitchen Storage Units'],
                ]],
                'مفروشات منزلية' => ['en' => 'Home Furnishings', 'subs' => [
                    ['سجاد', 'Carpets'], ['ستائر', 'Curtains'], ['مفارش', 'Bed Sheets'], ['أغطية ووسائد', 'Covers & Pillows'],
                ]],
            ],

            'health' => [
                'مراكز طبية' => ['en' => 'Medical Centers', 'subs' => [
                    ['مراكز أشعة', 'Radiology Centers'], ['مراكز تحاليل', 'Lab Centers'], ['مراكز علاج طبيعي', 'Physical Therapy Centers'],
                ]],
                'صيدليات' => ['en' => 'Pharmacies', 'subs' => [
                    ['صيدليات خدمة 24 ساعة', '24-Hour Pharmacies'], ['صيدليات نهارية', 'Daytime Pharmacies'],
                ]],
                'متاجر معدات طبية' => ['en' => 'Medical Equipment Stores', 'subs' => [
                    ['أجهزة قياس ضغط وسكر', 'BP & Sugar Monitors'], ['أدوات طبية منزلية', 'Home Medical Tools'], ['مستلزمات عيادات', 'Clinic Supplies'],
                ]],
            ],

            'education' => [
                'مدارس ومعاهد' => ['en' => 'Schools & Institutes', 'subs' => [
                    ['مدارس لغات', 'Language Schools'], ['مدارس دولية', 'International Schools'], ['معاهد تدريب', 'Training Institutes'],
                ]],
                'مراكز دروس خصوصية' => ['en' => 'Tutoring Centers', 'subs' => [
                    ['مراكز تقوية', 'Remedial Centers'], ['سناتر', 'Study Centers'],
                ]],
                'مكتبات وأدوات مدرسية' => ['en' => 'Libraries & School Supplies', 'subs' => [
                    ['مكتبة عامة', 'General Library'], ['مستحضرات فنية وقرطاسية', 'Art & Stationery Supplies'],
                ]],
            ],

            'shipping' => [
                'شركات شحن داخلي' => ['en' => 'Domestic Shipping', 'subs' => [
                    ['شحن محافظات', 'Governorate Shipping'], ['توصيل محلي داخل المدينة', 'Local City Delivery'],
                ]],
                'شركات شحن دولي' => ['en' => 'International Shipping', 'subs' => [
                    ['شحن جوي', 'Air Freight'], ['شحن بحري', 'Sea Freight'], ['شحن بري دولي', 'International Land Freight'],
                ]],
                'خدمات توصيل محلي' => ['en' => 'Local Delivery Services', 'subs' => [
                    ['ديليفري مطاعم', 'Restaurant Delivery'], ['توصيل طلبات عامة', 'General Order Delivery'],
                ]],
            ],

            'mens-clothes' => [
                'ملابس رجالي كاجوال' => ['en' => "Men's Casual Wear", 'subs' => [
                    ['تيشيرتات وقمصان', 'T-Shirts & Shirts'], ['بناطيل جينز', 'Jeans'], ['ترينجات وملابس رياضية', 'Tracksuits & Sportswear'],
                ]],
                'ملابس رجالي رسمية' => ['en' => "Men's Formal Wear", 'subs' => [
                    ['بدل رسمية', 'Formal Suits'], ['قمصان كلاسيك', 'Classic Shirts'], ['بنطلونات قماش', 'Dress Pants'],
                ]],
                'أحذية رجالي' => ['en' => "Men's Shoes", 'subs' => [
                    ['أحذية رسمية', 'Formal Shoes'], ['أحذية رياضية', 'Sports Shoes'], ['أحذية كاجوال', 'Casual Shoes'],
                ]],
                'إكسسوارات رجالي' => ['en' => "Men's Accessories", 'subs' => [
                    ['أحزمة', 'Belts'], ['محافظ', 'Wallets'], ['كرافات وربطات عنق', 'Ties & Neckties'],
                ]],
            ],

            'watches-jewelry' => [
                'ساعات يد' => ['en' => 'Wristwatches', 'subs' => [
                    ['ساعات رجالي', "Men's Watches"], ['ساعات حريمي', "Women's Watches"], ['ساعات أطفال', "Kids' Watches"],
                ]],
                'إكسسوارات ومجوهرات' => ['en' => 'Accessories & Jewelry', 'subs' => [
                    ['ذهب', 'Gold'], ['فضة', 'Silver'], ['إكسسوارات تقليد', 'Imitation Accessories'],
                ]],
                'محلات صيانة وإصلاح' => ['en' => 'Repair Shops', 'subs' => [
                    ['تصليح ساعات', 'Watch Repair'], ['تصليح مجوهرات', 'Jewelry Repair'],
                ]],
            ],

            'free-professions' => [
                'خدمات فنية' => ['en' => 'Technical Services', 'subs' => [
                    ['كهربائي', 'Electrician'], ['سبّاك', 'Plumber'], ['نجّار', 'Carpenter'], ['نقّاش', 'Painter'],
                ]],
                'خدمات مكتبية' => ['en' => 'Office Services', 'subs' => [
                    ['كاتب عدل', 'Notary'], ['ترجمة', 'Translation'], ['خدمات طباعة وتصوير', 'Printing & Copying'],
                ]],
                'خدمات استشارية' => ['en' => 'Consulting Services', 'subs' => [
                    ['استشارات قانونية', 'Legal Consulting'], ['استشارات مالية', 'Financial Consulting'], ['استشارات تسويق', 'Marketing Consulting'],
                ]],
            ],

            'kids-toys' => [
                'لعب أطفال' => ['en' => "Children's Toys", 'subs' => [
                    ['لعب تعليمية', 'Educational Toys'], ['عرايس ودمى', 'Dolls'], ['ألعاب إلكترونية', 'Electronic Games'], ['ألعاب تركيب', 'Building Toys'],
                ]],
                'مستلزمات أطفال' => ['en' => "Children's Supplies", 'subs' => [
                    ['ملابس أطفال', "Children's Clothing"], ['مستلزمات رضّع', 'Baby Supplies'], ['أدوات إطعام', 'Feeding Tools'],
                ]],
                'أدوات تنموية وترفيهية' => ['en' => 'Developmental & Recreational Tools', 'subs' => [
                    ['مراجيح', 'Swings'], ['دراجات أطفال', "Kids' Bikes"], ['ألعاب خارجية', 'Outdoor Games'],
                ]],
            ],

            'gym' => [
                'صالات جيم' => ['en' => 'Gyms', 'subs' => [
                    ['جيم رجالي', "Men's Gym"], ['جيم حريمي', "Women's Gym"], ['جيم مختلط', 'Mixed Gym'],
                ]],
                'مراكز لياقة' => ['en' => 'Fitness Centers', 'subs' => [
                    ['كروس فيت', 'CrossFit'], ['يوجا وبيلاتس', 'Yoga & Pilates'], ['تمارين شخصية (Personal Training)', 'Personal Training'],
                ]],
                'متاجر أجهزة رياضية' => ['en' => 'Sports Equipment Stores', 'subs' => [
                    ['أجهزة كارديو', 'Cardio Machines'], ['أجهزة حديد', 'Weight Machines'], ['إكسسوارات رياضية', 'Sports Accessories'],
                ]],
            ],

            'construction' => [
                'مواد بناء' => ['en' => 'Building Materials', 'subs' => [
                    ['أسمنت وحديد', 'Cement & Steel'], ['رمل وزلط وطوب', 'Sand, Gravel & Bricks'], ['بلوكات وبلاطات', 'Blocks & Tiles'],
                ]],
                'مواد تشطيبات' => ['en' => 'Finishing Materials', 'subs' => [
                    ['دهانات', 'Paints'], ['سيراميك وبورسلين', 'Ceramic & Porcelain'], ['أرضيات وخامات تغطية', 'Flooring & Covering'],
                ]],
                'مقاولات وتنفيذ' => ['en' => 'Contracting & Execution', 'subs' => [
                    ['مقاولات عامة', 'General Contracting'], ['تشطيبات كاملة', 'Full Finishing'], ['ترميم وصيانة مباني', 'Building Renovation'],
                ]],
            ],

            'maintenance' => [
                'صيانة كهرباء' => ['en' => 'Electrical Maintenance', 'subs' => [
                    ['صيانة لوحات كهرباء', 'Electrical Panel Maintenance'], ['صيانة إنارة', 'Lighting Maintenance'],
                ]],
                'صيانة سباكة' => ['en' => 'Plumbing Maintenance', 'subs' => [
                    ['إصلاح مواسير', 'Pipe Repair'], ['تركيب أدوات صحية', 'Sanitary Fixtures Installation'],
                ]],
                'صيانة عامة' => ['en' => 'General Maintenance', 'subs' => [
                    ['صيانة منازل', 'Home Maintenance'], ['صيانة محلات', 'Shop Maintenance'],
                ]],
            ],

            'car-services' => [
                'مراكز صيانة' => ['en' => 'Service Centers', 'subs' => [
                    ['صيانة ميكانيكا', 'Mechanical Maintenance'], ['صيانة كهرباء سيارات', 'Auto Electrical'], ['صيانة عفشة', 'Suspension Maintenance'],
                ]],
                'خدمات إطارات وزيوت' => ['en' => 'Tires & Oil Services', 'subs' => [
                    ['تغيير زيت', 'Oil Change'], ['ترصيص وزن', 'Wheel Balancing'], ['إصلاح كاوتش', 'Tire Repair'],
                ]],
                'خدمات خارجية' => ['en' => 'Exterior Services', 'subs' => [
                    ['غسيل سيارات', 'Car Wash'], ['تلميع وحماية', 'Polishing & Protection'], ['خدمات متنقلة', 'Mobile Services'],
                ]],
            ],

            'home-services' => [
                'تنظيف منازل' => ['en' => 'Home Cleaning', 'subs' => [
                    ['تنظيف دوري', 'Regular Cleaning'], ['تنظيف بعد التشطيب', 'Post-Construction Cleaning'],
                ]],
                'خدمات حدائق' => ['en' => 'Garden Services', 'subs' => [
                    ['تنسيق حدائق', 'Garden Landscaping'], ['ري وتشجير', 'Irrigation & Planting'],
                ]],
                'خدمات منزلية أخرى' => ['en' => 'Other Home Services', 'subs' => [
                    ['نقل عفش', 'Furniture Moving'], ['تركيب أثاث', 'Furniture Assembly'],
                ]],
            ],

            'lighting-decor' => [
                'محلات وحدات إضاءة' => ['en' => 'Lighting Stores', 'subs' => [
                    ['نجف وثريات', 'Chandeliers'], ['سبوت لايت', 'Spotlights'], ['كشافات خارجية', 'Outdoor Floodlights'],
                ]],
                'ديكور داخلي' => ['en' => 'Interior Decor', 'subs' => [
                    ['ورق حائط', 'Wallpaper'], ['أسقف معلقة', 'False Ceilings'], ['ديكورات جبس', 'Gypsum Decor'],
                ]],
                'ديكور خارجي' => ['en' => 'Exterior Decor', 'subs' => [
                    ['واجهات', 'Facades'], ['ديكورات حدائق', 'Garden Decor'],
                ]],
            ],

            'animals' => [
                'طيور' => ['en' => 'Birds', 'subs' => [
                    ['ببغاء', 'Parrots'], ['حمام', 'Pigeons'], ['دواجن', 'Poultry'], ['عصافير الزينة', 'Ornamental Birds'],
                ]],
                'حيوانات أليفة' => ['en' => 'Pets', 'subs' => [
                    ['قطط', 'Cats'], ['كلاب', 'Dogs'], ['أرانب', 'Rabbits'], ['أسماك زينة', 'Ornamental Fish'],
                ]],
                'مستلزمات تربية' => ['en' => 'Pet Supplies', 'subs' => [
                    ['أقفاص', 'Cages'], ['أعلاف', 'Feed'], ['أدوية بيطرية', 'Veterinary Medicines'],
                ]],
            ],

            'farm-products' => [
                'منتجات مزارع' => ['en' => 'Farm Products', 'subs' => [
                    ['خضروات طازجة', 'Fresh Vegetables'], ['فاكهة طازجة', 'Fresh Fruits'], ['ألبان من المزرعة', 'Farm Dairy'],
                ]],
                'منتجات مصانع غذائية' => ['en' => 'Food Factory Products', 'subs' => [
                    ['معلبات', 'Canned Goods'], ['مشروبات', 'Beverages'], ['مخبوزات', 'Baked Goods'],
                ]],
                'منتجات صناعية' => ['en' => 'Industrial Products', 'subs' => [
                    ['منتجات بلاستيكية', 'Plastic Products'], ['منتجات معدنية خفيفة', 'Light Metal Products'],
                ]],
            ],

            'wholesale' => [
                'مواد غذائية جملة' => ['en' => 'Food Wholesale', 'subs' => [
                    ['سوبر ماركت جملة', 'Wholesale Supermarket'], ['مورد غذائيات', 'Food Supplier'],
                ]],
                'ملابس وأحذية جملة' => ['en' => 'Clothing & Shoes Wholesale', 'subs' => [
                    ['جملة ملابس', 'Clothing Wholesale'], ['جملة أحذية', 'Shoes Wholesale'],
                ]],
                'أدوات منزلية جملة' => ['en' => 'Household Tools Wholesale', 'subs' => [
                    ['جملة بلاستيك', 'Plastic Wholesale'], ['جملة أدوات مطبخ', 'Kitchen Tools Wholesale'],
                ]],
            ],

            'production-lines' => [
                'خطوط إنتاج كاملة' => ['en' => 'Complete Production Lines', 'subs' => [
                    ['خطوط تعبئة وتغليف', 'Packaging Lines'], ['خطوط تصنيع غذائي', 'Food Manufacturing Lines'], ['خطوط مياه ومشروبات', 'Water & Beverage Lines'],
                ]],
                'ماكينات منفردة' => ['en' => 'Individual Machines', 'subs' => [
                    ['ماكينات لحام وتغليف', 'Welding & Packaging Machines'], ['ماكينات طباعة', 'Printing Machines'], ['ماكينات قص وتقطيع', 'Cutting Machines'],
                ]],
                'خامات إنتاج' => ['en' => 'Production Raw Materials', 'subs' => [
                    ['خامات بلاستيك', 'Plastic Raw Materials'], ['خامات ورق وكرتون', 'Paper & Cardboard'], ['خامات معدنية', 'Metal Raw Materials'],
                ]],
            ],

            'light-vehicles' => [
                'دراجات' => ['en' => 'Bicycles & Motorcycles', 'subs' => [
                    ['دراجات هوائية', 'Bicycles'], ['دراجات نارية خفيفة', 'Light Motorcycles'],
                ]],
                'مركبات خفيفة' => ['en' => 'Light Vehicles', 'subs' => [
                    ['توك توك', 'Tuk-Tuk'], ['ميني كار', 'Mini Car'],
                ]],
                'إكسسوارات وخدمات' => ['en' => 'Accessories & Services', 'subs' => [
                    ['خدمات صيانة', 'Maintenance Services'], ['إكسسوارات وكماليات', 'Accessories & Add-ons'],
                ]],
            ],

            'heavy-transport' => [
                'شاحنات ونقل ثقيل' => ['en' => 'Trucks & Heavy Transport', 'subs' => [
                    ['سيارات نقل', 'Transport Vehicles'], ['تريلات', 'Trailers'], ['لودر وجرافة', 'Loader & Bulldozer'],
                ]],
                'معدات إنشائية ثقيلة' => ['en' => 'Heavy Construction Equipment', 'subs' => [
                    ['حفارات', 'Excavators'], ['ونش رفع', 'Cranes'], ['معدات حفر', 'Drilling Equipment'],
                ]],
                'خدمات نقل ثقيل' => ['en' => 'Heavy Transport Services', 'subs' => [
                    ['نقل بضائع', 'Cargo Transport'], ['نقل معدات', 'Equipment Transport'],
                ]],
            ],

            'tools' => [
                'عدد يدوية' => ['en' => 'Hand Tools', 'subs' => [
                    ['مفكات ومفاتيح', 'Screwdrivers & Wrenches'], ['شواكيش ومناشير', 'Hammers & Saws'],
                ]],
                'عدد كهربائية' => ['en' => 'Power Tools', 'subs' => [
                    ['دريل', 'Drill'], ['صاروخ', 'Angle Grinder'], ['ماكنات قص', 'Cutting Machines'],
                ]],
                'مستلزمات ورش' => ['en' => 'Workshop Supplies', 'subs' => [
                    ['مستلزمات لحام', 'Welding Supplies'], ['إكسسوارات صناعية', 'Industrial Accessories'],
                ]],
            ],

            'missing' => [
                'أشخاص مفقودين' => ['en' => 'Missing Persons', 'subs' => [
                    ['أطفال مفقودين', 'Missing Children'], ['بالغين مفقودين', 'Missing Adults'],
                ]],
                'مقتنيات مفقودة' => ['en' => 'Lost Items', 'subs' => [
                    ['هويات وأوراق رسمية', 'IDs & Official Documents'], ['موبايلات وأجهزة', 'Phones & Devices'], ['حقائب ومحافظ', 'Bags & Wallets'],
                ]],
            ],

            'spare-parts' => [
                'الموتور وناقل الحركة' => ['en' => 'Engine & Transmission', 'subs' => [
                    ['موتور كامل', 'Complete Engine'], ['وش سلندر', 'Cylinder Head'], ['بساتم', 'Pistons'],
                    ['فتيس مانيوال', 'Manual Gearbox'], ['فتيس أوتوماتيك', 'Automatic Gearbox'],
                ]],
                'العفشة والفرامل' => ['en' => 'Suspension & Brakes', 'subs' => [
                    ['مساعدين', 'Shock Absorbers'], ['مقصات', 'Control Arms'], ['طنابير وفرامل', 'Drums & Brakes'], ['جنوط وكاوتش', 'Rims & Tires'],
                ]],
                'الكهرباء والإلكترونيات' => ['en' => 'Electrical & Electronics', 'subs' => [
                    ['بطارية', 'Battery'], ['دينامو ومارش', 'Alternator & Starter'], ['ضفيرة كهرباء', 'Wiring Harness'],
                    ['وحدة رفع زجاج', 'Window Regulator'], ['حساسات ووحدات تحكم', 'Sensors & Control Units'],
                ]],
                'الهيكل والصاج' => ['en' => 'Body & Sheet Metal', 'subs' => [
                    ['أكصدامات', 'Bumpers'], ['رفارف', 'Fenders'], ['أبواب', 'Doors'], ['شنطة وكبوت', 'Trunk & Hood'],
                ]],
                'الصالون والداخلي' => ['en' => 'Interior & Cabin', 'subs' => [
                    ['تابلوه', 'Dashboard'], ['فرش ومراتب', 'Seats & Cushions'], ['طارة وفتيس', 'Steering Wheel & Gear Knob'], ['أرضيات وسجاد', 'Floor Mats & Carpets'],
                ]],
                'إكسسوارات ولوازم' => ['en' => 'Accessories & Supplies', 'subs' => [
                    ['كاسيت وشاشات', 'Car Audio & Screens'], ['إكسسوارات تجميلية', 'Cosmetic Accessories'],
                    ['أغطية ومفارش', 'Covers & Mats'], ['مستلزمات تنظيف', 'Cleaning Supplies'],
                ]],
            ],

            'jobs' => [
                'إدارة وسكرتارية' => ['en' => 'Administration & Secretarial', 'subs' => [
                    ['مدير موارد بشرية', 'HR Manager'], ['مدير مكتب', 'Office Manager'], ['سكرتارية', 'Secretary'],
                    ['موظف إداري', 'Administrative Staff'], ['مساعد شخصي', 'Personal Assistant'],
                ]],
                'مبيعات وتسويق' => ['en' => 'Sales & Marketing', 'subs' => [
                    ['مندوب مبيعات', 'Sales Representative'], ['مدير مبيعات', 'Sales Manager'],
                    ['مسوق رقمي', 'Digital Marketer'], ['أخصائي تسويق', 'Marketing Specialist'], ['مروج منتجات', 'Product Promoter'],
                ]],
                'محاسبة ومالية' => ['en' => 'Accounting & Finance', 'subs' => [
                    ['محاسب', 'Accountant'], ['مدير حسابات', 'Accounts Manager'], ['مراجع مالي', 'Financial Auditor'],
                    ['أمين صندوق', 'Cashier'], ['محلل مالي', 'Financial Analyst'],
                ]],
                'تكنولوجيا معلومات واتصالات' => ['en' => 'IT & Communications', 'subs' => [
                    ['مبرمج ومطور ويب', 'Programmer & Web Developer'], ['مصمم جرافيك', 'Graphic Designer'],
                    ['دعم فني', 'Technical Support'], ['مهندس شبكات', 'Network Engineer'], ['مدير أنظمة', 'Systems Administrator'],
                ]],
                'طب وتمريض وصيدلة' => ['en' => 'Medicine, Nursing & Pharmacy', 'subs' => [
                    ['طبيب عام', 'General Practitioner'], ['طبيب أسنان', 'Dentist'], ['صيدلي', 'Pharmacist'],
                    ['ممرض', 'Nurse'], ['فني مختبر', 'Lab Technician'],
                ]],
                'هندسة وإنشاءات' => ['en' => 'Engineering & Construction', 'subs' => [
                    ['مهندس مدني', 'Civil Engineer'], ['مهندس معماري', 'Architect'], ['مهندس ميكانيكا', 'Mechanical Engineer'],
                    ['مهندس كهرباء', 'Electrical Engineer'], ['رسام هندسي', 'Technical Drafter'],
                ]],
                'تدريس وتدريب' => ['en' => 'Teaching & Training', 'subs' => [
                    ['مدرس لغة عربية', 'Arabic Teacher'], ['مدرس لغة إنجليزية', 'English Teacher'],
                    ['مدرس علوم ورياضيات', 'Science & Math Teacher'], ['محفظ قرآن', 'Quran Teacher'], ['مدرب رياضي', 'Sports Trainer'],
                ]],
                'سياحة وفنادق وضيافة' => ['en' => 'Tourism, Hotels & Hospitality', 'subs' => [
                    ['طباخ وشيف', 'Cook & Chef'], ['موظف استقبال', 'Receptionist'], ['ويتر / كابتن', 'Waiter / Captain'],
                    ['عامل نظافة (Housekeeping)', 'Housekeeping'], ['مرشد سياحي', 'Tour Guide'],
                ]],
                'حرف وصناعات وخدمات' => ['en' => 'Crafts, Industries & Services', 'subs' => [
                    ['سائق (رخصة خاصة/مهنية)', 'Driver (Private/Professional License)'], ['كهربائي', 'Electrician'],
                    ['سباك', 'Plumber'], ['نجار', 'Carpenter'], ['فني تكييف وتبريد', 'AC & Refrigeration Technician'],
                    ['عامل إنتاج', 'Production Worker'], ['أمن وحراسة', 'Security Guard'],
                ]],
                'خدمة عملاء' => ['en' => 'Customer Service', 'subs' => [
                    ['موظف كول سنتر', 'Call Center Agent'], ['أخصائي خدمة عملاء', 'Customer Service Specialist'], ['استقبال عملاء', 'Customer Reception'],
                ]],
            ],
        ];
    }
}
