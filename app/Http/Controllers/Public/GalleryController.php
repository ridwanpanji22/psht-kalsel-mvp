<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use Illuminate\View\View;

class GalleryController extends Controller
{
    public function index(): View
    {
        $galleries = Gallery::query()
            ->published()
            ->select(['id', 'slug', 'title', 'description'])
            ->with(['media' => fn ($query) => $query
                ->where('is_public', true)
                ->where('disk', 'public')
                ->select(['id', 'gallery_id', 'path', 'alt_text'])])
            ->orderByDesc('published_at')
            ->paginate(12);

        return view('public.galleries.index', ['galleries' => $galleries]);
    }
}
