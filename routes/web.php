<?php

use App\Http\Controllers\Admin\AdminAccountController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GovernmentIdController;
use App\Http\Controllers\Admin\DocumentController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

Route::get('/dashboard', fn () => redirect()->route(auth()->user()->cmsHomeRoute()))
    ->middleware(['auth', 'can:access-cms'])
    ->name('dashboard');

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'auth.session', 'can:access-cms'])
    ->group(function () {

        // Dashboard
        Route::get('/', [DashboardController::class, 'index'])
            ->name('dashboard');


        // Government IDs
        Route::resource('government-ids', GovernmentIdController::class)
            ->except(['show']);

        Route::resource('documents', DocumentController::class)
            ->except(['show']);


        // CMS Admin Accounts
        Route::resource('users', AdminAccountController::class)
            ->parameters(['users' => 'account'])
            ->names([
                'index' => 'accounts.index',
                'create' => 'accounts.create',
                'store' => 'accounts.store',
                'edit' => 'accounts.edit',
                'update' => 'accounts.update',
            ])
            ->only(['index', 'create', 'store', 'edit', 'update'])
            ->middleware('can:manage-admins');


        // Change Password
        Route::view(
            'change-password',
            'admin.accounts.change-password'
        )->name('password.edit');


        // Audit Logs
        Route::get(
            'audit-logs',
            [AuditLogController::class, 'index']
        )
            ->middleware('can:manage-admins')
            ->name('audit-logs.index');
    });

require __DIR__.'/auth.php';