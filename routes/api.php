<?php

use App\Http\Controllers\Api\BarangayController;
use App\Http\Controllers\Api\GovernmentIdController;
use App\Http\Controllers\Api\MapController;
use App\Http\Controllers\Api\ResidentAuthController;
use App\Http\Controllers\Api\ResidentInventoryController;
use App\Http\Controllers\Api\SignupEmailVerificationController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use Illuminate\Support\Facades\Route;

Route::prefix('maps')->middleware('throttle:30,1')->group(function () {
    Route::post('search', [MapController::class, 'search']);
    Route::post('route', [MapController::class, 'route']);
});

/*
|--------------------------------------------------------------------------
| Health Check
|--------------------------------------------------------------------------
*/

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'IDireksyon backend connected',
    ]);
});

/*
|--------------------------------------------------------------------------
| Public Reference APIs
|--------------------------------------------------------------------------
*/

Route::get('/barangays', [BarangayController::class, 'index']);

Route::get('/government-ids', [GovernmentIdController::class, 'index']);
Route::get('/government-ids/{governmentId}', [
    GovernmentIdController::class,
    'show',
]);

/*
|--------------------------------------------------------------------------
| Resident Authentication API
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    Route::post('forgot-password', [PasswordResetLinkController::class, 'sendApi'])
        ->middleware('throttle:5,1');
    Route::post('email-verification/send', [SignupEmailVerificationController::class, 'send'])
        ->middleware('throttle:5,1');
    Route::post('email-verification/verify', [SignupEmailVerificationController::class, 'verify'])
        ->middleware('throttle:10,1');

    Route::post('register', [
        ResidentAuthController::class,
        'register',
    ])->middleware('throttle:5,1');

    Route::post('login', [
        ResidentAuthController::class,
        'login',
    ])->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [
            ResidentAuthController::class,
            'me',
        ]);

        Route::post('profile-setup', [
            ResidentAuthController::class,
            'setup',
        ]);

        Route::post('account', [
            ResidentAuthController::class,
            'updateAccount',
        ])->middleware('throttle:5,1');

        Route::post('password', [
            ResidentAuthController::class,
            'updatePassword',
        ])->middleware('throttle:5,1');

        Route::post('logout', [
            ResidentAuthController::class,
            'logout',
        ]);

        Route::get('inventory', [
            ResidentInventoryController::class,
            'show',
        ]);

        Route::put('inventory', [
            ResidentInventoryController::class,
            'update',
        ]);
    });
});
