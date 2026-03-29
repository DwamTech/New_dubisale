<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ListingResource;
use App\Models\Favorite;
use App\Models\Listing;
use App\Support\Section;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $rows = Favorite::query()
            ->where('user_id', $user->id)
            ->with(['ad.governorate', 'ad.city'])
            ->get();

        $payload = $rows->map(function ($f) {
            $ad = $f->ad;
            if (!$ad) return null;

            $slug = $ad->category_id ? Section::fromId($ad->category_id)->slug : null;

            return [
                'plan_type'  => $ad->plan_type,
                'price'      => $ad->price,
                'description'=> $ad->description,
                'gov'        => optional($ad->governorate)->name,
                'cite'       => optional($ad->city)->name,
                'puplished'  => $ad->published_at,
                'main_image' => $ad->main_image ? asset('storage/' . $ad->main_image) : null,
                'view'       => $ad->views,
                'id'         => $ad->id,
                'rank'       => $ad->rank,
                'categry'    => $slug,
                'categry_name'=> $slug ? Section::fromId($ad->category_id)->name : null,
            ];
        })->filter();

        return response()->json([
            'count' => $payload->count(),
            'data'  => $payload->values(),
        ]);
    }

    public function toggle(Request $request)
    {
        $data = $request->validate([
            'ad_id' => ['required', 'integer', 'exists:listings,id'],
        ]);

        $user = $request->user();

        $existing = Favorite::query()
            ->where('user_id', $user->id)
            ->where('ad_id', $data['ad_id'])
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['message' => __('api.favorite_removed'), 'favorited' => false]);
        }

        $ad = Listing::find($data['ad_id']);
        $slug = Section::fromId($ad->category_id)->slug;

        Favorite::create(['user_id' => $user->id, 'ad_id' => $ad->id, 'category_slug' => $slug]);

        return response()->json(['message' => __('api.favorite_added'), 'favorited' => true], 201);
    }
}

