<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $status = $request->query('status');

        $q = Notification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at');

        if ($status === 'unread') {
            $q->whereNull('read_at');
        } elseif ($status === 'read') {
            $q->whereNotNull('read_at');
        }

        $items = $q->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'meta' => [
                'page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'last_page' => $items->lastPage(),
            ],
            'data' => $items->items(),
        ]);
    }

    public function status(Request $request)
    {
        $user = $request->user();
        $count = Notification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'unread_count' => $count,
            'has_new' => $count > 0,
        ]);
    }
//Admin create notification 
    public function store(Request $request, NotificationService $service)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'type' => ['nullable', 'string', 'max:50'],
            'data' => ['nullable', 'array'],
        ]);

        $result = $service->dispatch(
            $data['user_id'],
            $data['title'],
            $data['body'],
            $data['type'] ?? null,
            $data['data'] ?? null,
            true // Bypass cooldown for admin
        );

        return response()->json([
            'message' => 'created',
            'data' => $result['notification'],
            'external_sent' => $result['external_sent'],
        ], 201);
    }

    public function markRead(Request $request, Notification $notification)
    {
        $user = $request->user();
        if ($notification->user_id !== $user->id) {
            return response()->json(['message' => __('api.forbidden')], 403);
        }
        $notification->read_at = now();
        $notification->save();
        return response()->json(['message' => __('api.notification_read')]);
    }

    public function markAllRead(Request $request)
    {
        $user = $request->user();
        Notification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
        return response()->json(['message' => 'ok']);
    }

    public function read(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:notifications,id'],
            'all' => ['nullable', 'boolean'],
        ]);

        if (!empty($data['id'])) {
            $n = Notification::find($data['id']);
            if (!$n || $n->user_id !== $user->id) {
                return response()->json(['message' => __('api.forbidden')], 403);
            }
            $n->read_at = now();
            $n->save();
            return response()->json(['message' => 'ok', 'id' => $n->id]);
        }

        Notification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'ok', 'all' => true]);
    }
}
