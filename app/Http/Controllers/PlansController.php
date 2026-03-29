<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CategoryPlanPrice;
use App\Support\Section;
use App\Traits\LocalizedResponse;
use Illuminate\Http\Request;

class PlansController extends Controller
{
    use LocalizedResponse;

    public function show(string $section)
    {
        $sec = Section::fromSlug($section);

        $cat = Category::where('id', $sec->id())->first();
        $name = ($this->lang() === 'en' && !empty($cat?->name_en))
            ? $cat->name_en
            : $cat?->name ?? $sec->name;

        $prices = CategoryPlanPrice::where('category_id', $sec->id())->first();

        return response()->json([
            'category' => [
                'id'   => $sec->id(),
                'slug' => $sec->slug,
                'name' => $name,
            ],
            'price_featured' => $prices?->price_featured ?? 0,
            'price_standard' => $prices?->price_standard ?? 0,
        ]);
    }
}
