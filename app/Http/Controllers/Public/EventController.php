<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(): View
    {
        $events = Event::query()
            ->published()
            ->select(['id', 'slug', 'title', 'description', 'starts_at', 'ends_at', 'venue'])
            ->orderBy('starts_at')
            ->paginate(12);

        return view('public.events.index', ['events' => $events]);
    }

    public function show(string $slug): View
    {
        $event = Event::query()
            ->published()
            ->select(['id', 'slug', 'title', 'description', 'starts_at', 'ends_at', 'venue'])
            ->where('slug', $slug)
            ->firstOrFail();

        return view('public.events.show', ['event' => $event]);
    }
}
