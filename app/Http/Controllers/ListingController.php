<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenericListingRequest;
use App\Http\Resources\ListingResource;
use App\Models\Listing;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\ListingService;
use App\Support\Section;
use App\Traits\HasRank;
use App\Traits\PackageHelper;
use App\Traits\LocalizedResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use App\Models\UserPlanSubscription;
use App\Models\CategoryPlanPrice;
use App\Models\CategoryBanner;
use App\Services\AdminNotificationService;
use App\Services\NotificationService;


class ListingController extends Controller
{
    use HasRank, PackageHelper, LocalizedResponse;



    public function index(string $section, Request $request)
    {
        Listing::autoExpire();
        $sec = Section::fromSlug($section);
        $typesByKey = Listing::typesByKeyForSection($sec);

        $filterableKeys = collect($sec->fields)
            ->where('filterable', true)
            ->pluck('field_name')
            ->all();

        $with = ['attributes', 'governorate', 'city'];
        if ($sec->supportsMakeModel()) {
            $with[] = 'make';
            $with[] = 'model';
        }
        if ($sec->supportsSections()) {
            $with[] = 'mainSection';
            $with[] = 'subSection';
        }

        $q = Listing::query()
            ->forSection($sec)
            ->with($with)
            ->active()
            ->orderBy('rank', 'asc')
            ->keyword($request->query('q'))
            ->filterGovernorate($request->query('governorate_id'), $request->query('governorate'))
            ->filterCity($request->query('city_id'), $request->query('city'))
            ->priceRange($request->query('price_min'), $request->query('price_max'));

        if ($plan = $request->query('plan_type')) {

            $q->where('plan_type', $plan);
        }
        if ($sec->supportsMakeModel()) {
            $makeId = $request->query('make_id');
            $makeName = $request->query('make');
            $modelId = $request->query('model_id');
            $modelName = $request->query('model');

            if ($makeId) {
                $q->where('make_id', (int) $makeId);
            } elseif ($makeName) {
                $q->whereHas('make', function ($qq) use ($makeName) {
                    $qq->where('name', 'like', '%' . trim($makeName) . '%');
                });
            }

            if ($modelId) {
                $q->where('model_id', (int) $modelId);
            } elseif ($modelName) {
                $q->whereHas('model', function ($qq) use ($modelName) {
                    $qq->where('name', 'like', '%' . trim($modelName) . '%');
                });
            }
        }
        if ($sec->supportsSections()) {
            $mainSectionId = $request->query('main_section_id');
            $subSectionId = $request->query('sub_section_id');
            $mainSectionName = $request->query('main_section');
            $subSectionName = $request->query('sub_section');

            if ($mainSectionId) {
                $q->where('main_section_id', $mainSectionId);
            } elseif ($mainSectionName) {
                $q->whereHas('mainSection', function ($qq) use ($mainSectionName) {
                    $qq->where('name', $mainSectionName);
                });
            }

            if ($subSectionId) {
                $q->where('sub_section_id', $subSectionId);
            } elseif ($subSectionName) {
                $q->whereHas('subSection', function ($qq) use ($subSectionName) {
                    $qq->where('name', $subSectionName);
                });
            }
        }

        $attrEq = (array) $request->query('attr', []);
        $attrEq = array_intersect_key($attrEq, array_flip($filterableKeys));
        $q->attrEq($attrEq, $typesByKey);

        $attrIn = (array) $request->query('attr_in', []);
        $attrIn = array_intersect_key($attrIn, array_flip($filterableKeys));
        $q->attrIn($attrIn, $typesByKey);


        $attrMin = (array) $request->query('attr_min', []);
        $attrMax = (array) $request->query('attr_max', []);
        $attrMin = array_intersect_key($attrMin, array_flip($filterableKeys));
        $attrMax = array_intersect_key($attrMax, array_flip($filterableKeys));
        $q->attrRange($attrMin, $attrMax, $typesByKey);

        // attr_like = بحث نصي جزئي
        $attrLike = (array) $request->query('attr_like', []);
        $attrLike = array_intersect_key($attrLike, array_flip($filterableKeys));
        $q->attrLike($attrLike);


        $rows = $q->get();

        // زيّدي views لكل النتائج (بالشُحنات)
        // if ($rows->isNotEmpty()) {
        //     $ids = $rows->pluck('id');
        //     $ids->chunk(1000)->each(function ($chunk) {
        //         DB::table('listings')
        //             ->whereIn('id', $chunk)
        //             ->update(['views' => DB::raw('views + 1')]);
        //     });
        // }

        $supportsMakeModel = $sec->supportsMakeModel();
        $supportsSections = $sec->supportsSections();

        $categorySlug = $sec->slug;
        $categoryName = $sec->name;


        $items = $rows->map(function ($item) use ($supportsMakeModel, $supportsSections, $categorySlug, $categoryName) {
            $attrs = [];
            if ($item->relationLoaded('attributes')) {
                foreach ($item->attributes as $row) {
                    $attrs[$row->key] = $this->castEavValueRow($row);
                }
            }

            $data = [
                'attributes' => $attrs,
                'governorate' => ($item->relationLoaded('governorate') && $item->governorate) ? $item->governorate->name : null,
                'city' => ($item->relationLoaded('city') && $item->city) ? $item->city->name : null,
                'price' => $item->price,
                'contact_phone' => $item->contact_phone,
                'whatsapp_phone' => $item->whatsapp_phone,
                'main_image_url' => ($categorySlug === 'jobs' || $categorySlug === 'doctors' || $categorySlug === 'teachers')
                    ? (asset('storage/' . \Illuminate\Support\Facades\Cache::remember("settings:{$categorySlug}_default_image", now()->addHours(6), fn() => \App\Models\SystemSetting::where('key', "{$categorySlug}_default_image")->value('value') ?? "defaults/{$categorySlug}_default.png")))
                    : ($item->main_image ? asset('storage/' . $item->main_image) : null),
                'created_at' => $item->created_at,
                'plan_type' => $item->plan_type,
                'views' => $item->views,
                'rank' => $item->rank,
                'id' => $item->id,
                'lat' => $item->lat,
                'lng' => $item->lng,

                // الكاتيجري
                'category' => $categorySlug,   // slug
                'category_name' => $categoryName,   // الاسم
            ];

            if ($supportsMakeModel) {
                $data['make'] = ($item->relationLoaded('make') && $item->make) ? $item->make->name : null;
                $data['model'] = ($item->relationLoaded('model') && $item->model) ? $item->model->name : null;
            }
            if ($supportsSections) {
                $data['main_section_id'] = $item->main_section_id;
                $data['main_section'] = ($item->relationLoaded('mainSection') && $item->mainSection)
                    ? $item->mainSection->name
                    : null;

                $data['sub_section_id'] = $item->sub_section_id;
                $data['sub_section'] = ($item->relationLoaded('subSection') && $item->subSection)
                    ? $item->subSection->name
                    : null;
            }

            return $data;
        })->values();

        return response()->json($items);
    }

