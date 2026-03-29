<?php

namespace App\Http\Controllers;

use App\Http\Resources\ListingResource;
use App\Models\Listing;
use App\Models\ListingReport;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AdminNotificationService;
use App\Services\NotificationService;
use App\Support\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ListingReportController extends Controller
{
    /**
     * Store a newly created report in storage.
     */
    public function store(Request $request, Listing $listing, NotificationService $notificationService, AdminNotificationService $adminNotificationService): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        $report = ListingReport::create([
            'listing_id' => $listing->id,
            'user_id' => Auth::id(), // Can be null if guest reporting is allowed, but usually auth required
            'reason' => $data['reason'],
            'details' => $data['details'] ?? null,
            'status' => 'pending',
        ]);

        // Notify the listing owner about the new report
        if ($listing->user_id) {
            $notificationService->dispatch(
                $listing->user_id,
                'بلاغ جديد على إعلانك',
                "تم تقديم بلاغ على إعلانك: {$listing->title}. السبب: {$data['reason']}",
                'report_received',
                ['listing_id' => $listing->id, 'report_id' => $report->id]
            );
        }

        // Notify Admin about the new report
        $adminNotificationService->dispatch(
            'بلاغ جديد على إعلان',
            "تم تقديم بلاغ جديد على إعلان رقم {$listing->id}: {$listing->title}. السبب: {$data['reason']}",
            'new_report',
            ['listing_id' => $listing->id, 'report_id' => $report->id]
        );

        return response()->json(['message' => __('api.report_submitted'), 'report' => $report], 201);
    }

    /**
     * Display a listing of the reports (Admin only).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $status = $request->query('status'); // Report status
        $search = $request->query('search'); // Search by title or advertiser code

        // Query Listings that have reports
        $query = Listing::query()
            ->whereHas('reports', function ($q) use ($status) {
                if ($status && $status !== 'all') {
                    $q->where('status', $status);
                }
            })
            ->with(['reports' => function ($q) {
                $q->select('id', 'listing_id', 'reason', 'created_at', 'status', 'is_read')
                    ->latest();
            }, 'user:id,name,referral_code,phone'])
            ->select('id', 'user_id', 'category_id', 'status', 'created_at', 'title');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%$search%")
                    ->orWhere('id', $search)
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('referral_code', 'like', "%$search%")
                            ->orWhere('name', 'like', "%$search%");
                    });
            });
        }

        // Order by the latest report date
        $listings = $query->orderByDesc(
            ListingReport::select('created_at')
                ->whereColumn('listing_reports.listing_id', 'listings.id')
                ->latest()
                ->limit(1)
        )->paginate($perPage);

        $items = collect($listings->items())->map(function (Listing $listing) {
            $sec = Section::fromId((int) $listing->category_id);
            $latestReport = $listing->reports->first(); // Since we ordered by latest in 'with' (or rely on DB order)
            // Actually 'with' doesn't guarantee order for 'first()', but let's sort in collection to be safe
            $latestReport = $listing->reports->sortByDesc('created_at')->first();

            return [
                // 'id_report'=>$latestReport->id,
                'is_read' => $latestReport?->is_read ?? false,
                'id' => $listing->id,
                'title' => $listing->title ?? 'إعلان رقم ' . $listing->id,
                'category_name' => $sec?->name,
                'status' => $listing->status === 'Valid' ? 'منشور' : ($listing->status === 'Pending' ? 'معلق' : 'مرفوض'),
                'advertiser_code' => $listing->user->referral_code ?? (string) $listing->user->id,
                'report_date' => optional($latestReport?->created_at)->toDateString(),
                'reasons' => $listing->reports->pluck('reason')->unique()->values(),
                'reports_count' => $listing->reports->count(),
                'report_status' => $latestReport?->status ?? 'pending',
            ];
        });

        return response()->json([
            'meta' => [
                'page' => $listings->currentPage(),
                'per_page' => $listings->perPage(),
                'total' => $listings->total(),
                'last_page' => $listings->lastPage(),
            ],
            'data' => $items,
        ]);
    }

    /**
     * Accept the report (Listing will be rejected).
     */
    public function acceptReport(Listing $listing, NotificationService $notificationService): JsonResponse
    {
        // Get the reason from the latest pending report before updating
        $latestReport = $listing->reports()->where('status', 'pending')->latest()->first();
        $reason = $latestReport ? $latestReport->reason : 'تم قبول البلاغ';

        // Mark all pending reports for this listing as resolved
        $listing->reports()->where('status', 'pending')->update([
            'status' => 'resolved',
        ]);

        // Reject the listing itself
        $listing->update([
            'status' => 'Rejected',
            'admin_comment' => $reason
        ]);

        // Notify the listing owner
        if ($listing->user_id) {
            $notificationService->dispatch(
                $listing->user_id,
                'تم قبول البلاغ ضد إعلانك',
                "تم مراجعة البلاغ وقبوله، وبناءً عليه تم رفض الإعلان. السبب: {$reason}",
                'report_accepted',
                ['listing_id' => $listing->id]
            );
        }

        return response()->json(['message' => __('api.report_accepted')]);
    }

    /**
     * Dismiss/Reject the report (Listing stays valid).
     */
    public function dismissReport(Listing $listing, NotificationService $notificationService): JsonResponse
    {
        // Mark all pending reports for this listing as dismissed
        $listing->reports()->where('status', 'pending')->update([
            'status' => 'dismissed',
        ]);

        // Check for automatic approval setting
        // $manualApprove = Cache::remember('settings:manual_approval', now()->addHours(6), function () {
        //     $val = SystemSetting::where('key', 'manual_approval')->value('value');
        //     return (int) $val === 1;
        // });

        // if ($manualApprove) {
        //     // Manual approval required:
        //     // If listing was 'Rejected', revert to 'Pending' so it can be approved properly.
        //     // If it was 'Valid' or 'Pending', keep status and just clear comment.
        //     if ($listing->status === 'Rejected') {
        //         $listing->update([
        //             'status' => 'Pending',
        //             'admin_comment' => null,
        //             'admin_approved' => false, // Ensure it requires approval
        //         ]);
        //     } else {
        //         $listing->update(['admin_comment' => null]);
        //     }
        // } else {
        //     // Auto approval enabled:
        // Force status to 'Valid' and 'admin_approved' to true.
        $listing->update([
            'status' => 'Valid',
            'admin_approved' => true,
            'admin_comment' => null,
        ]);
        // }

        // Notify the listing owner
        if ($listing->user_id) {
            $notificationService->dispatch(
                $listing->user_id,
                'تم رفض البلاغ ضد إعلانك',
                "تم مراجعة البلاغ ورفضه. إعلانك سليم ومتاح للعرض.",
                'report_dismissed',
                ['listing_id' => $listing->id]
            );
        }

        return response()->json(['message' => __('api.report_dismissed')]);
    }



    /**
     * Mark the report (and all reports for this listing) as read.
     */
    public function markAsRead($id): JsonResponse
    {
        $ad = Listing::findOrFail($id);

        // Also mark all other reports for the same listing as read
        ListingReport::where('listing_id', $ad->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $owner = User::select('id', 'name', 'created_at')->find($ad->user_id);

        $userPayload = [
            'id' => $owner?->id,
            'name' => $owner?->name ?? "advertiser",
            'joined_at' => $owner?->created_at?->toIso8601String(),
            'joined_at_human' => $owner?->created_at?->diffForHumans(),
        ];

        return (new ListingResource(
            $ad->load([
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
            'message' => 'All reports for this listing marked as read.',
        ])->response()->setStatusCode(200);
    }

    /**
     * Remove the specified report (Admin only).
     */
    public function destroy(ListingReport $report): JsonResponse
    {
        $report->delete();

        return response()->json([
            'message' => 'Report deleted successfully',
        ]);
    }
}
