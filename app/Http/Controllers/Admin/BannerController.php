<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CategoryBanner;
use App\Traits\LocalizedResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BannerController extends Controller
{
    use LocalizedResponse;
    protected $allowedSlugs = [
        'real_estate', 'cars', 'cars_rent', 'spare-parts', 'stores',
        'restaurants', 'groceries', 'food-products', 'electronics',
        'home-appliances', 'home-tools', 'furniture', 'doctors', 'health',
        'teachers', 'education', 'jobs', 'shipping', 'mens-clothes',
        'watches-jewelry', 'free-professions', 'kids-toys', 'gym',
        'construction', 'maintenance', 'car-services', 'home-services',
        'lighting-decor', 'animals', 'farm-products', 'wholesale',
        'production-lines', 'light-vehicles', 'heavy-transport', 'tools',
        'missing', 'home_ads', 'home', 'unified',
    ];

    public function index()
    {
        $dbBanners = CategoryBanner::all()->keyBy('slug');
        $lang = $this->lang();
        $banners = [];

        foreach ($this->allowedSlugs as $slug) {
            $row = $dbBanners[$slug] ?? null;
            $arUrl = $row?->banner_path    ? asset($row->banner_path)    : null;
            $enUrl = $row?->banner_path_en ? asset($row->banner_path_en) : null;

            if ($lang === 'ar') {
                $banners[] = ['slug' => $slug, 'banner_url' => $arUrl];
            } elseif ($lang === 'en') {
                $banners[] = ['slug' => $slug, 'banner_url' => $enUrl ?? $arUrl];
            } else {
                $banners[] = ['slug' => $slug, 'banner_url' => $arUrl, 'banner_url_en' => $enUrl];
            }
        }

        return response()->json(['success' => true, 'data' => $banners]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'slug'  => 'required|string|in:' . implode(',', $this->allowedSlugs),
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'lang'  => 'nullable|in:ar,en',
        ]);

        $slug = $request->input('slug');
        $lang = $request->input('lang', 'ar');

        $exists = CategoryBanner::where('slug', $slug)->exists();
        $field  = $lang === 'en' ? 'banner_path_en' : 'banner_path';

        if ($exists && $lang === 'ar') {
            return response()->json([
                'success' => false,
                'message' => 'هذا القسم يحتوي على بانر بالفعل. يرجى استخدام التعديل لتغييره.',
                'errors'  => ['slug' => ['This category already has a banner.']],
            ], 422);
        }

        return $this->handleBannerUpload($slug, $request->file('image'), $field);
    }

    public function update(Request $request, $slug)
    {
        if (!in_array($slug, $this->allowedSlugs)) {
            return response()->json([
                'success' => false,
                'message' => 'The selected slug is invalid.',
                'errors'  => ['slug' => ['The selected slug is invalid.']],
            ], 422);
        }

        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'lang'  => 'nullable|in:ar,en',
        ]);

        $lang  = $request->input('lang', 'ar');
        $field = $lang === 'en' ? 'banner_path_en' : 'banner_path';

        return $this->handleBannerUpload($slug, $request->file('image'), $field);
    }

    private function handleBannerUpload($slug, $file, string $field = 'banner_path')
    {
        $lang      = $field === 'banner_path_en' ? 'en' : 'ar';
        $directory = public_path("storage/uploads/banner/{$slug}/{$lang}");

        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        // حذف الصورة القديمة للغة دي بس
        $currentBanner = CategoryBanner::where('slug', $slug)->first();
        if ($currentBanner && $currentBanner->$field) {
            $oldPath = public_path($currentBanner->$field);
            if (File::exists($oldPath)) {
                File::delete($oldPath);
            }
        }

        // حذف كل ملفات الفولدر
        foreach (File::files($directory) as $f) {
            File::delete($f);
        }

        $filename     = Str::random(40) . '.' . $file->getClientOriginalExtension();
        $file->move($directory, $filename);
        $relativePath = "storage/uploads/banner/{$slug}/{$lang}/{$filename}";

        $banner = CategoryBanner::updateOrCreate(
            ['slug' => $slug],
            [$field => $relativePath, 'is_active' => true]
        );

        return response()->json([
            'success' => true,
            'message' => 'Banner updated successfully',
            'data'    => [
                'slug'          => $banner->slug,
                'banner_url'    => $banner->banner_path    ? asset($banner->banner_path)    : null,
                'banner_url_en' => $banner->banner_path_en ? asset($banner->banner_path_en) : null,
            ],
        ]);
    }
}
