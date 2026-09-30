<?php

namespace App\Http\Controllers;

use App\Models\Gallery;
use App\Models\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivateMediaController extends Controller
{
    public function show(Request $request, Media $media): StreamedResponse|RedirectResponse
    {
        if (! $request->user()) {
            return redirect()->route('filament.admin.auth.login');
        }

        Gate::authorize('view', $media);

        return Storage::disk($media->disk)->download($media->path);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'gallery_id' => ['required', 'integer', 'exists:galleries,id'],
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'alt_text' => ['required', 'string', 'max:255'],
        ]);
        $gallery = Gallery::query()->findOrFail($validated['gallery_id']);
        Gate::authorize('update', $gallery);
        $uploadedFile = $request->file('file');
        $path = $uploadedFile->store('private/gallery-media', 'local');

        try {
            Media::query()->create([
                'gallery_id' => $gallery->id,
                'organization_unit_id' => $gallery->organization_unit_id,
                'disk' => 'local',
                'path' => $path,
                'mime_type' => $uploadedFile->getMimeType(),
                'size' => $uploadedFile->getSize(),
                'alt_text' => $validated['alt_text'],
                'is_public' => false,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }

        return back();
    }
}
