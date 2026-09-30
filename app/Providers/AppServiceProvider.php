<?php

namespace App\Providers;

use App\Contracts\FirebaseAuthentication;
use App\Models\ApprovalRequest;
use App\Models\Event;
use App\Models\Gallery;
use App\Models\Media;
use App\Models\Membership;
use App\Models\MembershipStatusHistory;
use App\Models\OrganizationPosition;
use App\Models\OrganizationUnit;
use App\Models\Page;
use App\Models\TrainingLocation;
use App\Models\UnitAssignment;
use App\Models\User;
use App\Policies\ApprovalRequestPolicy;
use App\Policies\EventPolicy;
use App\Policies\GalleryPolicy;
use App\Policies\MediaPolicy;
use App\Policies\MembershipPolicy;
use App\Policies\MembershipStatusHistoryPolicy;
use App\Policies\OrganizationPositionPolicy;
use App\Policies\OrganizationUnitPolicy;
use App\Policies\PagePolicy;
use App\Policies\TrainingLocationPolicy;
use App\Policies\UnitAssignmentPolicy;
use App\Policies\UserPolicy;
use App\Services\FirebaseService;
use Filament\Facades\Filament;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Factory;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FirebaseAuthentication::class, function ($app): FirebaseService {
            try {
                $factory = new Factory;

                $credentialsPath = config('services.firebase.credentials');

                if (is_string($credentialsPath) && $credentialsPath !== '' && file_exists($credentialsPath)) {
                    $factory = $factory->withServiceAccount($credentialsPath);
                }

                return new FirebaseService($factory->createAuth());
            } catch (Throwable) {
                // Credentials are not resolvable yet (e.g. local CLI); fall back to
                // lazy container resolution so the app does not fail to boot.
                return new FirebaseService;
            }
        });
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
        Gate::policy(UnitAssignment::class, UnitAssignmentPolicy::class);
        Gate::policy(MembershipStatusHistory::class, MembershipStatusHistoryPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        Filament::registerRenderHook(
            PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
            fn () => Blade::render(
                '<div class="text-center mt-4"><p class="text-sm text-gray-500">Atau, <a href="{{ route(\'auth.activate\') }}" class="text-primary-600 hover:underline">aktifkan undangan Anda</a>.</p></div>'
            )
        );
    }
}
