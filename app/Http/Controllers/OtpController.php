<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Twilio\Rest\Client;
use Illuminate\Support\Facades\Log;

class OtpController extends Controller
{
    protected $client;

    public function __construct()
    {
        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');
        
        if ($sid && $token) {
             $this->client = new Client($sid, $token);
        }
    }

    public function send(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
        ]);

        $phone = $this->formatPhoneNumber($request->phone);
        $verifySid = config('services.twilio.verify_sid');

        if (!$this->client || !$verifySid) {
            return response()->json(['error' => 'Twilio not configured'], 500);
        }

        try {
            // Try sending via WhatsApp first as requested
            try {
                $verification = $this->client->verify->v2->services($verifySid)
                    ->verifications
                    ->create($phone, "whatsapp");

                return response()->json([
                    'status' => $verification->status,
                    'message' => 'OTP sent via WhatsApp',
                    'channel' => 'whatsapp'
                ]);
            } catch (\Exception $e) {
                // If WhatsApp fails, fallback to SMS
                // Note: The error might be because the user is not on WhatsApp or other Twilio errors.
                // We'll log it and try SMS.
                Log::info("Twilio WhatsApp OTP failed for $phone: " . $e->getMessage());

                $verification = $this->client->verify->v2->services($verifySid)
                    ->verifications
                    ->create($phone, "sms");
                    
                return response()->json([
                    'status' => $verification->status,
                    'message' => 'OTP sent via SMS (WhatsApp fallback)',
                    'channel' => 'sms'
                ]);
            }
        } catch (\Exception $e) {
             return response()->json([
                 'error' => 'Failed to send OTP',
                 'message' => $e->getMessage()
             ], 500);
        }
    }

    public function verify(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'code' => 'required|string',
        ]);

        $phone = $this->formatPhoneNumber($request->phone);
        $verifySid = config('services.twilio.verify_sid');

         if (!$this->client || !$verifySid) {
            return response()->json(['error' => 'Twilio not configured'], 500);
        }

        try {
            $verification_check = $this->client->verify->v2->services($verifySid)
                ->verificationChecks
                ->create([
                    'to' => $phone,
                    'code' => $request->code
                ]);

            if ($verification_check->status === 'approved') {
                return response()->json(['status' => 'approved', 'message' => 'OTP verified successfully']);
            }

            return response()->json(['status' => $verification_check->status, 'message' => 'Invalid OTP'], 400);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Verification failed', 'message' => $e->getMessage()], 500);
        }
    }

    private function formatPhoneNumber($phone)
    {
        // Remove any non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Ensure it starts with + if it doesn't already
        if (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }
        
        return $phone;
    }
}
