<?php

namespace App\Providers;

use App\Models\GovernmentId;
use App\Models\User;
use App\Models\Document;
use App\Models\Office;
use App\Observers\CmsAuditObserver;
use App\Security\AuthEventLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        Office::observe(CmsAuditObserver::class);
        User::observe(CmsAuditObserver::class);

        Gate::define('access-cms', fn (User $user) => $user->canAccessCms());
        Gate::define('manage-admins', fn (User $user) => $user->canManageAdmins());
        Gate::define('delete-catalog-records', fn (User $user) => $user->canDeleteCatalogRecords());

        // Only CMS forms use the default; resident forms set their own rule.
        Password::defaults(fn () => Password::min(12));

        Event::listen(Login::class, [AuthEventLogger::class, 'login']);
        Event::listen(Failed::class, [AuthEventLogger::class, 'failed']);
        Event::listen(Lockout::class, [AuthEventLogger::class, 'lockout']);
    }
}