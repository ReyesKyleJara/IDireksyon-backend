<?php

use App\Http\Controllers\Api\GovernmentIdController;
use App\Http\Controllers\Api\ResidentAuthController;
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
| Government ID API
|--------------------------------------------------------------------------
*/

Route::get('/government-ids', [GovernmentIdController::class, 'index']);
Route::get('/government-ids/{governmentId}', [GovernmentIdController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Resident Authentication API
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    Route::post('register', [ResidentAuthController::class, 'register'])
        ->middleware('throttle:5,1');

    Route::post('login', [ResidentAuthController::class, 'login'])
        ->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [ResidentAuthController::class, 'me']);
        Route::post('profile-setup', [ResidentAuthController::class, 'setup']);
        Route::post('account', [ResidentAuthController::class, 'updateAccount'])->middleware('throttle:5,1');
        Route::post('password', [ResidentAuthController::class, 'updatePassword'])->middleware('throttle:5,1');
        Route::post('logout', [ResidentAuthController::class, 'logout']);
    });
});
