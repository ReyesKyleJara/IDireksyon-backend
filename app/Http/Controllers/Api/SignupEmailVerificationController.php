<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SignupEmailVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class SignupEmailVerificationController extends Controller
{
    public function send(Request $request, SignupEmailVerification $verification): JsonResponse
    {
        $request->merge(['email' => is_string($request->email) ? strtolower(trim($request->email)) : $request->email]);
        $data = $request->validate(['email' => ['required', 'email', 'max:255', 'unique:users,email']]);
        try {
            $id = $verification->send($data['email']);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'We could not send your verification email. Please try again shortly.'], 503);
        }

        return response()->json(['challenge_id' => $id, 'expires_in' => 600]);
    }

    public function verify(Request $request, SignupEmailVerification $verification): JsonResponse
    {
        $data = $request->validate([
            'challenge_id' => ['required', 'uuid'],
            'code' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
        ]);

        return response()->json(['email_verification_token' => $verification->verify($data['challenge_id'], $data['code'])]);
    }
}
