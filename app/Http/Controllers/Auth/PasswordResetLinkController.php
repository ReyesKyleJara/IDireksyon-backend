<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function sendApi(Request $request): JsonResponse
    {
        $request->merge([
            'email' => is_string($request->email)
                ? strtolower(trim($request->email))
                : $request->email,
        ]);
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        try {
            Password::sendResetLink([
                'email' => $data['email'],
                'is_active' => true,
                'role' => 'resident',
            ]);
        } catch (\Throwable $exception) {
            // Mail errors can contain credentials or a reset URL; do not log them.
            Log::error('Resident password reset email could not be sent.');

            return response()->json([
                'message' => 'Unable to send the reset email. Please try again later.',
            ], 503);
        }

        return response()->json([
            'message' => 'If an active account uses this email, a password reset link will be sent. Check your inbox and spam folder.',
        ]);
    }

    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
