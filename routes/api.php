<?php

use App\Http\Controllers\Api\BarangayController;
use App\Http\Controllers\Api\GovernmentIdController;
use App\Http\Controllers\Api\ResidentAuthController;
use App\Http\Controllers\Api\ResidentInventoryController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use Illuminate\Support\Facades\Route;

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

Route::get('/offices', [\App\Http\Controllers\Api\OfficeController::class, 'index']);

Route::get('/documents', [\App\Http\Controllers\Api\DocumentController::class, 'index']);
Route::get('/documents/{document}', [\App\Http\Controllers\Api\DocumentController::class, 'show']);

Route::get('/catalog-images/{file}', [\App\Http\Controllers\Api\CatalogImageController::class, 'show'])
    ->where('file', '[A-Za-z0-9]{40}\.jpg')
    ->name('catalog-images.show');

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
        ->middleware('throttle:5,1,auth-forgot-password:');

    Route::post('email-verification/send', [\App\Http\Controllers\Api\SignupEmailVerificationController::class, 'send'])
        ->middleware('throttle:5,1,signup-send:');
    Route::post('email-verification/verify', [\App\Http\Controllers\Api\SignupEmailVerificationController::class, 'verify'])
        ->middleware('throttle:20,1,signup-verify:');
    Route::post('register', [
        ResidentAuthController::class,
        'register',
    ])->middleware('throttle:5,1,auth-register:');

    Route::post('login', [
        ResidentAuthController::class,
        'login',
    ])->middleware('throttle:5,1,auth-login:');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('journeys', [\App\Http\Controllers\Api\ResidentJourneyController::class, 'index']);
        Route::post('journeys', [\App\Http\Controllers\Api\ResidentJourneyController::class, 'store']);
        Route::put('journeys/{journey}/resolution', [\App\Http\Controllers\Api\ResidentJourneyController::class, 'resolve'])->whereNumber('journey');
        Route::put('journeys/{journey}/completion', [\App\Http\Controllers\Api\ResidentJourneyController::class, 'complete'])->whereNumber('journey');

        Route::post('roadmap-plan', [\App\Http\Controllers\Api\ResidentRoadmapController::class, 'plan']);

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

        Route::post('account/delete', [
            ResidentAuthController::class,
            'destroyAccount',
        ])->middleware('throttle:5,1');

        Route::post('logout', [
            ResidentAuthController::class,
            'logout',
        ]);

        Route::get('requirement-preparation/{recordType}/{recordId}', [
            \App\Http\Controllers\Api\ResidentRequirementPreparationController::class, 'show',
        ])->whereNumber('recordId');
        Route::put('requirement-preparation/{recordType}/{recordId}', [
            \App\Http\Controllers\Api\ResidentRequirementPreparationController::class, 'update',
        ])->whereNumber('recordId');

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
