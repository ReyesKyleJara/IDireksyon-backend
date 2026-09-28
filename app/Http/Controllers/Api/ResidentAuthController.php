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

        return str_starts_with($value, '09') ? '+63'.substr($value, 1) : $value;
    }

    public function register(Request $request): JsonResponse
    {
        $request->merge([
            'name' => is_string($request->name) ? trim($request->name) : $request->name,
            'email' => is_string($request->email) ? strtolower(trim($request->email)) : $request->email,
            'phone' => is_string($request->phone) ? $this->phone($request->phone) : $request->phone,
        ]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'required_without:phone', 'prohibits:phone', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'required_without:email', 'prohibits:email', 'regex:/^\+639\d{9}$/', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8', 'max:128', 'confirmed'],
            'terms_accepted' => ['accepted'],
        ]);

        return DB::transaction(function () use ($data) {
            $user = User::create(collect($data)->only(['name', 'email', 'phone', 'password'])->all());

            return $this->session($user, 201);
        });
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:128'],
        ]);
        $identifier = strtolower(trim($data['identifier']));
        $user = str_contains($identifier, '@')
            ? User::where('email', $identifier)->first()
            : User::where('phone', $this->phone($identifier))->first();

        if (! $user || ! Hash::check($data['password'], $user->password) || ! $user->is_active || $user->role !== 'resident') {
            throw ValidationException::withMessages(['identifier' => 'The provided credentials are incorrect.']);
        }

        return $this->session($user);
    }

    private function session(User $user, int $status = 200): JsonResponse
    {
        return response()->json([
            'user' => $this->profile($user),
            'token' => $user->createToken('resident-app', ['resident'], now()->addDays(30))->plainTextToken,
        ], $status);
    }

    public function me(Request $request): JsonResponse
    {
        abort_unless($request->user()->is_active && $request->user()->role === 'resident', 403);

        return response()->json(['user' => $this->profile($request->user())]);
    }

    private function profile(User $user): array
    {
        return $user->only(['id', 'name', 'email', 'phone', 'owned_ids', 'owned_documents', 'profile_setup_completed_at']);
    }

    public function setup(Request $request): JsonResponse
    {
        abort_unless($request->user()->is_active && $request->user()->role === 'resident', 403);
        $data = $request->validate([
            'ids' => ['present', 'array', 'max:8'],
            'ids.*' => ['string', 'distinct', Rule::in(['PhilSys ID', 'Passport ID', 'SSS ID', 'PhilHealth ID', 'UMID', 'TIN ID', 'Postal ID', 'Driver’s License'])],
            'documents' => ['present', 'array', 'max:7'],
            'documents.*' => ['string', 'distinct', Rule::in(['School ID', 'Birth Certificate', 'Certificate of Residency', 'Marriage Certificate', 'Barangay Clearance', 'Police Clearance', 'NBI Clearance'])],
        ]);
        $request->user()->forceFill([
            'owned_ids' => $data['ids'],
            'owned_documents' => $data['documents'],
            'profile_setup_completed_at' => now(),
        ])->save();

        return response()->json(['user' => $this->profile($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}
