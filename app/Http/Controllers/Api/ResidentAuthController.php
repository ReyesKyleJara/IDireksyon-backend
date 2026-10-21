<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ResidentAuthController extends Controller
{
    private function phone(string $value): string
    {
        $value = preg_replace('/[\s()-]/', '', $value);

        return str_starts_with($value, '09')
            ? '+63'.substr($value, 1)
            : $value;
    }

    public function register(Request $request): JsonResponse
    {
        $request->merge([
            'email' => is_string($request->email)
                ? strtolower(trim($request->email))
                : $request->email,

            'phone' => is_string($request->phone)
                ? $this->phone($request->phone)
                : $request->phone,
        ]);

        $data = $request->validate([
            'email_verification_token' => ['required_with:email', 'nullable', 'string', 'size:64'],
            'email' => [
                'nullable',
                'required_without:phone',
                'prohibits:phone',
                'email',
                'max:255',
                'unique:users,email',
            ],

            'phone' => [
                'nullable',
                'required_without:email',
                'prohibits:email',
                'regex:/^\+639\d{9}$/',
                'unique:users,phone',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
                'max:128',
                'confirmed',
            ],

            'terms_accepted' => [
                'accepted',
            ],
        ]);

        return DB::transaction(function () use ($data) {
            if (! empty($data['email'])) {
                app(\App\Services\SignupEmailVerification::class)->consume($data['email'], $data['email_verification_token']);
            }
            $user = User::create(
                collect($data)
                    ->only([
                        'email',
                        'phone',
                        'password',
                    ])
                    ->all()
            );

            if (! empty($data['email'])) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            return $this->session($user, 201);
        });
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => [
                'required',
                'string',
                'max:255',
            ],
            'password' => [
                'required',
                'string',
                'max:128',
            ],
        ]);

        $identifier = strtolower(trim($data['identifier']));

        $user = str_contains($identifier, '@')
            ? User::where('email', $identifier)->first()
            : User::where(
                'phone',
                $this->phone($identifier)
            )->first();

        if (
            ! $user ||
            ! Hash::check($data['password'], $user->password) ||
            ! $user->is_active ||
            $user->role !== 'resident'
        ) {
            throw ValidationException::withMessages([
                'identifier' => 'The provided credentials are incorrect.',
            ]);
        }

        return $this->session($user);
    }

    private function session(
        User $user,
        int $status = 200
    ): JsonResponse {
        return response()->json([
            'user' => $this->profile($user),
            'token' => $user
                ->createToken(
                    'resident-app',
                    ['resident'],
                    now()->addDays(30)
                )
                ->plainTextToken,
        ], $status);
    }

    public function me(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->is_active &&
            $request->user()->role === 'resident',
            403
        );

        return response()->json([
            'user' => $this->profile($request->user()),
        ]);
    }

    private function profile(User $user): array
    {
        return $user->only([
            'id',
            'name',
            'email',
            'phone',
            'date_of_birth',
            'barangay_id',
            'owned_ids',
            'owned_documents',
            'profile_setup_completed_at',
        ]);
    }

    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user->is_active &&
            $user->role === 'resident',
            403
        );

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'date_of_birth' => [
                'required',
                'date',
                'before:today',
            ],

            'barangay_id' => [
                'required',
                'integer',
                'exists:barangays,id',
            ],

            'government_id_ids' => [
                'present',
                'array',
            ],

            'government_id_ids.*' => [
                'integer',
                'distinct',
                'exists:government_ids,id',
            ],

            'document_ids' => [
                'present',
                'array',
            ],

            'document_ids.*' => [
                'integer',
                'distinct',
                'exists:documents,id',
            ],
        ]);

        DB::transaction(function () use ($user, $data) {
            /*
             * Save the resident's basic profile details.
             */
            $user->fill([
                'name' => trim($data['name']),
                'date_of_birth' => $data['date_of_birth'],
                'barangay_id' => $data['barangay_id'],
            ])->save();

            /*
             * Save the resident's current inventory.
             */
            $user->ownedGovernmentIds()->sync(
                $data['government_id_ids']
            );

            $user->ownedDocuments()->sync(
                $data['document_ids']
            );

            /*
             * Keep the legacy JSON fields synchronized temporarily.
             * They are no longer the source of truth.
             */
            $user->forceFill([
                'owned_ids' => $user
                    ->ownedGovernmentIds()
                    ->orderBy('government_ids.name')
                    ->pluck('government_ids.name')
                    ->values()
                    ->all(),

                'owned_documents' => $user
                    ->ownedDocuments()
                    ->orderBy('documents.name')
                    ->pluck('documents.name')
                    ->values()
                    ->all(),

                'profile_setup_completed_at' => now(),
            ])->save();
        });

        return response()->json([
            'user' => $this->profile($user->fresh()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()
            ->currentAccessToken()
            ->delete();

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }

    public function updateAccount(
        Request $request
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user->is_active &&
            $user->role === 'resident',
            403
        );

        $contact = $user->email !== null
            ? 'email'
            : 'phone';

        $value = $request->input($contact);

        $request->merge([
            'name' => is_string($request->name)
                ? trim($request->name)
                : $request->name,

            $contact => is_string($value)
                ? (
                    $contact === 'email'
                        ? strtolower(trim($value))
                        : $this->phone($value)
                )
                : $value,
        ]);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            $contact => [
                'required',
                'string',
                'max:255',
                $contact === 'email'
                    ? 'email'
                    : 'regex:/^\+639\d{9}$/',

                Rule::unique('users', $contact)
                    ->ignore($user->id),
            ],
        ]);

        if (
            $contact === 'email' &&
            $data['email'] !== $user->email
        ) {
            $user->email_verified_at = null;
        }

        $user->fill($data)->save();

        return response()->json([
            'user' => $this->profile($user),
        ]);
    }

    public function updatePassword(
        Request $request
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user->is_active &&
            $user->role === 'resident',
            403
        );

        $data = $request->validate([
            'current_password' => [
                'required',
                'string',
                'max:128',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
                'max:128',
                'confirmed',
                'different:current_password',
            ],
        ]);

        if (
            ! Hash::check(
                $data['current_password'],
                $user->password
            )
        ) {
            throw ValidationException::withMessages([
                'current_password' =>
                    'Your current password is incorrect.',
            ]);
        }

        $user->forceFill([
            'password' => Hash::make(
                $data['password']
            ),
        ])->save();

        return response()->json([
            'message' => 'Password updated.',
        ]);
    }
}
