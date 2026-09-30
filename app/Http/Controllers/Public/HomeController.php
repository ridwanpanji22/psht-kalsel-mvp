<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Gallery;
use App\Models\Page;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('public.home', [
            'pages' => Page::query()
                ->published()
                ->select(['id', 'slug', 'title', 'kind'])
                ->orderBy('title')
                ->limit(6)
                ->get(),
            'events' => Event::query()
                ->published()
                ->select(['id', 'slug', 'title', 'starts_at', 'venue'])
                ->where('starts_at', '>=', now())
                ->orderBy('starts_at')
                ->limit(4)
                ->get(),
            'galleries' => Gallery::query()
                ->published()
                ->select(['id', 'slug', 'title', 'description'])
                ->orderByDesc('published_at')
                ->limit(4)
                ->get(),
        ]);
    }
}
