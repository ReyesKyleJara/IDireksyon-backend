<?php

use App\Http\Controllers\Admin\AdminAccountController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GovernmentIdController;
use App\Http\Controllers\Admin\GovernmentIdChecklistController;
use App\Http\Controllers\Admin\GovernmentIdRequirementSetController;
use App\Http\Controllers\Admin\GovernmentIdRequirementGroupController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\AgencyController;
use App\Http\Controllers\Admin\OfficeController;
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


        // Government IDs - Creation Wizard
        Route::controller(\App\Http\Controllers\Admin\GovernmentIdCreationWizardController::class)
            ->prefix('government-ids')
            ->name('government-ids.wizard.')
            ->group(function () {

                Route::post('wizard', 'store')
                    ->name('store');

                Route::get('{governmentId}/creation/{step}', 'show')
                    ->name('show');

                Route::post('{governmentId}/creation/cases/add', 'storeCase')
                    ->name('cases');

                Route::delete(
                    '{governmentId}/creation/cases/{requirementSet}',
                    'destroyCase'
                )
                    ->whereNumber('requirementSet')
                    ->name('cases.destroy');

                Route::post('{governmentId}/creation/{step}', 'save')
                    ->name('save');
            });


        // Government IDs
        Route::resource('government-ids', GovernmentIdController::class);


        // Government ID Checklists
        Route::post(
            'government-ids/{governmentId}/checklists',
            [GovernmentIdChecklistController::class, 'store']
        )
            ->name('government-ids.checklists.store');

        Route::put(
            'government-ids/{governmentId}/checklists/{checklist}',
            [GovernmentIdChecklistController::class, 'update']
        )
            ->whereNumber('checklist')
            ->name('government-ids.checklists.update');

        Route::delete(
            'government-ids/{governmentId}/checklists/{checklist}',
            [GovernmentIdChecklistController::class, 'destroy']
        )
            ->whereNumber('checklist')
            ->name('government-ids.checklists.destroy');


        // Government ID Requirement Sets
        Route::prefix('government-ids/{governmentId}/requirement-sets')
            ->name('government-ids.requirement-sets.')
            ->controller(GovernmentIdRequirementSetController::class)
            ->group(function () {

                Route::get('/', 'index')
                    ->name('index');

                Route::get('/create', 'create')
                    ->name('create');

                Route::post('/', 'store')
                    ->name('store');

                Route::get('/{requirementSet}/edit', 'edit')
                    ->whereNumber('requirementSet')
                    ->name('edit');

                Route::put('/{requirementSet}', 'update')
                    ->whereNumber('requirementSet')
                    ->name('update');

                Route::delete('/{requirementSet}', 'destroy')
                    ->whereNumber('requirementSet')
                    ->name('destroy');
            });


        // Government ID Requirement Groups
        Route::prefix(
            'government-ids/{governmentId}/requirement-sets/{requirementSet}/groups'
        )
            ->name('government-ids.requirement-sets.groups.')
            ->whereNumber('requirementSet')
            ->controller(GovernmentIdRequirementGroupController::class)
            ->group(function () {

                Route::get('/', 'index')
                    ->name('index');

                Route::get('/create', 'create')
                    ->name('create');

                Route::post('/', 'store')
                    ->name('store');

                Route::get('/{requirementGroup}/edit', 'edit')
                    ->whereNumber('requirementGroup')
                    ->name('edit');

                Route::put('/{requirementGroup}', 'update')
                    ->whereNumber('requirementGroup')
                    ->name('update');

                Route::delete('/{requirementGroup}', 'destroy')
                    ->whereNumber('requirementGroup')
                    ->name('destroy');
            });


        // Document Checklists
        Route::post(
            'documents/{document}/checklists',
            [\App\Http\Controllers\Admin\DocumentChecklistController::class, 'store']
        )
            ->name('documents.checklists.store');

        Route::put(
            'documents/{document}/checklists/{checklist}',
            [\App\Http\Controllers\Admin\DocumentChecklistController::class, 'update']
        )
            ->whereNumber('checklist')
            ->name('documents.checklists.update');

        Route::delete(
            'documents/{document}/checklists/{checklist}',
            [\App\Http\Controllers\Admin\DocumentChecklistController::class, 'destroy']
        )
            ->whereNumber('checklist')
            ->name('documents.checklists.destroy');


        // Documents
        Route::resource('documents', DocumentController::class);


        // Offices
        Route::resource('offices', OfficeController::class)
            ->only([
                'index',
                'create',
                'store',
                'edit',
                'update',
            ]);


        // Agencies
        Route::post(
            'agencies',
            [AgencyController::class, 'store']
        )
            ->name('agencies.store');


        // CMS Admin Accounts
        Route::resource('users', AdminAccountController::class)
            ->parameters([
                'users' => 'account',
            ])
            ->names([
                'index' => 'accounts.index',
                'create' => 'accounts.create',
                'store' => 'accounts.store',
                'edit' => 'accounts.edit',
                'update' => 'accounts.update',
            ])
            ->only([
                'index',
                'create',
                'store',
                'edit',
                'update',
            ])
            ->middleware('can:manage-admins');


        // Change Password
        Route::view(
            'change-password',
            'admin.accounts.change-password'
        )
            ->name('password.edit');


        // Audit Logs
        Route::get(
            'audit-logs',
            [AuditLogController::class, 'index']
        )
            ->middleware('can:manage-admins')
            ->name('audit-logs.index');
    });


require __DIR__.'/auth.php';