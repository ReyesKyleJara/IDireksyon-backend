<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SignupEmailVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SignupEmailVerificationController extends Controller
{
    public function send(Request $request, SignupEmailVerification $verification): JsonResponse
    {
        $request->merge(['email' => is_string($request->email) ? strtolower(trim($request->email)) : $request->email]);
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:255', 'unique:users,email']]);

        return response()->json([
            'challenge_id' => $verification->send($data['email']),
            'expires_in' => 600, 'resend_after' => 60,
            'message' => 'Check your email for the verification code.',
        ]);
    }

    public function verify(Request $request, SignupEmailVerification $verification): JsonResponse
    {
        $data = $request->validate([
            'challenge_id' => ['required', 'uuid'],
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        return response()->json(['email_verification_token' => $verification->verify($data['challenge_id'], $data['code'])]);
    }
}
