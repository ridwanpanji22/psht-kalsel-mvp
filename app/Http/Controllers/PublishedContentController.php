<?php

namespace App\Http\Controllers;

use App\Actions\PublishContent;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PublishedContentController extends Controller
{
    public function store(Request $request, Page $page, PublishContent $publishContent): RedirectResponse
    {
        Gate::authorize('update', $page);

        $publishContent->submit($request->user(), $page);

        return back();
    }
}
