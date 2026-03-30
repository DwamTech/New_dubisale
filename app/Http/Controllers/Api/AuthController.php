<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\OtpVerification;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Twilio\Rest\Client as TwilioClient;
use Twilio\Exceptions\TwilioException;

class AuthController extends Controller
{
    private const OTP_EXPIRY_MINUTES   = 10;
    private const OTP_COOLDOWN_SECONDS = 60;

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function maxAttempts(): int
    {
        return (int) Cache::remember('settings:otp_max_attempts', now()->addHours(6), function () {
            return SystemSetting::where('key', 'otp_max_attempts')->value('value') ?? 5;
        });
    }

    private function cleanPhoneNumber(string $phone): string
    {
        $phone = preg_replace('/[^\d+]/', '', $phone);

        // Fix Egyptian numbers: +2001... → +201...
        if (preg_match('/^\+200(\d{10})$/', $phone, $m)) {
            $phone = '+20' . $m[1];
        }
        // 00... → +...
        if (str_starts_with($phone, '00')) {
            $phone = '+' . substr($phone, 2);
        }
        // Egyptian local 01... → +2001...
        if (preg_match('/^01\d{9}$/', $phone)) {
            $phone = '+20' . $phone;
        }

        return $phone;
    }

    private function twilioConfig(): array
    {
        return [
            'sid'        => config('services.twilio.sid'),
            'token'      => config('services.twilio.token'),
            'verifySid'  => config('services.twilio.verify_sid'),
        ];
    }

    private function twilioClient(): ?TwilioClient
    {
        $cfg = $this->twilioConfig();
        if (!$cfg['sid'] || !$cfg['token'] || !$cfg['verifySid']) {
            return null;
        }
        return new TwilioClient($cfg['sid'], $cfg['token']);
    }

    private function sendOtp(string $phone): array
    {
        $cfg    = $this->twilioConfig();
        $client = $this->twilioClient();

        if (!$client) {
            return ['ok' => false, 'status' => 500, 'key' => 'error.twilio_config'];
        }

        try {
            $verification = $client->verify->v2->services($cfg['verifySid'])
                ->verifications->create($phone, 'whatsapp');

            Log::info('otp_sent', ['phone' => $phone, 'channel' => $verification->channel]);
            return ['ok' => true];
        } catch (TwilioException $e) {
            Log::error('otp_send_twilio_error', ['phone' => $phone, 'error' => $e->getMessage()]);
            $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
            return [
                'ok'     => false,
                'status' => $status === 429 ? 429 : 500,
                'key'    => $status === 429 ? 'error.twilio_rate_limit' : 'error.twilio_send',
                'error'  => $e->getMessage(),
            ];
        }
    }

    private function checkOtp(string $phone, string $code): array
    {
        $cfg    = $this->twilioConfig();
        $client = $this->twilioClient();

        if (!$client) {
            return ['ok' => false, 'status' => 500, 'key' => 'error.twilio_config'];
        }

        try {
            $check = $client->verify->v2->services($cfg['verifySid'])
                ->verificationChecks->create(['to' => $phone, 'code' => $code]);

            return ['ok' => $check->status === 'approved', 'twilio_status' => $check->status];
        } catch (TwilioException $e) {
            Log::error('otp_check_twilio_error', ['phone' => $phone, 'error' => $e->getMessage()]);
            return ['ok' => false, 'status' => 500, 'key' => 'error.twilio_verify', 'error' => $e->getMessage()];
        }
    }

    private function addToAgentClients(UserClient $agentClient, int $userId): void
    {
        $clients = $agentClient->clients ?? [];
        if (!in_array($userId, $clients)) {
            $clients[] = $userId;
            $agentClient->update(['clients' => $clients]);
        }
    }

    // ── POST /api/auth/login-or-register ─────────────────────────────────────

