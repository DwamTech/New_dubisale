<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserClient;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $data = $request->validated();

        $existingUser = null;
        if (!empty($data['phone'])) {
            $existingUser = User::where('phone', $data['phone'])->first();
        }
        if (!$existingUser && !empty($data['email'])) {
            $existingUser = User::where('email', $data['email'])->first();
        }

        if ($existingUser) {
            if ($existingUser->status !== 'active') {
                return response()->json(['message' => __('api.user_inactive')], 403);
            }
            if (!Hash::check($data['password'], $existingUser->password)) {
                return response()->json(['message' => __('api.invalid_credentials')], 401);
            }
            if (!empty($data['referral_code']) && $existingUser->referral_code !== $data['referral_code']) {
                return response()->json(['message' => __('api.invalid_referral_code')], 401);
            }
            $message = __('api.login_success');
            $user = $existingUser;
        } else {
            if (empty($data['phone'])) {
                return response()->json(['message' => __('api.phone_required')], 422);
            }

            if (!empty($data['referral_code'])) {
                $delegateUser = User::where('id', $data['referral_code'])
                    ->where('role', 'representative')->first();
                if (!$delegateUser) {
                    return response()->json(['message' => __('api.invalid_delegate_code')], 404);
                }
            }

            $user = User::create([
                'name'         => $data['name'] ?? null,
                'phone'        => $data['phone'],
                'role'         => 'user',
                'password'     => Hash::make($data['password']),
                'country_code' => $data['country_code'] ?? null,
                'referral_code'=> $data['referral_code'] ?? null,
            ]);

            if (!empty($data['referral_code'])) {
                $userClient = UserClient::firstOrCreate(
                    ['user_id' => $data['referral_code']],
                    ['clients' => []]
                );
                $clients = $userClient->clients ?? [];
                if (!in_array($user->id, $clients)) {
                    $clients[] = $user->id;
                    $userClient->clients = $clients;
                    $userClient->save();
                }
            }

            $message = __('api.register_success');
        }

        $token = $user->createToken('nasmasr_token')->plainTextToken;

        return response()->json([
            'message' => $message,
            'user'    => new UserResource($user),
            'token'   => $token,
        ], 201);
    }

    public function adminLogin(Request $request)
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->where('role', 'admin')->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => __('api.invalid_credentials')], 401);
        }
        if ($user->status !== 'active') {
            return response()->json(['message' => __('api.account_inactive')], 403);
        }

        $token = $user->createToken('admin_token')->plainTextToken;

        return response()->json([
            'message' => __('api.admin_login_success'),
            'user'    => new UserResource($user),
            'token'   => $token,
        ]);
    }

    public function changePass(User $user)
    {
        $user->password = Hash::make('123456');
        $user->save();
        return response()->json([
            'message' => 'مرحبًا ' . $user->name . '، تم تغيير كلمة السر الخاصة بحسابك إلى: 123456. يرجى تسجيل الدخول وتغييرها بعد أول دخول. فريق ناس مصر',
        ]);
    }
}
