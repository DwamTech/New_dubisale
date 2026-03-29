<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserClient;
use App\Traits\LocalizedResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserClientController extends Controller
{
    use LocalizedResponse;

    public function index(): JsonResponse
    {
        $data = UserClient::with('user')->get();

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    public function show(int $userId): JsonResponse
    {
        $userClient = UserClient::where('user_id', $userId)->first();

        if (! $userClient) {
            return response()->json([
                'success' => false,
                'message' => __('api.user_client_not_found'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'user_id' => $userClient->user_id,
                'clients' => $userClient->clients ?? [],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id'     => ['required', 'integer', 'exists:users,id'],
            'clients'     => ['nullable', 'array'],
            'clients.*'   => ['integer'],
        ]);

        $existing = UserClient::where('user_id', $validated['user_id'])->first();
        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => __('api.user_client_exists'),
            ], 422);
        }

        $userClient = UserClient::create([
            'user_id' => $validated['user_id'],
            'clients' => $validated['clients'] ?? [],
        ]);

        return response()->json([
            'success' => true,
            'message' => __('api.user_client_created'),
            'data'    => $userClient,
        ], 201);
    }

    public function update(Request $request, int $userId): JsonResponse
    {
        $validated = $request->validate([
            'clients'   => ['required', 'array'],
            'clients.*' => ['integer'],
        ]);

        $userClient = UserClient::where('user_id', $userId)->first();

        if (! $userClient) {
            return response()->json([
                'success' => false,
                'message' => __('api.user_client_not_found'),
            ], 404);
        }

        $userClient->clients = $validated['clients'];
        $userClient->save();

        return response()->json([
            'success' => true,
            'message' => __('api.user_client_updated'),
            'data'    => $userClient,
        ]);
    }

    public function addClient(Request $request, int $userId): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'integer'],
        ]);

        $userClient = UserClient::firstOrCreate(
            ['user_id' => $userId],
            ['clients' => []]
        );

        $clients   = $userClient->clients ?? [];
        $clients[] = $validated['client_id'];

        $userClient->clients = $clients;
        $userClient->save();

        return response()->json([
            'success' => true,
            'message' => __('api.user_client_added'),
            'data'    => $userClient,
        ]);
    }

    public function removeClient(Request $request, int $userId): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'integer'],
        ]);

        $userClient = UserClient::where('user_id', $userId)->first();

        if (! $userClient) {
            return response()->json([
                'success' => false,
                'message' => __('api.user_client_not_found'),
            ], 404);
        }

        $clients = array_values(array_filter(
            $userClient->clients ?? [],
            fn($id) => (int) $id !== (int) $validated['client_id']
        ));

        $userClient->clients = $clients;
        $userClient->save();

        return response()->json([
            'success' => true,
            'message' => __('api.user_client_removed'),
            'data'    => $userClient,
        ]);
    }

    public function destroy(int $userId): JsonResponse
    {
        $userClient = UserClient::where('user_id', $userId)->first();

        if (! $userClient) {
            return response()->json([
                'success' => false,
                'message' => __('api.user_client_not_found'),
            ], 404);
        }

        $userClient->delete();

        return response()->json([
            'success' => true,
            'message' => __('api.user_client_deleted'),
        ]);
    }
}
