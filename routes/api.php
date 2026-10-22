<?php

use App\Http\Controllers\Api\BarangayController;
use App\Http\Controllers\Api\GovernmentIdApplicabilityController;
use App\Http\Controllers\Api\GovernmentIdController;
use App\Http\Controllers\Api\GovernmentIdSequenceController;
use App\Http\Controllers\Api\ResidentAuthController;
use App\Http\Controllers\Api\ResidentInventoryController;
use App\Http\Controllers\Api\ResidentRoadmapGoalController;
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

Route::get('/government-ids', [GovernmentIdController::class, 'index']);
Route::post('/government-ids/{governmentId}/applicability', GovernmentIdApplicabilityController::class);
Route::post('/government-ids/{governmentId}/sequence', GovernmentIdSequenceController::class);
Route::get('/government-ids/{governmentId}/content', [GovernmentIdController::class, 'content']);

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

        Route::get('roadmap-goals', [ResidentRoadmapGoalController::class, 'index']);
        Route::get('roadmap-goals/{governmentId}', [ResidentRoadmapGoalController::class, 'show']);
        Route::put('roadmap-goals/{governmentId}', [ResidentRoadmapGoalController::class, 'save']);
        Route::delete('roadmap-goals/{governmentId}', [ResidentRoadmapGoalController::class, 'destroy']);

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
