<?php

namespace App\Http\Controllers\Public;

use App\Enums\GalleryCategory;
use App\Http\Controllers\Controller;
use App\Models\Gallery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GalleryController extends Controller
{
    public function index(Request $request): View
    {
        $category = $this->resolveCategory($request->query('category'));

        $galleries = Gallery::query()
            ->published()
            ->select(['id', 'slug', 'title', 'description', 'category', 'published_at'])
            ->when(
                $category !== 'all',
                // A specific category never includes legacy albums with a null
                // category; those only appear under `all`.
                fn (Builder $query) => $query->where('category', $category),
            )
            ->with(['media' => fn ($query) => $query
                ->where('is_public', true)
                ->where('disk', 'public')
                ->select(['id', 'gallery_id', 'path', 'alt_text', 'sort_order'])])
            ->orderByDesc('published_at')
            ->paginate(12)
            ->withQueryString();

        return view('public.galleries.index', ['galleries' => $galleries, 'category' => $category]);
    }

    /**
     * Only the allowlisted `all|latihan|kegiatan|pembinaan` values are honored;
     * anything else (missing or invalid) falls back to the default `all`.
     */
    private function resolveCategory(mixed $category): string
    {
        if (! is_string($category)) {
            return 'all';
        }

        return GalleryCategory::tryFrom($category)?->value ?? 'all';
    }
}