    public function loginOrRegister(RegisterRequest $request)
    {
        $data = $request->validated();

        $user = null;
        if (!empty($data['phone'])) {
            $user = User::where('phone', $data['phone'])->first();
        }
        if (!$user && !empty($data['email'])) {
            $user = User::where('email', $data['email'])->first();
        }

        // ── Existing user: login ──────────────────────────────────────────────
        if ($user) {
            if ($user->status !== 'active') {
                return response()->json(['ok' => false, 'message' => __('api.user_inactive')], 403);
            }
            if (!Hash::check($data['password'], $user->password)) {
                return response()->json(['ok' => false, 'message' => __('api.invalid_credentials')], 401);
            }

            if (!empty($data['referral_code'])) {
                $agentClient = UserClient::where('user_id', $data['referral_code'])->first();
                if ($agentClient) {
                    $user->referral_code = $data['referral_code'];
                    $user->save();
                    $this->addToAgentClients($agentClient, $user->id);
                }
            }

            $token = $user->createToken('nasmasr_token')->plainTextToken;
            return response()->json([
                'ok'      => true,
                'message' => __('api.login_success'),
                'status'  => 'logged_in',
                'user'    => new UserResource($user),
                'token'   => $token,
            ]);
        }

        // ── New user: send OTP ────────────────────────────────────────────────
        if (empty($data['phone'])) {
            return response()->json(['ok' => false, 'message' => __('api.phone_required')], 422);
        }

        if (!empty($data['referral_code'])) {
            $delegate = User::where('id', $data['referral_code'])->where('role', 'representative')->first();
            if (!$delegate) {
                return response()->json(['ok' => false, 'message' => __('api.invalid_delegate_code')], 404);
            }
        }

        $phone = $this->cleanPhoneNumber($data['phone']);

        $recent = OtpVerification::where('phone', $phone)
            ->where('type', 'registration')
            ->where('status', 'pending')
            ->latest()->first();

        if ($recent && !$recent->canResend(self::OTP_COOLDOWN_SECONDS)) {
            return response()->json([
                'ok'                => false,
                'message'           => __('api.otp_cooldown', ['seconds' => $recent->remainingCooldown(self::OTP_COOLDOWN_SECONDS)]),
                'remaining_seconds' => $recent->remainingCooldown(self::OTP_COOLDOWN_SECONDS),
            ], 429);
        }

        OtpVerification::where('phone', $phone)
            ->where('type', 'registration')
            ->whereIn('status', ['pending', 'expired'])
            ->delete();

        $result = $this->sendOtp($phone);
        if (!$result['ok']) {
            return response()->json([
                'ok'      => false,
                'message' => __('api.' . ($result['key'] ?? 'otp_send_failed')),
            ], $result['status'] ?? 500);
        }

        OtpVerification::create([
            'phone'        => $phone,
            'type'         => 'registration',
            'payload'      => [
                'password'     => Hash::make($data['password']),
                'agent_code'   => $data['referral_code'] ?? null,
                'name'         => $data['name'] ?? ('user_' . substr($phone, -4)),
                'country_code' => $data['country_code'] ?? null,
                'role'         => 'user',
                'status'       => 'active',
            ],
            'status'       => 'pending',
            'attempts'     => 0,
            'expires_at'   => now()->addMinutes(self::OTP_EXPIRY_MINUTES),
            'last_sent_at' => now(),
            'ip_address'   => request()->ip(),
        ]);

        return response()->json([
            'ok'           => true,
            'message'      => __('api.otp_required'),
            'status'       => 'otp_required',
            'phone'        => $phone,
            'expires_in'   => self::OTP_EXPIRY_MINUTES * 60,
            'resend_after' => self::OTP_COOLDOWN_SECONDS,
        ]);
    }

    // ── POST /api/auth/verify-otp-register ───────────────────────────────────

