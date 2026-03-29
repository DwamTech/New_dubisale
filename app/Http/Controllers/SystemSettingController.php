<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\SystemSetting;
use App\Traits\LocalizedResponse;

class SystemSettingController extends Controller
{
    use LocalizedResponse;
    protected array $allowedKeys = [
        'support_number',
        'panner_image',
        'privacy_policy',
        'terms_conditions-main_',
        'sub_support_number',
        'emergency_number',
        'facebook',
        'twitter',
        'instagram',
        'email',
        'featured_users_count',
        'show_phone',
        'manual_approval',
        'enable_global_external_notif',
        'free_ads_count',
        'free_ads_max_price',
        'free_ad_days_validity',
        'featured_user_max_ads',
        'jobs_default_image',
        'doctors_default_image',
        'teachers_default_image',
        'home_image',
        'home_ad_image'
    ];

    // المفاتيح اللي بتدعم قيمة إنجليزية منفصلة
    protected array $bilingualKeys = [
        'panner_image',
        'privacy_policy',
        'terms_conditions-main_',
    ];

    // مفاتيح حسب النوع
    protected array $booleanKeys = ['show_phone','manual_approval','enable_global_external_notif'];
    protected array $integerKeys = ['featured_users_count','free_ads_count','free_ads_max_price','featured_user_max_ads', 'free_ad_days_validity'];

    protected function rules(): array
    {
        return [
            'support_number'             => ['nullable', 'string', 'max:255'],
            'sub_support_number'         => ['nullable', 'string', 'max:255'],
            'emergency_number'           => ['nullable', 'string', 'max:255'],
            'panner_image'               => ['nullable', 'string', 'max:1024'],
            'panner_image_en'            => ['nullable', 'string', 'max:1024'],
            'privacy_policy'             => ['nullable', 'string'],
            'privacy_policy_en'          => ['nullable', 'string'],
            'terms_conditions-main_'     => ['nullable', 'string'],
            'terms_conditions-main__en'  => ['nullable', 'string'],
            'facebook'                   => ['nullable', 'url', 'max:1024'],
            'twitter'                    => ['nullable', 'url', 'max:1024'],
            'instagram'                  => ['nullable', 'url', 'max:1024'],
            'email'                      => ['nullable', 'email', 'max:255'],
            'show_phone'                 => ['nullable', 'boolean'],
            'featured_users_count'       => ['nullable', 'integer', 'min:0', 'max:100'],
            'manual_approval'            => ['nullable', 'boolean'],
            'enable_global_external_notif' => ['nullable', 'boolean'],
            'free_ads_count'             => ['nullable', 'integer', 'min:0'],
            'free_ads_max_price'         => ['nullable', 'integer', 'min:0'],
            'free_ad_days_validity'      => ['nullable', 'integer', 'min:1'],
            'featured_user_max_ads'      => ['nullable', 'integer', 'min:1'],
        ];
    }

    // بيرجع اسم الـ en field للمفتاح
    protected function enKeyName(string $key): string
    {
        return $key . '_en';
    }

    protected function typeForKey(string $key): string
    {
        if (in_array($key, $this->booleanKeys, true)) return 'boolean';
        if (in_array($key, $this->integerKeys, true)) return 'integer';
        return in_array($key, ['privacy_policy', 'terms_conditions-main_'], true) ? 'text' : 'string';
    }

    protected function groupForKey(string $key): string
    {
        if ($key === 'panner_image') return 'appearance';
        if (in_array($key, ['free_ads_count','free_ads_max_price', 'free_ad_days_validity'], true)) return 'ads';
        if ($key === 'featured_user_max_ads') return 'home';
        return 'general';
    }

    // قبل التخزين: موحّد كل حاجة كـ string (مع تحويل منطقي للأرقام والبوليان)
    protected function castForStorage(string $key, $value): string
    {
        if (in_array($key, $this->booleanKeys, true)) {
            return $value ? '1' : '0';
        }
        if (in_array($key, $this->integerKeys, true)) {
            return (string) (int) $value;
        }
        return (string) $value;
    }

    protected function castForOutput(string $type, ?string $value)
    {
        return match ($type) {
            'boolean' => (bool) ($value === '1' || $value === 1 || $value === true),
            'integer' => $value !== null ? (int) $value : null,
            default   => $value,
        };
    }

    protected function forgetCache(string $key): void
    {
        Cache::forget("settings:{$key}");
        Cache::forget('settings:autoload'); // لو بتخزن تجميعة autoload
    }

