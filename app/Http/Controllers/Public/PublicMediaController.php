<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicMediaController extends Controller
{
    public function __invoke(Media $media): StreamedResponse
    {
        abort_unless(
            $media->is_public
                && $media->disk === 'public'
                && $media->gallery?->status->value === 'published',
            404,
        );

        return Storage::disk('public')->response($media->path);
    }
}
