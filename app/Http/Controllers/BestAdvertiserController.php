<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\BestAdvertiser;
use App\Models\Category;
use App\Models\Listing;
use App\Models\SystemSetting;
use App\Models\User;
use App\Traits\LocalizedResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\Section;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Cache;

class BestAdvertiserController extends Controller
{
    use LocalizedResponse;

    public function index(string $section)
    {
        Listing::autoExpire();
        $sec = Section::fromSlug($section);
        $categoryId = $sec->id();
        $isEn = $this->lang() === 'en';

        $featured = BestAdvertiser::active()
            ->whereRaw('JSON_CONTAINS(category_ids, ?)', [json_encode((int) $categoryId)])
            ->with('user')
            ->get();

        $userIds = $featured->pluck('user_id')->map(fn($v) => (int)$v)->all();

        if (count($userIds) === 0) {
            return response()->json(['advertisers' => []]);
        }

        $idsStr = implode(',', $userIds);

        $maxListings = Cache::remember('settings:featured_user_max_ads', now()->addHours(6), function () {
            return (int) (SystemSetting::where('key', 'featured_user_max_ads')->value('value') ?? 8);
        });

        $rows = DB::select("
            SELECT id, user_id
            FROM (
                SELECT l.*,
                    ROW_NUMBER() OVER (
                        PARTITION BY user_id
                        ORDER BY l.rank ASC, l.published_at DESC, l.created_at DESC
                    ) rn
                FROM listings l
                WHERE l.category_id = ?
                    AND l.status = 'Valid'
                    AND l.user_id IN ($idsStr)
            ) t
            WHERE rn <= ?
        ", [(int)$categoryId, $maxListings]);

        $listingIds = collect($rows)->pluck('id')->all();

        $listings = Listing::with([
            'attributes',
            'make',
            'model',
            'governorate',
            'city',
        ])->whereIn('id', $listingIds)->get()->keyBy('id');

        $byUser = [];
        foreach ($rows as $row) {
            $listing = $listings[$row->id] ?? null;
            if ($listing) {
                $attrs = [];
                if ($listing->relationLoaded('attributes')) {
                    foreach ($listing->attributes as $attr) {
                        $attrs[$attr->key] = $this->castEavValueRow($attr);
                    }
                }

                $gov  = ($listing->relationLoaded('governorate') && $listing->governorate) ? $listing->governorate : null;
                $city = ($listing->relationLoaded('city') && $listing->city) ? $listing->city : null;

                $govName  = $gov  ? ($isEn && $gov->name_en  ? $gov->name_en  : $gov->name)  : null;
                $cityName = $city ? ($isEn && $city->name_en ? $city->name_en : $city->name) : null;

                $lSec    = $listing->category_id ? Section::fromId($listing->category_id) : null;
                $catSlug = $lSec?->slug ?? null;

                // Resolve category name with language
                $catName = null;
                if ($lSec) {
                    $cat = Category::find($lSec->id());
                    $catName = ($isEn && !empty($cat?->name_en)) ? $cat->name_en : ($cat?->name ?? $lSec->name);
                }

                $byUser[$row->user_id][] = [
                    'main_image_url' => ($section === 'jobs' || $section === 'doctors' || $section === 'teachers')
                        ? asset('storage/' . Cache::remember("settings:{$section}_default_image", now()->addHours(6), fn() => SystemSetting::where('key', "{$section}_default_image")->value('value') ?? "defaults/{$section}_default.png"))
                        : ($listing->main_image ? asset('storage/' . $listing->main_image) : null),
                    'governorate'   => $govName,
                    'city'          => $cityName,
                    'price'         => $listing->price,
                    'attributes'    => $attrs,
                    'rank'          => $listing->rank,
                    'views'         => $listing->views,
                    'id'            => $listing->id,
                    'lat'           => $listing->lat,
                    'lng'           => $listing->lng,
                    'category'      => $catSlug,
                    'category_name' => $catName,
                ];
            }
        }

        $out = $featured->map(function (BestAdvertiser $ba) use ($byUser) {
            $u = $ba->user;
            return [
                'id'       => $ba->id,
                'user'     => ['name' => $u->name, 'id' => $u->id],
                'listings' => $byUser[$ba->user_id] ?? [],
            ];
        })->values();

        return response()->json(['advertisers' => $out]);
    }

    protected function castEavValueRow($attr)
    {
        return $attr->value_int
            ?? $attr->value_decimal
            ?? $attr->value_bool
            ?? $attr->value_string
            ?? $this->decodeJsonSafe($attr->value_json)
            ?? $attr->value_date
            ?? null;
    }

    protected function decodeJsonSafe($json)
    {
        if (is_null($json)) return null;
        if (is_array($json)) return $json;
        $x = json_decode($json, true);
        return json_last_error() === JSON_ERROR_NONE ? $x : $json;
    }

    // ---- Admin Endpoints ----

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id'        => ['required', 'integer', 'exists:users,id'],
            'category_ids'   => ['required', 'array', 'min:1'],
            'category_ids.*' => ['integer'],
            'max_listings'   => ['nullable', 'integer', 'min:1'],
            'is_active'      => ['boolean'],
        ]);

        $data['is_active'] = $data['is_active'] ?? true;

        $user = User::find($data['user_id']);
        if (!$user || $user->status !== 'active') {
            return response()->json(['message' => __('api.best_advertiser_inactive')], 422);
        }

        $existingCategoryIds = Category::whereIn('id', $data['category_ids'])->pluck('id')->all();
        $invalidIds = array_diff($data['category_ids'], $existingCategoryIds);
        if (!empty($invalidIds)) {
            return response()->json([
                'message'     => __('api.best_advertiser_bad_cats'),
                'invalid_ids' => $invalidIds,
            ], 422);
        }

        $limit = Cache::rememberForever('settings:featured_users_count', function () {
            return (int) (SystemSetting::where('key', 'featured_users_count')->value('value') ?? 8);
        });

        [$ba, $message] = DB::transaction(function () use ($data, $limit) {
            $ba = BestAdvertiser::where('user_id', $data['user_id'])->lockForUpdate()->first();

            $wasActive    = (bool) ($ba->is_active ?? false);
            $willBeActive = (bool) $data['is_active'];

            $activeCountQuery = BestAdvertiser::where('is_active', true);
            if ($ba && $wasActive) {
                $activeCountQuery->where('user_id', '!=', $ba->user_id);
            }
            $activeCount = (int) $activeCountQuery->lockForUpdate()->count();

            if ((!$wasActive && $willBeActive) || (!$ba && $willBeActive)) {
                if ($activeCount >= $limit) {
                    throw ValidationException::withMessages([
                        'limit' => __('api.best_advertiser_limit', ['limit' => $limit]),
                    ]);
                }
            }

            if ($ba) {
                $ba->update($data);
                $message = __('api.best_advertiser_updated');
            } else {
                $ba = BestAdvertiser::create($data);
                $message = __('api.best_advertiser_created');
            }

            return [$ba, $message];
        });

        $isEn = $this->lang() === 'en';
        $categories = Category::whereIn('id', $ba->category_ids)->get(['id', 'name', 'name_en', 'slug'])
            ->map(fn($c) => [
                'id'   => $c->id,
                'slug' => $c->slug,
                'name' => ($isEn && !empty($c->name_en)) ? $c->name_en : $c->name,
            ]);

        return response()->json([
            'message' => $message,
            'data'    => [
                'best_advertiser' => $ba,
                'categories'      => $categories,
            ],
        ], $ba->wasRecentlyCreated ? 201 : 200);
    }

    public function disable(BestAdvertiser $bestAdvertiser)
    {
        $bestAdvertiser->update(['is_active' => false]);
        return response()->json(['message' => __('api.best_advertiser_disabled')]);
    }
}