    public function index()
    {
        $rows = SystemSetting::whereIn('key', $this->allowedKeys)->get(['key', 'value', 'value_en', 'type']);
        $map = [];

        foreach ($rows as $row) {
            $type = $row->type ?: $this->typeForKey($row->key);
            $map[$row->key] = $this->castForOutput($type, $row->value);

            if (in_array($row->key, $this->bilingualKeys, true)) {
                $map[$this->enKeyName($row->key)] = $this->castForOutput($type, $row->value_en);
            }
        }

        foreach ($this->allowedKeys as $k) {
            if (!array_key_exists($k, $map)) {
                $map[$k] = null;
            }
            if (in_array($k, $this->bilingualKeys, true) && !array_key_exists($this->enKeyName($k), $map)) {
                $map[$this->enKeyName($k)] = null;
            }
        }

        return response()->json(
            $this->localizeSettingsMap($map, $this->bilingualKeys)
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        $payload = collect($validated)
            ->only($this->allowedKeys)
            ->filter(fn ($v) => $v !== null)
            ->all();

        foreach ($payload as $key => $value) {
            $type = $this->typeForKey($key);
            $enKey = $this->enKeyName($key);
            $valueEn = in_array($key, $this->bilingualKeys, true)
                ? ($validated[$enKey] ?? null)
                : null;

            SystemSetting::updateOrCreate(
                ['key' => $key],
                [
                    'value'    => $this->castForStorage($key, $value),
                    'value_en' => $valueEn !== null ? (string) $valueEn : null,
                    'type'     => $type,
                    'group'    => $this->groupForKey($key),
                    'autoload' => true,
                ]
            );

            $this->forgetCache($key);

            $ttl = now()->addHours(6);
            Cache::put("settings:{$key}", $this->castForOutput($type, $this->castForStorage($key, $value)), $ttl);
            if ($valueEn !== null) {
                Cache::put("settings:{$enKey}", $valueEn, $ttl);
            }
        }

        // حفظ الـ en-only fields لو اتبعتوا بدون الـ ar
        foreach ($this->bilingualKeys as $key) {
            $enKey = $this->enKeyName($key);
            if (!isset($payload[$key]) && isset($validated[$enKey]) && $validated[$enKey] !== null) {
                SystemSetting::updateOrCreate(
                    ['key' => $key],
                    ['value_en' => (string) $validated[$enKey]]
                );
                $this->forgetCache($key);
                Cache::put("settings:{$enKey}", $validated[$enKey], now()->addHours(6));
            }
        }

        return response()->json([
            'status' => 'ok',
            'data'   => $payload,
        ]);
    }

    public function show(string $id)
    {
        $row = SystemSetting::where('key', $id)->first() ?: SystemSetting::find($id);

        if (!$row || !in_array($row->key, $this->allowedKeys, true)) {
            abort(404);
        }

        $type = $row->type ?: $this->typeForKey($row->key);

        return response()->json([
            $row->key => $this->castForOutput($type, $row->value)
        ]);
    }

    public function update(Request $request, string $id)
    {
        $validated = $request->validate($this->rules());

        $payload = collect($validated)
            ->only($this->allowedKeys)
            ->filter(fn ($v) => $v !== null)
            ->all();

        foreach ($payload as $key => $value) {
            $type = $this->typeForKey($key);
            $enKey = $this->enKeyName($key);
            $valueEn = in_array($key, $this->bilingualKeys, true)
                ? ($validated[$enKey] ?? null)
                : null;

            SystemSetting::updateOrCreate(
                ['key' => $key],
                [
                    'value'    => $this->castForStorage($key, $value),
                    'value_en' => $valueEn !== null ? (string) $valueEn : null,
                    'type'     => $type,
                    'group'    => $this->groupForKey($key),
                    'autoload' => true,
                ]
            );

            $this->forgetCache($key);

            $ttl = now()->addHours(6);
            Cache::put("settings:{$key}", $this->castForOutput($type, $this->castForStorage($key, $value)), $ttl);
            if ($valueEn !== null) {
                Cache::put("settings:{$enKey}", $valueEn, $ttl);
            }
        }

        // تحديث الـ en-only fields لو اتبعتوا بدون الـ ar
        foreach ($this->bilingualKeys as $key) {
            $enKey = $this->enKeyName($key);
            if (!isset($payload[$key]) && isset($validated[$enKey]) && $validated[$enKey] !== null) {
                SystemSetting::updateOrCreate(
                    ['key' => $key],
                    ['value_en' => (string) $validated[$enKey]]
                );
                $this->forgetCache($key);
                Cache::put("settings:{$enKey}", $validated[$enKey], now()->addHours(6));
            }
        }

        return response()->json(['status' => 'ok']);
    }

    public function destroy(string $id)
    {
        $row = SystemSetting::where('key', $id)->first();

        if ($row && in_array($row->key, $this->allowedKeys, true)) {
            $this->forgetCache($row->key);
            $row->delete();
            return response()->json(null, 204);
        }

        abort(404);
    }

    public function uploadDefaultImage(Request $request)
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:50120'],
            'key' => ['required', 'string', 'in:jobs_default_image,doctors_default_image,teachers_default_image'],
        ]);

        $file = $request->file('image');
        $key = $request->input('key');

        $directory = in_array($key, ['home_image', 'home_ad_image']) ? 'uploads/banner' : 'defaults';
        $path = $file->storeAs($directory, $key . '.' . $file->getClientOriginalExtension(), 'public');

        SystemSetting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $path,
                'type' => 'string',
                'group' => 'appearance',
                'autoload' => true,
            ]
        );

        $this->forgetCache($key);

        return response()->json([
            'status' => 'ok',
            'url' => asset('storage/' . $path),
        ]);
    }
}
