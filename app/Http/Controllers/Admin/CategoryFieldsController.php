<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\Admin\StoreCategoryFieldRequest;
use App\Http\Requests\Admin\UpdateCategoryFieldRequest;
use App\Models\Category;
use App\Models\CategoryField;
use App\Models\Governorate;
use App\Models\Make;
use App\Support\Section;
use App\Traits\LocalizedResponse;
use Illuminate\Http\Request;
use App\Models\CategoryMainSection;
use App\Models\CategorySubSection;

class CategoryFieldsController extends Controller
{
    use LocalizedResponse;

    public function index(Request $request)
    {
        $q = CategoryField::query()->orderBy('category_slug')->orderBy('sort_order');

        $slug = $request->query('category_slug');
        if ($slug) $q->where('category_slug', $slug);

        $fields = $q->get();

        $fields->transform(function ($field) {
            if (!empty($field->options) && is_array($field->options)) {
                if (!in_array('غير ذلك', $field->options)) {
                    $field->options = array_merge($field->options, ['غير ذلك']);
                }
            }
            if (!empty($field->options_en) && is_array($field->options_en)) {
                if (!in_array('Other', $field->options_en)) {
                    $field->options_en = array_merge($field->options_en, ['Other']);
                }
            }
            return $field;
        });

        // تطبيق اللغة على الحقول
        $lang = $this->lang();
        if ($lang !== null) {
            $fields = $fields->map(function ($field) {
                return $this->localizeFieldOptions($field->toArray());
            });
        }

        $governorates = Governorate::with('cities')->get();
        if ($lang !== null) {
            $governorates = $this->localizeCollection($governorates, ['name']);
        }

        $section = $slug ? Section::fromSlug($slug) : null;
        $supportsMakeModel = $section?->supportsMakeModel() ?? false;
        $supportsSections  = $section?->supportsSections() ?? false;

        $makes = [];
        if ($supportsMakeModel) {
            $makes = Make::with('models')->get();
            if ($lang !== null) {
                $makes = $this->localizeCollection($makes, ['name']);
            }
        }

        $mainSections = [];
        if ($supportsSections && $section) {
            $mainSections = CategoryMainSection::with([
                'subSections' => fn($q) => $q->where('is_active', true)->orderBy('sort_order')
            ])
                ->where('category_id', $section->id())
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();

            if ($lang !== null) {
                $mainSections = $this->localizeCollection($mainSections, ['name']);
            }
        }

        return response()->json([
            'data'                => $fields,
            'governorates'        => $governorates,
            'makes'               => $supportsMakeModel ? $makes : [],
            'supports_make_model' => $supportsMakeModel,
            'supports_sections'   => $supportsSections,
            'main_sections'       => $mainSections,
        ]);
    }


    // POST /api/admin/category-fields
    public function store(StoreCategoryFieldRequest $request)
    {
        $data = $request->validated();

        $category = Category::firstOrCreate(
            ['slug' => $data['category_slug']],
            [
                'name' => $data['category_slug'],
                'is_active' => true,
            ]
        );

        if (empty($data['options'])) {
            $data['options'] = [];
        } else {
            if (!in_array('غير ذلك', $data['options'])) {
                $data['options'][] = 'غير ذلك';
            }
        }

        // مزامنة options_en: لو فيه options_en نضيف "Other" في الآخر
        if (!empty($data['options_en'])) {
            if (!in_array('Other', $data['options_en'])) {
                $data['options_en'][] = 'Other';
            }
        }

        $field = CategoryField::create($data);

        return response()->json([
            'message' => 'تم إنشاء الحقل بنجاح',
            'data' => $field,
        ], 201);
    }

    // PUT /api/admin/category-fields/{id}
    public function update(UpdateCategoryFieldRequest $request, $categorySlug)
    {
        $data = $request->validated();

        $field = CategoryField::where('category_slug', $categorySlug)
            ->where('field_name', $data['field_name'])
            ->first();

        if (!$field) {
            throw ValidationException::withMessages([
                'field_name' => ['الحقل المطلوب غير موجود في هذا القسم.'],
            ]);
        }

        if (isset($data['options']) && is_array($data['options'])) {
            $clean = [];
            foreach ($data['options'] as $opt) {
                $value = trim((string) $opt);
                if ($value !== '') $clean[] = $value;
            }
            $data['options'] = array_values(array_unique($clean));
            if (!empty($data['options']) && !in_array('غير ذلك', $data['options'])) {
                $data['options'][] = 'غير ذلك';
            }
        }

        if (isset($data['options_en']) && is_array($data['options_en'])) {
            $cleanEn = [];
            foreach ($data['options_en'] as $opt) {
                $value = trim((string) $opt);
                if ($value !== '') $cleanEn[] = $value;
            }
            $data['options_en'] = array_values(array_unique($cleanEn));
            if (!empty($data['options_en']) && !in_array('Other', $data['options_en'])) {
                $data['options_en'][] = 'Other';
            }
        }

        unset($data['field_name']);

        $field->update($data);

        return response()->json([
            'message' => 'تم تحديث الحقل بنجاح',
            'data' => $field->fresh(),
        ]);
    }


    public function destroy(CategoryField $categoryField)
    {
        $categoryField->update(['is_active' => false]);

        return response()->json([
            'message' => 'تم إلغاء تفعيل الحقل',
        ]);
    }
}
