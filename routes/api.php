<?php

use App\Http\Controllers\Api\GovernmentIdController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'IDireksyon backend connected',
    ]);
});

Route::get('/government-ids', [GovernmentIdController::class, 'index']);
Route::get('/government-ids/{governmentId}', [GovernmentIdController::class, 'show']);