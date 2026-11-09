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

Route::view('/privacy', 'legal.privacy')->name('privacy');
Route::view('/account-deletion', 'legal.account-deletion')->name('account-deletion');

Route::get('/dashboard', fn () => redirect()->route(auth()->user()->cmsHomeRoute()))
    ->middleware(['auth', 'can:access-cms'])
    ->name('dashboard');

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'auth.session', 'can:access-cms', \App\Http\Middleware\CheckCatalogRevision::class])
    ->group(function () {

        foreach (['documents' => 'document', 'government-ids' => 'governmentId'] as $resource => $parameter) {
            $controller = \App\Http\Controllers\Admin\CatalogSpecialCaseController::class;
            Route::get($resource.'/{'.$parameter.'}/special-cases/create', [$controller, $parameter.'Edit'])->name($resource.'.special-cases.create');
            Route::get($resource.'/{'.$parameter.'}/special-cases/{specialCase}/edit', [$controller, $parameter.'Edit'])->name($resource.'.special-cases.edit');
            Route::post($resource.'/{'.$parameter.'}/special-cases', [$controller, $parameter.'Save'])->name($resource.'.special-cases.save');
            Route::post($resource.'/{'.$parameter.'}/special-cases/action', [$controller, $parameter.'Action'])->name($resource.'.special-cases.action');
        }

        Route::post('documents/{document}/channels', [\App\Http\Controllers\Admin\CatalogChannelController::class, 'document'])->name('documents.channels');
        Route::post('government-ids/{governmentId}/channels', [\App\Http\Controllers\Admin\CatalogChannelController::class, 'governmentId'])->name('government-ids.channels');
        foreach (['documents' => 'document', 'government-ids' => 'governmentId'] as $resource => $parameter) {
            $controller = \App\Http\Controllers\Admin\CatalogChannelController::class;
            Route::get($resource.'/{'.$parameter.'}/request-options/create', [$controller, $parameter.'Edit'])->name($resource.'.request-options.create');
            Route::get($resource.'/{'.$parameter.'}/request-options/{pathId}/edit', [$controller, $parameter.'Edit'])->whereNumber('pathId')->name($resource.'.request-options.edit');
            Route::delete($resource.'/{'.$parameter.'}/request-options/{pathId}', [$controller, $parameter.'Delete'])->whereNumber('pathId')->name($resource.'.request-options.destroy');
        }

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


        // Document creation follows the same saved-step flow as Government IDs.
        Route::controller(\App\Http\Controllers\Admin\DocumentCreationWizardController::class)
            ->prefix('documents')->name('documents.wizard.')
            ->group(function () {
                Route::post('wizard', 'store')->name('store');
                Route::get('{document}/creation/{step}', 'show')->name('show');
                Route::post('{document}/creation/{step}', 'save')->name('save');
            });

        Route::controller(\App\Http\Controllers\Admin\GovernmentIdCreationWizardController::class)
            ->prefix('government-ids/{governmentId}/edit')->name('government-ids.edit-wizard.')
            ->group(function () {
                Route::get('{step}', 'edit')->name('show');
                Route::post('{step}', 'saveEdit')->name('save');
            });

        Route::controller(\App\Http\Controllers\Admin\DocumentCreationWizardController::class)
            ->prefix('documents/{document}/edit')->name('documents.edit-wizard.')
            ->group(function () {
                Route::get('{step}', 'edit')->name('show');
                Route::post('{step}', 'saveEdit')->name('save');
            });

        // Documents
        Route::resource('documents', DocumentController::class);

        // Permanent catalog deletion: the warning modal reads this first.
        Route::get('government-ids/{governmentId}/delete-impact', [GovernmentIdController::class, 'deleteImpact'])->name('government-ids.delete-impact');
        Route::get('documents/{document}/delete-impact', [DocumentController::class, 'deleteImpact'])->name('documents.delete-impact');

        // ID and document images
        Route::controller(\App\Http\Controllers\Admin\CatalogImageController::class)
            ->prefix('images')
            ->name('images.')
            ->group(function () {
                Route::post('{type}/{id}', 'update')->whereIn('type', ['government-ids', 'documents'])->whereNumber('id')->name('update');
                Route::delete('{type}/{id}', 'destroy')->whereIn('type', ['government-ids', 'documents'])->whereNumber('id')->name('destroy');
                Route::get('{type}/{id}/original', 'original')->whereIn('type', ['government-ids', 'documents'])->whereNumber('id')->name('original');
            });


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
