<?php

namespace App\Traits;

use App\Models\UserPackages;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

trait PackageHelper
{
    public function consumeForPlan(int $userId, string $planType, int $categoryId ,int $count = 1)
    {
        $planType = $this->normalizePlan($planType);

        $pkg = UserPackages::where('user_id', $userId)->first();

        if (!$pkg) {
            return response()->json([
                'success' => false,
                'message' => 'لا توجد باقة لهذا المستخدم.',
            ], 404);
        }

        if ($categoryId !== null && !empty($pkg->categories) && is_array($pkg->categories) && count($pkg->categories) > 0) {
            $allowedCats = array_map('intval', $pkg->categories);
            if (!in_array((int)$categoryId, $allowedCats)) {
                return response()->json([
                    'success' => false,
                    'message' => 'هذه الباقة غير صالحة لهذا القسم.',
                ], 422);
            }
        }

        [$totalField, $usedField, $daysField, $startField, $expireField, $title] = $this->mapFields($planType);

        if (empty($pkg->{$expireField})) {
            $days = (int) ($pkg->{$daysField} ?? 0);
            if ($days > 0) {
                $pkg->{$startField}  = now();
                $pkg->{$expireField} = now()->copy()->addDays($days);
                $pkg->save();
            }
        }

        if ($pkg->{$expireField} instanceof Carbon && $pkg->{$expireField}->isPast()) {
            return response()->json([
                'success' => false,
                'message' => "انتهت صلاحية {$title}.",
            ], 404);
        }

        $total  = (int) ($pkg->{$totalField} ?? 0);
        $used   = (int) ($pkg->{$usedField} ?? 0);
        $remain = max(0, $total - $used);

        // رصيد غير كافي
        if ($remain < $count) {
            return response()->json([
                'success' => false,
                'message' => "لا يوجد رصيد كافٍ في {$title} (المتبقي: {$remain}).",
            ], 422);
        }

        // خصم من الباقة
        $pkg->increment($usedField, $count);

        return response()->json([
            'success'     => true,
            'message'     => "تم خصم {$count} إعلان من {$title} ✅",
            'plan'        => $planType,
            'total'       => $total,
            'used'        => $used + $count,
            'remaining'   => max(0, $total - ($used + $count)),
            'expire_date' => $pkg->{$expireField},
            'package_id'  => $pkg->id,
        ], 200);
    }


    protected function normalizePlan(string $plan): string
    {
        $plan = strtolower(trim($plan));
        return match ($plan) {
            'premium', 'featured' => 'featured',
            'standard'            => 'standard',
            'free'                => 'free',
            default               => 'standard', // fallback آمن
        };
    }

    protected function mapFields(string $plan): array
    {
        return match ($plan) {
            'featured' => ['featured_ads', 'featured_ads_used', 'featured_days', 'featured_start_date', 'featured_expire_date', 'بريميوم'],
            'standard' => ['standard_ads', 'standard_ads_used', 'standard_days', 'standard_start_date', 'standard_expire_date', 'متميز'],
            default    => ['standard_ads', 'standard_ads_used', 'standard_days', 'standard_start_date', 'standard_expire_date', 'متميز'],
        };
    }

    protected function fail(string $msg): array
    {
        return ['success' => false, 'message' => $msg];
    }
}
