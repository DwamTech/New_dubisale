<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryMainSection;
use App\Models\CategorySubSection;
use App\Traits\LocalizedResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Listing;
use Illuminate\Validation\Rule;

class CategorySectionsController extends Controller
{
    use LocalizedResponse;

    public function index(Request $request)
    {
        $slug = $request->query('category_slug');

        if (!$slug) {
            return response()->json(['message' => 'يجب تحديد القسم بواسطة باراميتر category_slug.'], 422);
        }

        $category = Category::where('slug', $slug)->first();
        if (!$category) {
            return response()->json(['message' => 'القسم غير موجود.'], 404);
        }

        $mainSections = CategoryMainSection::with([
            'subSections' => fn($q) => $q->where('is_active', true)->orderBy('sort_order')
        ])
            ->where('category_id', $category->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $mainSections->push((object)[
            'id' => null, 'name' => 'غير ذلك', 'name_en' => 'Other',
            'subSections' => [], 'sort_order' => 9999, 'category_id' => $category->id
        ]);

        return response()->json([
            'category'      => ['id' => $category->id, 'slug' => $category->slug, 'name' => $category->name],
            'main_sections' => $this->localizeCollection($mainSections, ['name']),
        ]);
    }

    /**
     * POST /api/admin/category-sections/{category_slug}
     *
     * body:
     * {
     *   "main_sections": [
     *     {
     *       "id": 1,                    // اختياري (للتعديل)
     *       "name": "ملابس رجالي كاجوال",
     *       "sort_order": 1,            // اختياري
     *       "is_active": true,          // اختياري
     *       "sub_sections": [
     *         {
     *           "id": 10,               // اختياري (للتعديل)
     *           "name": "تيشيرت",
     *           "sort_order": 1,        // اختياري
     *           "is_active": true       // اختياري
     *         }
     *       ]
     *     }
     *   ]
     * }
     */
    // POST /api/admin/category-sections/{category_slug}/main

    public function subSections(CategoryMainSection $mainSection)
    {
        $subSections = $mainSection->subSections()->orderBy('sort_order')->get();
        $subSections->push((object)[
            'id' => null, 'name' => 'غير ذلك', 'name_en' => 'Other',
            'main_section_id' => $mainSection->id, 'category_id' => $mainSection->category_id
        ]);

        return response()->json(
            $this->localizeCollection($subSections, ['name'])
        );
    }
    public function storeMain(Request $request, string $categorySlug)
    {
        $category = Category::where('slug', $categorySlug)->firstOrFail();

        $data = $request->validate([
            'name'    => ['required', 'string', 'max:191'],
            'name_en' => ['nullable', 'string', 'max:191'],
        ]);

        $main = CategoryMainSection::where('category_id', $category->id)
            ->where('name', $data['name'])
            ->first();

        if ($main) {
            return response()->json(['message' => 'قسم رئيسي بهذا الاسم موجود بالفعل لهذا القسم.'], 422);
        }

        $main = CategoryMainSection::create([
            'category_id' => $category->id,
            'name'        => $data['name'],
            'name_en'     => $data['name_en'] ?? null,
            'sort_order'  => (CategoryMainSection::where('category_id', $category->id)->max('sort_order') ?? 0) + 1,
            'is_active'   => true,
        ]);

        CategorySubSection::create([
            'category_id'     => $category->id,
            'main_section_id' => $main->id,
            'name'            => 'غير ذلك',
            'name_en'         => 'Other',
            'sort_order'      => 9999,
            'is_active'       => true,
        ]);

        $main->load(['subSections' => fn($q) => $q->where('is_active', true)->orderBy('sort_order')]);

        return response()->json($main, 201);
    }


    public function addSubSections(Request $request, CategoryMainSection $mainSection)
    {
        $data = $request->validate([
            'sub_sections'           => ['required', 'array', 'min:1'],
            'sub_sections.*.name'    => [
                'required', 'string', 'max:191',
                Rule::unique('category_sub_section', 'name')->where(fn($q) => $q
                    ->where('category_id', $mainSection->category_id)
                    ->where('main_section_id', $mainSection->id)
                ),
            ],
            'sub_sections.*.name_en' => ['nullable', 'string', 'max:191'],
        ]);

        $created  = [];
        $sortBase = (int) CategorySubSection::where('category_id', $mainSection->category_id)
            ->where('main_section_id', $mainSection->id)
            ->max('sort_order');
        $order = $sortBase + 1;

        foreach ($data['sub_sections'] as $item) {
            $created[] = CategorySubSection::create([
                'category_id'     => $mainSection->category_id,
                'main_section_id' => $mainSection->id,
                'name'            => $item['name'],
                'name_en'         => $item['name_en'] ?? null,
                'sort_order'      => $order++,
                'is_active'       => true,
            ]);
        }

        return response()->json([
            'main_section_id' => $mainSection->id,
            'sub_sections'    => $created,
        ], 201);
    }


    public function updateMain(Request $request, CategoryMainSection $mainSection)
    {
        $data = $request->validate([
            'name' => [
                'sometimes', 'string', 'max:191',
                Rule::unique('category_main_sections', 'name')
                    ->where(fn($q) => $q->where('category_id', $mainSection->category_id))
                    ->ignore($mainSection->id),
            ],
            'name_en' => ['nullable', 'string', 'max:191'],
        ]);

        $mainSection->update($data);

        return response()->json($mainSection->load('subSections'));
    }

    // DELETE /api/admin/category-sections/main/{mainSection}
    public function destroyMain(CategoryMainSection $mainSection)
    {
        $subIds = $mainSection->subSections()->pluck('id');

        $isUsed = Listing::where('main_section_id', $mainSection->id)
            ->orWhereIn('sub_section_id', $subIds)
            ->exists();

        if ($isUsed) {
            return response()->json([
                'message' => 'لا يمكن حذف هذا القسم الرئيسي لأنه مرتبط بإعلانات أو أقسام فرعية مستخدمة في إعلانات.',
            ], 422);
        }

        $mainSection->subSections()->delete();
        $mainSection->delete();

        return response()->json("Deleted successfully", 204);
    }

    public function updateSub(Request $request, CategorySubSection $subSection)
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:191',
                Rule::unique('category_sub_section', 'name')
                    ->where(fn($q) => $q
                        ->where('category_id', $subSection->category_id)
                        ->where('main_section_id', $subSection->main_section_id)
                    )
                    ->ignore($subSection->id),
            ],
            'name_en' => ['nullable', 'string', 'max:191'],
        ]);

        $subSection->update($data);

        return response()->json($subSection);
    }

    // DELETE /api/admin/category-sections/sub/{subSection}
    public function destroySub(CategorySubSection $subSection)
    {
        $isUsed = Listing::where('sub_section_id', $subSection->id)->exists();

        if ($isUsed) {
            return response()->json([
                'message' => 'لا يمكن حذف هذا القسم الفرعي لأنه مستخدم في إعلانات حالية.',
            ], 422);
        }

        $subSection->delete();

        return response()->json("Deleted successfully", 204);
    }
}