    /** نفس منطق قراءة قيمة الـ EAV */
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
        if (is_null($json))
            return null;
        if (is_array($json))
            return $json;

        $x = json_decode($json, true);
        return json_last_error() === JSON_ERROR_NONE ? $x : $json;
    }



    public function store(string $section, GenericListingRequest $request, ListingService $service, AdminNotificationService $adminNotification)
    {
        $user = $request->user();
        $sec = Section::fromSlug($section);
        $data = $request->validated();
        $isAdmin = $this->userIsAdmin($user);

        $rank = $this->getNextRank(Listing::class, $sec->id());
        $data['rank'] = $rank;

        if ($request->hasFile('main_image')) {
            $data['main_image'] = $this->storeUploaded($request->file('main_image'), $section, 'main');
        }

        if ($request->hasFile('images')) {
            $stored = [];
            foreach ($request->file('images') as $file) {
                $stored[] = $this->storeUploaded($file, $section, 'gallery');
            }
            $data['images'] = $stored;
        }

        $manualApprove = Cache::remember('settings:manual_approval', now()->addHours(6), function () {
            $val = SystemSetting::where('key', 'manual_approval')->value('value');
            return (int) $val === 1;
        });

        $paymentRequired = false;
        $packageData = null;
        $activeSub = null;
        $paymentType = null;
        $paymentReference = null;
        $paymentMethod = null;
        $priceOut = 0.0;

        if (!empty($data['plan_type']) && $data['plan_type'] !== 'free') {
            $planNorm = $this->normalizePlan($data['plan_type']);

            // 1. Try to consume from Package (UserPackages) first - This is what Admin creates
            $packageResult = $this->consumeForPlan($user->id, $planNorm, $sec->id());
            $packageData   = $packageResult->getData(true);

            if (!empty($packageData['success']) && $packageData['success'] === true) {
                // Success! Package consumed
                $data['expire_at'] = Carbon::parse($packageData['expire_date']);
                $paymentType = 'package';
                $prices = CategoryPlanPrice::where('category_id', $sec->id())->first();
                $priceOut = $planNorm === 'featured'
                    ? (float) ($prices?->featured_ad_price ?? 0)
                    : (float) ($prices?->standard_ad_price ?? 0);
                $data['publish_via'] = env('LISTING_PUBLISH_VIA_PACKAGE', 'package');
            } else {
                // 2. If no Package, check for legacy Subscription (UserPlanSubscription)
                $activeSub = UserPlanSubscription::query()
                    ->where('user_id', $user->id)
                    ->where('plan_type', $planNorm)
                    ->where('payment_status', 'paid')
                    ->where(function ($q) {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
                    })
                    ->first();

                if ($activeSub) {
                    $data['expire_at'] = $activeSub->expires_at;
                    $paymentType = 'subscription';
                    $data['publish_via'] = env('LISTING_PUBLISH_VIA_SUBSCRIPTION', 'subscription');
                    // Note: Consumption logic for subscription can be added here if needed
                } elseif ($isAdmin) {
                    // 3. Admin Bypass
                    $paymentRequired = false;
                    $prices = CategoryPlanPrice::where('category_id', $sec->id())->first();
                    $days = $planNorm === 'featured'
                        ? ((int)($prices?->featured_days ?? 30))
                        : ((int)($prices?->standard_days ?? 30));

                    $data['expire_at'] = now()->addDays($days > 0 ? $days : 30);
                    $paymentType = 'admin_bypass';
                    $priceOut = 0.0;
                    $data['publish_via'] = 'admin';
                } else {
                    // 4. No Package, No Sub -> Payment Required
                    $paymentRequired = true;
                    $message = $packageData['message'] ?? __('api.listing_no_package');
                }
            }
        } else {
            $freeVia = env('LISTING_PUBLISH_VIA_FREE', 'free');
            if ($sec->slug != 'missing') {
                $freeCount = Cache::remember('settings:free_ads_count', now()->addHours(6), function () {
                    return (int) (SystemSetting::where('key', 'free_ads_count')->value('value') ?? 0);
                });

                // Get max price from Category settings first
                $catPlan = CategoryPlanPrice::where('category_id', $sec->id())->first();
                $freeMaxPrice = (int) ($catPlan?->free_ad_max_price ?? 0);

                // Fallback to global setting if 0
                if ($freeMaxPrice === 0) {
                    $freeMaxPrice = Cache::remember('settings:free_ads_max_price', now()->addHours(6), function () {
                        return (int) (SystemSetting::where('key', 'free_ads_max_price')->value('value') ?? 0);
                    });
                }

                $userFreeCount = Listing::query()
                    ->where('user_id', $user->id)
                    ->where(function ($q) use ($freeVia) {
                        $q->where('publish_via', $freeVia)->orWhere('plan_type', 'free');
                    })
                    ->whereIn('status', ['Valid', 'Pending'])
                    ->count();

                $priceVal = (float) ($data['price'] ?? 0);
                $overCount = ($freeCount > 0 && $userFreeCount >= $freeCount);
                $overPrice = ($freeMaxPrice > 0 && $priceVal > $freeMaxPrice);

                if (($overCount || $overPrice) && !$isAdmin) {
                    // $paymentRequired = true;

                    $message = null;

                    if ($overCount && $overPrice) {
                        return Response()->json([
                            'success' => false,
                            'message' => __('api.listing_free_both'),
                        ], 402);
                    } elseif ($overCount) {
                        return Response()->json([
                            'success' => false,
                            'message' => __('api.listing_free_count'),
                        ], 402);
                    } elseif ($overPrice) {
                        return Response()->json([
                            'success' => false,
                            'message' => __('api.listing_free_price'),
                        ], 402);
                    }
                }
            }

            // Standard free flow (or Admin Bypass passes through here)
            $data['publish_via'] = $freeVia;
            $paymentType = 'free';
            $priceOut = 0.0;
        }



        if ($paymentRequired) {
            $data['status'] = 'Pending';
            $data['admin_approved'] = false;
        } else {
            if ($manualApprove && !$isAdmin) { // Admins bypass manual approval too? Assume yes
                $data['status'] = 'Pending';
                $data['admin_approved'] = false;
            } else {
                $data['status'] = 'Valid';
                $data['admin_approved'] = true;
                $data['published_at'] = now();
                if (($data['plan_type'] ?? 'free') === 'free' && empty($data['expire_at'])) {
                    $freeDays = Cache::remember('settings:free_ad_days_validity', now()->addHours(6), function () {
                        return (int)(SystemSetting::where('key', 'free_ad_days_validity')->value('value') ?? 365);
                    });
                    $data['expire_at'] = now()->addDays($freeDays);
                }
                if (($data['plan_type'] ?? 'free') === 'free') {
                    $paymentType = 'free';
                    $priceOut = 0.0;
                    $data['publish_via'] = env('LISTING_PUBLISH_VIA_FREE', 'free');
                }
            }
        }

        $listing = $service->create($sec, $data, $user->id);

        // Only change role if not admin (don't downgrade admin to advertiser)
        if ($user->role !== 'admin') {
            $user->role = 'advertiser';
            $user->save();
        }

        if ($paymentRequired) {
            return response()->json([
                'success' => false,
                'message' =>  $message,
                'payment_required' => true,
                'listing_id' => $listing->id,
                // 'count'=>$userFreeCount,
            ], 402);
        }

        // Notify Admin if listing is Pending (Manual Approval)
        if ($listing->status === 'Pending') {
            $adminNotification->dispatch(
                __('api.listing_pending_admin'),
                __('api.listing_pending_admin_body', ['id' => $listing->id, 'section' => $sec->name]),
                'listing_pending',
                ['listing_id' => $listing->id]
            );
        }

        return (new ListingResource(
            $listing->load([
                'attributes',
                'governorate',
                'city',
                'make',
                'model',
                'mainSection',
                'subSection',
            ])
        ))->additional([
            'payment' => [
                'type' => $paymentType,
                'plan_type' => $data['plan_type'] ?? 'free',
                'price' => $priceOut,
                'payment_reference' => $paymentReference,
                'payment_method' => $paymentMethod,
                'currency' => $listing->currency,
                'user_id' => $user->id,
                'subscribed_at' => $activeSub?->subscribed_at,
            ],
        ]);
    }

    public function renew(Request $request, Listing $listing)
    {
        $user = $request->user();

        if ($listing->user_id !== $user->id && $user->role !== 'admin') {
            abort(403, 'Unauthorized action.');
        }

        $sec = Section::fromId($listing->category_id);
        if (!$sec) {
            abort(404, 'Category not found or inactive');
        }

        $planType = $listing->plan_type ?? 'free';

        if ($planType !== 'free') {
            $planNorm = $this->normalizePlan($planType);
            // Check subscription
            $activeSub = UserPlanSubscription::query()
                ->where('user_id', $user->id)
                ->where('category_id', $sec->id())
                ->where('plan_type', $planNorm)
                ->where('payment_status', 'paid')
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
                })
                ->first();

            // if ($activeSub && $activeSub->consumeAd(1)) {
            //     $listing->update([
            //         'expire_at' => $activeSub->expires_at,
            //         'status' => 'Valid',
            //         'publish_via' => 'subscription',
            //         'isPayment' => true,
            //     ]);

            //     return response()->json([
            //         'success' => true,
            //         'message' => 'تم تجديد الإعلان بنجاح عبر الاشتراك',
            //         'data' => new ListingResource($listing)
            //     ]);
            // }
            if ($activeSub) {
                // Check package
                $packageResult = $this->consumeForPlan($user->id, $planNorm, $sec->id());
                $packageData   = $packageResult->getData(true);

                if (!empty($packageData['success']) && $packageData['success'] === true) {
                    $listing->update([
                        'expire_at' => Carbon::parse($packageData['expire_date']),
                        'status' => 'Valid',
                        'publish_via' => 'package',
                        'isPayment' => true,
                    ]);
                    return response()->json([
                        'success' => true,
                        'message' => __('api.listing_renew_package'),
                        'data' => new ListingResource($listing)
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'message' => __('api.listing_renew_no_balance'),
                    'payment_required' => true
                ], 402);
            }
        } else {
            // Free plan logic
            $freeVia = env('LISTING_PUBLISH_VIA_FREE', 'free');
            $freeCount = Cache::remember('settings:free_ads_count', now()->addHours(6), function () {
                return (int) (SystemSetting::where('key', 'free_ads_count')->value('value') ?? 0);
            });

            $userFreeCount = Listing::query()
                ->where('user_id', $user->id)
                ->where(function ($q) use ($freeVia) {
                    $q->where('publish_via', $freeVia)->orWhere('plan_type', 'free');
                })
                ->whereIn('status', ['Valid', 'Pending'])
                ->where('id', '!=', $listing->id)
                ->count();

            $overCount = ($freeCount > 0 && ($userFreeCount + 1) > $freeCount);

            // Get max price from Category settings first
            $catPlan = CategoryPlanPrice::where('category_id', $sec->id())->first();
            $freeMaxPrice = (int) ($catPlan?->free_ad_max_price ?? 0);

            // Fallback to global setting if 0
            if ($freeMaxPrice === 0) {
                $freeMaxPrice = Cache::remember('settings:free_ads_max_price', now()->addHours(6), function () {
                    return (int) (SystemSetting::where('key', 'free_ads_max_price')->value('value') ?? 0);
                });
            }
            $priceVal = (float) $listing->price;
            $overPrice = ($freeMaxPrice > 0 && $priceVal > $freeMaxPrice);

            if ($overCount || $overPrice) {
                $msg = __('api.listing_renew_free_limit');
                if ($overCount) $msg .= ' ' . __('api.listing_renew_over_count');
                if ($overPrice) $msg .= ' ' . __('api.listing_renew_over_price');

                return response()->json([
                    'success' => false,
                    'message' => $msg,
                ], 402);
            }

            $freeDays = Cache::remember('settings:free_ad_days_validity', now()->addHours(6), function () {
                return (int)(SystemSetting::where('key', 'free_ad_days_validity')->value('value') ?? 365);
            });

            $listing->update([
                'expire_at' => now()->addDays($freeDays),
                'status' => 'Valid',
                'publish_via' => 'free',
            ]);

            return response()->json([
                'success' => true,
                'message' => __('api.listing_renew_free'),
                'data' => new ListingResource($listing)
            ]);
        }
    }

    public function show(string $section, Listing $listing, NotificationService $notifications)
    {
        $sec = Section::fromSlug($section);
        abort_if($listing->category_id !== $sec->id(), 404);

        $listing->increment('views');
        $viewer = request()->user();
        if ($viewer->role != 'admin') {
            if ($viewer && $viewer->id !== $listing->user_id) {
                $notifications->dispatch(
                    (int) $listing->user_id,
                    __('api.listing_view_notify'),
                    __('api.listing_view_notify_body', [
                        'viewer'  => $viewer->id,
                        'listing' => $listing->id,
                        'section' => $sec->name,
                    ]),
                    'view',
                    ['viewer_id' => (int) $viewer->id, 'listing_id' => (int) $listing->id, 'category_slug' => $sec->slug]
                );
            }
        }

        $banner = null;
        $slug = $sec->slug;

        // 1. Try to find banner from DB for this category
        $catBanner = CategoryBanner::where('slug', $slug)->where('is_active', true)->first();
        if ($catBanner) {
            $banner = $catBanner->banner_path;
        }

        // 2. Fallback to unified if not found
        if (!$banner) {
            $unifiedBanner = CategoryBanner::where('slug', 'unified')->where('is_active', true)->first();
            if ($unifiedBanner) {
                $banner = $unifiedBanner->banner_path;
            }
        }

        // 3. Last resort fallback (FileSystem check for legacy support or if DB is empty)
        if (!$banner) {
            $unifiedPath = public_path("storage/uploads/banner/unified");
            if (File::isDirectory($unifiedPath)) {
                $files = File::files($unifiedPath);
                if (count($files) > 0) {
                    $banner = "storage/uploads/banner/unified/" . $files[0]->getFilename();
                }
            }
        }

        $owner = User::select('id', 'name', 'created_at')->find($listing->user_id);
        $adsCount = Listing::where('user_id', $listing->user_id)->count();

        $userPayload = [
            'id' => $owner?->id,
            'name' => $owner?->name ?? "advertiser",
            'joined_at' => $owner?->created_at?->toIso8601String(),
            'joined_at_human' => $owner?->created_at?->diffForHumans(),
            'listings_count' => $adsCount,
            'banner' => $banner
        ];


        return (new ListingResource(
            $listing->load([
                'attributes',
                'governorate',
                'city',
                'make',
                'model',
                'mainSection',
                'subSection',
            ])
        ))->additional([
            'user' => $userPayload,
        ]);
    }

    protected function userIsAdmin($user): bool
    {
        return $user->role == 'admin';
    }

    /**
     * Global Search across all listings.
     * GET /api/listings/search?q=keyword
     * 
     * Searches in: description, address, governorate, city, and attributes (EAV)
     * 
     * Returns:
     * - listings: array of matching listings with their category info
     * - categories: count of listings per category
     * - total: total count of matching listings
     */
    public function globalSearch(Request $request)
    {
        $keyword = trim($request->query('q', ''));

        if (strlen($keyword) < 2) {
            return response()->json([
                'message' => __('api.listing_search_min'),
            ], 422);
        }

        $perPage = (int) $request->query('per_page', 20);

        // Build search condition that covers multiple fields
        $searchCondition = function ($query) use ($keyword) {
            $query->where('description', 'like', "%{$keyword}%")
                ->orWhere('address', 'like', "%{$keyword}%")
                // Search in governorate name
                ->orWhereHas('governorate', function ($q) use ($keyword) {
                    $q->where('name', 'like', "%{$keyword}%");
                })
                // Search in city name
                ->orWhereHas('city', function ($q) use ($keyword) {
                    $q->where('name', 'like', "%{$keyword}%");
                })
                // Search in make name
                ->orWhereHas('make', function ($q) use ($keyword) {
                    $q->where('name', 'like', "%{$keyword}%");
                })
                // Search in model name
                ->orWhereHas('model', function ($q) use ($keyword) {
                    $q->where('name', 'like', "%{$keyword}%");
                })
                // Search in main section name
                ->orWhereHas('mainSection', function ($q) use ($keyword) {
                    $q->where('name', 'like', "%{$keyword}%");
                })
                // Search in sub section name
                ->orWhereHas('subSection', function ($q) use ($keyword) {
                    $q->where('name', 'like', "%{$keyword}%");
                })
                // Search in attributes (EAV) - value_string column
                ->orWhereHas('attributes', function ($q) use ($keyword) {
                    $q->where('value_string', 'like', "%{$keyword}%");
                });
        };

        // Base filter: published (Valid) and not expired
        $baseFilter = function ($query) {
            $query->where('status', 'Valid')
                ->where(function ($q) {
                    $q->whereNull('expire_at')
                        ->orWhere('expire_at', '>=', now());
                });
        };

        // Main query with all search conditions
        $query = Listing::query()
            ->where($baseFilter)
            ->where($searchCondition)
            ->with(['governorate', 'city', 'attributes', 'make', 'model', 'mainSection', 'subSection'])
            ->orderByDesc('created_at');

        // Get paginated results
        $results = $query->paginate($perPage);

        // Get category counts for the keyword (same search condition)
        $categoryCounts = Listing::query()
            ->where($baseFilter)
            ->where($searchCondition)
            ->selectRaw('category_id, COUNT(*) as count')
            ->groupBy('category_id')
            ->pluck('count', 'category_id');

        // Get category names
        $categoryIds = $categoryCounts->keys()->toArray();
        $categories = \App\Models\Category::whereIn('id', $categoryIds)
            ->get(['id', 'name', 'slug'])
            ->keyBy('id');

        // Build category breakdown
        $categoryBreakdown = [];
        foreach ($categoryCounts as $catId => $count) {
            $cat = $categories->get($catId);
            if ($cat) {
                $categoryBreakdown[] = [
                    'category_id' => $catId,
                    'category_name' => $cat->name,
                    'category_slug' => $cat->slug,
                    'count' => $count,
                ];
            }
        }

        // Sort by count descending
        usort($categoryBreakdown, fn($a, $b) => $b['count'] <=> $a['count']);

        // Format listings
        $listings = collect($results->items())->map(function ($item) use ($categories) {
            $cat = $categories->get($item->category_id);

            // Get attributes as key-value
            $attrs = [];
            if ($item->relationLoaded('attributes')) {
                foreach ($item->attributes as $attr) {
                    $attrs[$attr->key] = $attr->value_string
                        ?? $attr->value_int
                        ?? $attr->value_decimal
                        ?? $attr->value_bool
                        ?? $attr->value_date
                        ?? null;
                }
            }

            return [
                'id' => $item->id,
                'category_id' => $item->category_id,
                'category_name' => $cat?->name,
                'category_slug' => $cat?->slug,
                'description' => $item->description,
                'price' => $item->price,
                'address' => $item->address,
                'main_image_url' => $item->main_image ? asset('storage/' . $item->main_image) : null,
                'governorate' => $item->governorate?->name,
                'city' => $item->city?->name,
                'make' => $item->make?->name,
                'model' => $item->model?->name,
                'main_section' => $item->mainSection?->name,
                'sub_section' => $item->subSection?->name,
                'attributes' => $attrs,
                'created_at' => $item->created_at,
            ];
        });

        return response()->json([
            'keyword' => $keyword,
            'total' => $results->total(),
            'categories' => $categoryBreakdown,
            'meta' => [
                'page' => $results->currentPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
                'last_page' => $results->lastPage(),
            ],
            'data' => $listings,
        ]);
    }

    public function update(string $section, GenericListingRequest $request, Listing $listing, ListingService $service)
    {
        $sec = Section::fromSlug($section);
        abort_if($listing->category_id !== $sec->id(), 404);

        $user = $request->user();
        $isOwner = $listing->user_id === ($user->id);
        $isAdmin = $this->userIsAdmin($user);

        if (!$isOwner && !$isAdmin) {
            return response()->json([
                'message' => __('api.listing_unauthorized_edit2'),
            ], 403);
        }

        $data = $request->validated();
        if ($isAdmin) {
            $adminComment = $request->input('admin_comment');
            if ($adminComment !== null) {
                $data['admin_comment'] = $adminComment;
            }
        }

        if ($request->hasFile('main_image')) {
            $data['main_image'] = $this->storeUploaded($request->file('main_image'), $section, 'main');
        }
        if ($request->hasFile('images')) {
            $stored = [];
            foreach ($request->file('images') as $file) {
                $stored[] = $this->storeUploaded($file, $section, 'gallery');
            }
            $data['images'] = $stored;
        }

        $listing = $service->update($sec, $listing, $data);

        return new ListingResource($listing->load([
            'attributes',
            'governorate',
            'city',
            'make',
            'model',
            'mainSection',
            'subSection',
        ]));
    }

    public function destroy(string $section, Listing $listing)
    {
        $sec = Section::fromSlug($section);
        abort_if($listing->category_id !== $sec->id(), 404);

        $user = request()->user();
        $isOwner = $listing->user_id === ($user->id ?? null);
        $isAdmin = $this->userIsAdmin($user);

        if (!$isOwner && !$isAdmin) {
            return response()->json([
                'message' => __('api.listing_unauthorized_del2'),
            ], 403);
        }

        $listing->delete();

        return response()->json(['ok' => true]);
    }
    protected function storeUploaded($file, string $section, string $bucket = 'main'): string
    {
        $datePath = now()->format('Y/m');
        $dir = "uploads/{$section}/{$datePath}/" . ($bucket === 'main' ? 'main' : 'gallery');
        $name = Str::uuid()->toString() . '.' . $file->getClientOriginalExtension();
        try {
            Storage::disk('public')->makeDirectory($dir);
            $path = $file->storeAs($dir, $name, 'public');
        } catch (\Throwable $e) {
            Log::error('upload_store_exception', [
                'section' => $section,
                'bucket' => $bucket,
                'dir' => $dir,
                'name' => $name,
                'error' => $e->getMessage(),
            ]);
            $field = $bucket === 'main' ? 'main_image' : 'images';
            throw \Illuminate\Validation\ValidationException::withMessages([$field => [__('api.listing_upload_failed')]]);
        }
        if (!$path) {
            Log::error('upload_store_failed', [
                'section' => $section,
                'bucket' => $bucket,
                'dir' => $dir,
                'name' => $name,
                'disk_root' => config('filesystems.disks.public.root'),
            ]);
            $field = $bucket === 'main' ? 'main_image' : 'images';
            throw \Illuminate\Validation\ValidationException::withMessages([$field => [__('api.listing_upload_failed')]]);
        }
        if (!Storage::disk('public')->exists($path)) {
            Log::error('upload_file_missing_after_store', [
                'path' => $path,
                'dir' => $dir,
            ]);
            $field = $bucket === 'main' ? 'main_image' : 'images';
            throw \Illuminate\Validation\ValidationException::withMessages([$field => [__('api.listing_upload_save_failed')]]);
        }
        return $path;
    }
}
