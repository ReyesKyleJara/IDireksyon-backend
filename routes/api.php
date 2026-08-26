<?php

use App\Http\Controllers\Api\GovernmentIDController;
use App\Http\Controllers\Api\GovernmentOfficeController;
use App\Http\Controllers\Api\RequirementController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::apiResource('users', UserController::class);
Route::apiResource('government-ids', GovernmentIDController::class);
Route::apiResource('requirements', RequirementController::class);
Route::apiResource('government-offices', GovernmentOfficeController::class);
