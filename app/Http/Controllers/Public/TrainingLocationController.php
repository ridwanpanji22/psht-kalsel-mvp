<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\TrainingLocation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class TrainingLocationController extends Controller
{
    public function index(): View
    {
        $locations = TrainingLocation::query()
            ->published()
            ->whereNotNull('verified_at')
            ->select(['id', 'slug', 'name', 'address', 'contact', 'schedule', 'latitude', 'longitude'])
            ->orderBy('name')
            ->get();

        return view('public.training-locations.index', ['locations' => $locations]);
    }

    public function show(string $slug): View
    {
        $location = $this->verifiedPublished()
            ->select(['id', 'slug', 'name', 'address', 'contact', 'schedule', 'latitude', 'longitude', 'organization_unit_id'])
            ->with('organizationUnit:id,name')
            ->where('slug', $slug)
            ->firstOrFail();

        return view('public.training-locations.show', ['location' => $location]);
    }

    /**
     * The exact published + verified predicate used by the public index; an
     * unverified, unpublished, missing, or slug-mismatched row never resolves
     * and there is no default fallback record.
     */
    private function verifiedPublished(): Builder
    {
        return TrainingLocation::query()
            ->published()
            ->whereNotNull('verified_at');
    }
}
