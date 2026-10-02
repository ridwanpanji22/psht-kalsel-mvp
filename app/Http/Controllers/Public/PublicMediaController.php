<?php

namespace App\Http\Controllers\Public;

use App\Enums\PublicationStatus;
use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves published gallery media through the application.
 *
 * Access is gated on the record's own public state (`is_public` + `public`
 * disk) plus a published parent album with a non-null `published_at`. This is
 * an application-discovery gate only: the underlying `/storage/...` URL of an
 * already-disclosed public file cannot be revoked.
 */
class PublicMediaController extends Controller
{
    public function __invoke(Media $media): StreamedResponse
    {
        abort_unless($media->is_public && $media->disk === 'public', 404);

        $gallery = $media->gallery;

        abort_unless($gallery !== null, 404);
        abort_unless(
            $gallery->status === PublicationStatus::Published && $gallery->published_at !== null,
            404,
        );

        return Storage::disk('public')->response($media->path);
    }
}
