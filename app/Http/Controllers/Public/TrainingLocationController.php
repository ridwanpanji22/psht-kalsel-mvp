<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\TrainingLocation;
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
}
