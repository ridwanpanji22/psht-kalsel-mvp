<?php

namespace App\Providers;

use App\Models\ApprovalRequest;
use App\Models\Membership;
use App\Models\OrganizationPosition;
use App\Models\OrganizationUnit;
use App\Policies\ApprovalRequestPolicy;
use App\Policies\MembershipPolicy;
use App\Policies\OrganizationPositionPolicy;
use App\Policies\OrganizationUnitPolicy;
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
        Gate::policy(Membership::class, MembershipPolicy::class);
        Gate::policy(ApprovalRequest::class, ApprovalRequestPolicy::class);
        Gate::policy(OrganizationUnit::class, OrganizationUnitPolicy::class);
        Gate::policy(OrganizationPosition::class, OrganizationPositionPolicy::class);
    }
}
