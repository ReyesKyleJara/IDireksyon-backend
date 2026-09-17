<?php

namespace App\Providers;

use App\Models\GovernmentId;
use App\Models\User;
use App\Models\Document;
use App\Observers\CmsAuditObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        GovernmentId::observe(CmsAuditObserver::class);
        Document::observe(CmsAuditObserver::class);
        User::observe(CmsAuditObserver::class);

        Gate::define('access-cms', fn (User $user) => $user->canAccessCms());
        Gate::define('manage-admins', fn (User $user) => $user->canManageAdmins());
    }
}