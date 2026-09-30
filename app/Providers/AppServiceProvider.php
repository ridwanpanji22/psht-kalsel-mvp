<?php

namespace App\Providers;

use App\Models\ApprovalRequest;
use App\Models\Event;
use App\Models\Gallery;
use App\Models\Media;
use App\Models\Membership;
use App\Models\OrganizationPosition;
use App\Models\OrganizationUnit;
use App\Models\Page;
use App\Models\TrainingLocation;
use App\Policies\ApprovalRequestPolicy;
use App\Policies\EventPolicy;
use App\Policies\GalleryPolicy;
use App\Policies\MediaPolicy;
use App\Policies\MembershipPolicy;
use App\Policies\OrganizationPositionPolicy;
use App\Policies\OrganizationUnitPolicy;
use App\Policies\PagePolicy;
use App\Policies\TrainingLocationPolicy;
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
        Gate::policy(Event::class, EventPolicy::class);
        Gate::policy(Gallery::class, GalleryPolicy::class);
        Gate::policy(Media::class, MediaPolicy::class);
        Gate::policy(Page::class, PagePolicy::class);
        Gate::policy(TrainingLocation::class, TrainingLocationPolicy::class);
    }
}
