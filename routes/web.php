<?php

use App\Http\Controllers\Admin\AdminAccountController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CatalogStructureController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\GovernmentIdController;
use App\Http\Controllers\Admin\ReferenceController;
use App\Http\Controllers\Admin\RequirementController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));
Route::get('/dashboard', fn () => redirect()->route(auth()->user()->cmsHomeRoute()))
    ->middleware(['auth', 'can:access-cms'])->name('dashboard');

Route::prefix('admin')->name('admin.')->middleware(['auth', 'auth.session', 'can:access-cms'])->group(function () {
    Route::resource('users', AdminAccountController::class)
        ->parameters(['users' => 'account'])
        ->names(['index' => 'accounts.index', 'create' => 'accounts.create', 'store' => 'accounts.store', 'edit' => 'accounts.edit', 'update' => 'accounts.update'])
        ->only(['index', 'create', 'store', 'edit', 'update'])
        ->middleware('can:manage-admins');
    Route::get('/', [DashboardController::class, 'index'])->middleware('can:manage-admins')->name('dashboard');
    Route::view('change-password', 'admin.accounts.change-password')->name('password.edit');
    Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('can:manage-admins')->name('audit-logs.index');
    Route::get('accounts', fn () => redirect()->route('admin.accounts.index'))->middleware('can:manage-admins')->name('accounts.legacy');

    Route::resource('government-ids', GovernmentIdController::class);

    Route::post(
        'government-ids/{governmentId}/requirements',
        [GovernmentIdController::class, 'addRequirement']
    )->name('government-ids.requirements.store');

    Route::delete('government-ids/{governmentId}/requirements/{requirement}',
        [GovernmentIdController::class, 'removeRequirement'])->name('government-ids.requirements.destroy');

    Route::resource('documents', DocumentController::class);
    Route::post('documents/{document}/requirements',
        [DocumentController::class, 'addRequirement'])->name('documents.requirements.store');
    Route::delete('documents/{document}/requirements/{requirement}',
        [DocumentController::class, 'removeRequirement'])->name('documents.requirements.destroy');

    foreach (['government-ids' => GovernmentIdController::class, 'documents' => DocumentController::class] as $catalog => $controller) {
        Route::put($catalog.'/{id}/requirements/{requirement}', [$controller, 'updateRequirement'])->name($catalog.'.requirements.update');
    }
    Route::prefix('{catalog}/{id}')->where(['catalog' => 'government-ids|documents', 'id' => '[0-9]+'])->name('catalog.')->group(function () {
        Route::post('groups', [CatalogStructureController::class, 'saveGroup'])->name('groups.store');
        Route::put('groups/{group}', [CatalogStructureController::class, 'saveGroup'])->name('groups.update');
        Route::delete('groups/{group}', [CatalogStructureController::class, 'deleteGroup'])->name('groups.destroy');
        Route::post('fees', [CatalogStructureController::class, 'saveFee'])->name('fees.store');
        Route::put('fees/{fee}', [CatalogStructureController::class, 'saveFee'])->name('fees.update');
        Route::delete('fees/{fee}', [CatalogStructureController::class, 'deleteFee'])->name('fees.destroy');
    });

    Route::resource('requirements', RequirementController::class)->only(['index', 'create', 'store'])->middleware('can:manage-admins');
    Route::get('{type}', fn (string $type) => redirect()->route('admin.references.index', $type))
        ->where('type', 'levels|categories|agencies|barangays|offices')->middleware('can:manage-reference-data')->name('references.alias');
    Route::prefix('reference/{type}')->where(['type' => 'levels|categories|agencies|barangays|offices'])->name('references.')->middleware('can:manage-reference-data')->group(function () {
        Route::get('/', [ReferenceController::class, 'index'])->name('index');
        Route::get('/create', [ReferenceController::class, 'create'])->name('create');
        Route::post('/', [ReferenceController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [ReferenceController::class, 'edit'])->name('edit');
        Route::put('/{id}', [ReferenceController::class, 'update'])->name('update');
        Route::delete('/{id}', [ReferenceController::class, 'destroy'])->name('destroy');
    });
});

require __DIR__.'/auth.php';
