<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ResolveChatUser
{
    public function handle(Request $request, Closure $next)
    {
        // ── 1. Already authenticated via Sanctum token ────────────────────────
        if (Auth::guard('sanctum')->check()) {
            Auth::shouldUse('sanctum');
            return $next($request);
        }

        // ── 2. Guest flow: resolve by guest_uuid header ───────────────────────
        $guestUuid = $request->header('guest_uuid');

        if (!$guestUuid) {
            return response()->json([
                'message' => __('api.unauthenticated'),
            ], 401);
        }

        $guest = User::where('guest_uuid', $guestUuid)->first();

        if (!$guest) {
            return response()->json([
                'message' => __('api.guest_not_found'),
            ], 401);
        }

        // Bind the guest as the acting user for this request so that
        // $request->user() and auth()->user() both return this guest.
        Auth::setUser($guest);

        return $next($request);
    }
}
