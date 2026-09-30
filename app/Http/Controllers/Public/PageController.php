<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(string $slug): View
    {
        $page = Page::query()
            ->published()
            ->select(['id', 'slug', 'title', 'body', 'organization_unit_id', 'published_at'])
            ->where('slug', $slug)
            ->firstOrFail();

        return view('public.pages.show', ['page' => $page]);
    }
}
