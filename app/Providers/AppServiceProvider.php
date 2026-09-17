<?php

namespace App\Providers;

use App\Models\Agency;
use App\Models\Barangay;
use App\Models\CatalogFee;
use App\Models\Category;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\Level;
use App\Models\Office;
use App\Models\OfficeHour;
use App\Models\Requirement;
use App\Models\RequirementGroup;
use App\Models\User;
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
        foreach ([RequirementGroup::class, CatalogFee::class, GovernmentId::class, Document::class, Requirement::class, Level::class, Category::class, Agency::class, Barangay::class, Office::class, OfficeHour::class, User::class] as $model) {
            $model::observe(CmsAuditObserver::class);
        }
        Gate::define('manage-reference-data', fn (User $user) => $user->canManageAdmins());
        Gate::define('access-cms', fn (User $user) => $user->canAccessCms());
        Gate::define('manage-admins', fn (User $user) => $user->canManageAdmins());
    }
}