    public function verifyOtpRegister(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'code'  => ['required', 'string'],
        ]);

        $phone = $this->cleanPhoneNumber($data['phone']);

        $record = OtpVerification::where('phone', $phone)
            ->where('type', 'registration')
            ->where('status', 'pending')
            ->latest()->first();

        if (!$record) {
            return response()->json(['ok' => false, 'message' => __('api.otp_record_not_found')], 404);
        }
        if ($record->isExpired()) {
            $record->markAsExpired();
            return response()->json(['ok' => false, 'message' => __('api.otp_expired')], 422);
        }
        if ($record->hasExceededAttempts($this->maxAttempts())) {
            $record->markAsExpired();
            return response()->json(['ok' => false, 'message' => __('api.otp_max_attempts')], 429);
        }

        $check = $this->checkOtp($phone, $data['code']);

        if (isset($check['key'])) {
            return response()->json(['ok' => false, 'message' => __('api.' . $check['key'])], $check['status'] ?? 500);
        }

        if (!$check['ok']) {
            $record->incrementAttempts();
            if ($record->fresh()->hasExceededAttempts($this->maxAttempts())) {
                $record->markAsExpired();
                return response()->json(['ok' => false, 'message' => __('api.otp_max_attempts')], 429);
            }
            return response()->json([
                'ok'      => false,
                'message' => __('api.otp_invalid_code'),
                'status'  => $check['twilio_status'] ?? null,
            ], 422);
        }

        $payload = $record->payload ?? [];

        if (empty($payload['password'])) {
            $record->markAsExpired();
            return response()->json(['ok' => false, 'message' => __('api.otp_record_not_found')], 422);
        }

        return DB::transaction(function () use ($record, $phone, $payload) {
            $user = User::where('phone', $phone)->first();

            if ($user) {
                $record->markAsVerified();
                $token = $user->createToken('nasmasr_token')->plainTextToken;
                return response()->json([
                    'ok'      => true,
                    'message' => __('api.login_success'),
                    'user'    => new UserResource($user),
                    'token'   => $token,
                ]);
            }

            $user = User::create([
                'name'         => $payload['name'] ?? ('user_' . substr($phone, -4)),
                'phone'        => $phone,
                'password'     => $payload['password'],
                'role'         => $payload['role'] ?? 'user',
                'status'       => $payload['status'] ?? 'active',
                'country_code' => $payload['country_code'] ?? null,
                'referral_code'=> $payload['agent_code'] ?? null,
            ]);

            if (!empty($payload['agent_code'])) {
                $agentClient = UserClient::where('user_id', $payload['agent_code'])->first();
                if ($agentClient) {
                    $this->addToAgentClients($agentClient, $user->id);
                }
            }

            $record->markAsVerified();

            Log::info('otp_verified_and_user_created', ['phone' => $phone, 'user_id' => $user->id]);

            $token = $user->createToken('nasmasr_token')->plainTextToken;
            return response()->json([
                'ok'      => true,
                'message' => __('api.register_success'),
                'user'    => new UserResource($user),
                'token'   => $token,
            ], 201);
        });
    }

    // ── POST /api/auth/resend-otp ─────────────────────────────────────────────

    public function resendOtp(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'type'  => ['required', 'in:registration,password_reset'],
        ]);

        $phone = $this->cleanPhoneNumber($data['phone']);

        $record = OtpVerification::where('phone', $phone)
            ->where('type', $data['type'])
            ->whereIn('status', ['pending', 'expired'])
            ->latest()->first();

        if (!$record) {
            return response()->json(['ok' => false, 'message' => __('api.otp_record_not_found')], 404);
        }

        // If pending but expired by time, mark it expired
        if ($record->status === 'pending' && $record->isExpired()) {
            $record->markAsExpired();
            $record->refresh();
        }

        // Cooldown only applies to still-pending records
        if ($record->status === 'pending' && !$record->canResend(self::OTP_COOLDOWN_SECONDS)) {
            return response()->json([
                'ok'                => false,
                'message'           => __('api.otp_cooldown', ['seconds' => $record->remainingCooldown(self::OTP_COOLDOWN_SECONDS)]),
                'remaining_seconds' => $record->remainingCooldown(self::OTP_COOLDOWN_SECONDS),
            ], 429);
        }

        $result = $this->sendOtp($phone);
        if (!$result['ok']) {
            return response()->json([
                'ok'      => false,
                'message' => __('api.' . ($result['key'] ?? 'otp_send_failed')),
            ], $result['status'] ?? 500);
        }

        $record->update([
            'status'       => 'pending',
            'attempts'     => 0,
            'expires_at'   => now()->addMinutes(self::OTP_EXPIRY_MINUTES),
            'last_sent_at' => now(),
        ]);

        return response()->json([
            'ok'           => true,
            'message'      => __('api.otp_resent'),
            'phone'        => $phone,
            'expires_in'   => self::OTP_EXPIRY_MINUTES * 60,
            'resend_after' => self::OTP_COOLDOWN_SECONDS,
        ]);
    }

    // ── POST /api/auth/reset-password ─────────────────────────────────────────

    public function sendResetOtp(Request $request)
    {
        $data  = $request->validate(['phone' => ['required', 'string']]);
        $phone = $this->cleanPhoneNumber($data['phone']);
        $user  = User::where('phone', $phone)->first();

        if (!$user) {
            return response()->json(['ok' => false, 'message' => __('api.reset_user_not_found')], 404);
        }

        $recent = OtpVerification::where('phone', $phone)
            ->where('type', 'password_reset')
            ->where('status', 'pending')
            ->latest()->first();

        if ($recent && !$recent->canResend(self::OTP_COOLDOWN_SECONDS)) {
            return response()->json([
                'ok'                => false,
                'message'           => __('api.otp_cooldown', ['seconds' => $recent->remainingCooldown(self::OTP_COOLDOWN_SECONDS)]),
                'remaining_seconds' => $recent->remainingCooldown(self::OTP_COOLDOWN_SECONDS),
            ], 429);
        }

        OtpVerification::where('phone', $phone)
            ->where('type', 'password_reset')
            ->whereIn('status', ['pending', 'expired'])
            ->delete();

        $result = $this->sendOtp($phone);
        if (!$result['ok']) {
            return response()->json([
                'ok'      => false,
                'message' => __('api.' . ($result['key'] ?? 'otp_send_failed')),
            ], $result['status'] ?? 500);
        }

        OtpVerification::create([
            'phone'        => $phone,
            'type'         => 'password_reset',
            'payload'      => ['type' => 'password_reset', 'user_id' => $user->id],
            'status'       => 'pending',
            'attempts'     => 0,
            'expires_at'   => now()->addMinutes(self::OTP_EXPIRY_MINUTES),
            'last_sent_at' => now(),
            'ip_address'   => request()->ip(),
        ]);

        Log::info('password_reset_otp_sent', ['phone' => $phone, 'user_id' => $user->id]);

        return response()->json([
            'ok'           => true,
            'message'      => __('api.reset_otp_required'),
            'status'       => 'otp_required',
            'phone'        => $phone,
            'expires_in'   => self::OTP_EXPIRY_MINUTES * 60,
            'resend_after' => self::OTP_COOLDOWN_SECONDS,
        ]);
    }

    // ── POST /api/auth/verify-password-reset ──────────────────────────────────

    public function verifyPasswordReset(Request $request)
    {
        $data = $request->validate([
            'phone'        => ['required', 'string'],
            'code'         => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $phone = $this->cleanPhoneNumber($data['phone']);

        $record = OtpVerification::where('phone', $phone)
            ->where('type', 'password_reset')
            ->where('status', 'pending')
            ->latest()->first();

        if (!$record) {
            return response()->json(['ok' => false, 'message' => __('api.otp_record_not_found')], 404);
        }
        if ($record->isExpired()) {
            $record->markAsExpired();
            return response()->json(['ok' => false, 'message' => __('api.otp_expired')], 422);
        }
        if ($record->hasExceededAttempts($this->maxAttempts())) {
            $record->markAsExpired();
            return response()->json(['ok' => false, 'message' => __('api.otp_max_attempts')], 429);
        }

        $check = $this->checkOtp($phone, $data['code']);

        if (isset($check['key'])) {
            return response()->json(['ok' => false, 'message' => __('api.' . $check['key'])], $check['status'] ?? 500);
        }

        if (!$check['ok']) {
            $record->incrementAttempts();
            if ($record->fresh()->hasExceededAttempts($this->maxAttempts())) {
                $record->markAsExpired();
                return response()->json(['ok' => false, 'message' => __('api.otp_max_attempts')], 429);
            }
            return response()->json([
                'ok'      => false,
                'message' => __('api.otp_invalid_code'),
                'status'  => $check['twilio_status'] ?? null,
            ], 422);
        }

        return DB::transaction(function () use ($record, $phone, $data) {
            $userId = $record->payload['user_id'] ?? null;
            $user   = $userId ? User::find($userId) : User::where('phone', $phone)->first();

            if (!$user) {
                return response()->json(['ok' => false, 'message' => __('api.reset_user_not_found')], 404);
            }

            $user->update(['password' => Hash::make($data['new_password'])]);
            $record->markAsVerified();

            Log::info('password_reset_successful', ['phone' => $phone, 'user_id' => $user->id]);

            $token = $user->createToken('nasmasr_token')->plainTextToken;
            return response()->json([
                'ok'      => true,
                'message' => __('api.reset_success'),
                'user'    => new UserResource($user),
                'token'   => $token,
            ]);
        });
    }

    // ── Legacy / Admin ────────────────────────────────────────────────────────

    public function register(RegisterRequest $request)
    {
        $data = $request->validated();

        $user = null;
        if (!empty($data['phone'])) $user = User::where('phone', $data['phone'])->first();
        if (!$user && !empty($data['email'])) $user = User::where('email', $data['email'])->first();

        if ($user) {
            if ($user->status !== 'active') return response()->json(['message' => __('api.user_inactive')], 403);
            if (!Hash::check($data['password'], $user->password)) return response()->json(['message' => __('api.invalid_credentials')], 401);
            $message = __('api.login_success');
        } else {
            if (empty($data['phone'])) return response()->json(['message' => __('api.phone_required')], 422);
            if (!empty($data['referral_code'])) {
                if (!User::where('id', $data['referral_code'])->where('role', 'representative')->exists()) {
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
                $uc = UserClient::firstOrCreate(['user_id' => $data['referral_code']], ['clients' => []]);
                $clients = $uc->clients ?? [];
                if (!in_array($user->id, $clients)) { $clients[] = $user->id; $uc->clients = $clients; $uc->save(); }
            }
            $message = __('api.register_success');
        }

        $token = $user->createToken('nasmasr_token')->plainTextToken;
        return response()->json(['message' => $message, 'user' => new UserResource($user), 'token' => $token], 201);
    }

    public function adminLogin(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $user = User::where('email', $data['email'])->where('role', 'admin')->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => __('api.invalid_credentials')], 401);
        }
        if ($user->status !== 'active') {
            return response()->json(['message' => __('api.account_inactive')], 403);
        }

        $token = $user->createToken('admin_token')->plainTextToken;
        return response()->json(['message' => __('api.admin_login_success'), 'user' => new UserResource($user), 'token' => $token]);
    }

    public function changePass(User $user)
    {
        $user->password = Hash::make('123456');
        $user->save();
        return response()->json(['message' => 'تم تغيير كلمة السر إلى: 123456']);
    }
}
