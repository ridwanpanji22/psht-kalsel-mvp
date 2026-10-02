<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventController extends Controller
{
    /**
     * @var list<string>
     */
    private const PERIODS = ['upcoming', 'past', 'all'];

    public function index(Request $request): View
    {
        $period = $this->resolvePeriod($request->query('period'));

        $events = Event::query()
            ->published()
            ->select(['id', 'slug', 'title', 'description', 'starts_at', 'ends_at', 'venue', 'cover_path', 'cover_alt_text'])
            ->when($period === 'upcoming', fn (Builder $query) => $query->where('starts_at', '>=', now())->orderBy('starts_at')->orderBy('id'))
            ->when($period === 'past', fn (Builder $query) => $query->where('starts_at', '<', now())->orderByDesc('starts_at')->orderByDesc('id'))
            ->when($period === 'all', fn (Builder $query) => $query->orderBy('starts_at')->orderBy('id'))
            ->paginate(12)
            ->withQueryString();

        return view('public.events.index', ['events' => $events, 'period' => $period]);
    }

    public function show(string $slug): View
    {
        $event = Event::query()
            ->published()
            ->select(['id', 'slug', 'title', 'description', 'starts_at', 'ends_at', 'venue', 'cover_path', 'cover_alt_text'])
            ->where('slug', $slug)
            ->firstOrFail();

        return view('public.events.show', ['event' => $event]);
    }

    /**
     * Only the allowlisted `upcoming|past|all` values are honored; anything
     * else (missing or invalid) falls back to the default `upcoming`.
     */
    private function resolvePeriod(mixed $period): string
    {
        return is_string($period) && in_array($period, self::PERIODS, true) ? $period : 'upcoming';
    }
}
