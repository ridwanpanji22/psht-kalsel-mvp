<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Support\SafeStoragePath;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams the private-at-rest cover of a published event.
 *
 * The route resolves an Event by its slug binding; only the event's own
 * `cover_path` is ever read. Draft/unpublished events, events without a
 * cover, missing files, unsafe persisted paths, and non-image content all
 * resolve as 404 — a caller can never supply a path. The response MIME type
 * is derived from the stored file's actual content, not from its name or the
 * request, and is served inline with `X-Content-Type-Options: nosniff`.
 */
class EventCoverController extends Controller
{
    /**
     * @var list<string>
     */
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __invoke(Event $event): StreamedResponse
    {
        // `published()` requires status published AND published_at non-null.
        abort_unless(
            Event::query()->published()->whereKey($event->getKey())->exists(),
            404,
        );

        $path = SafeStoragePath::normalize(
            (string) $event->cover_path,
            [SafeStoragePath::EVENT_COVER_PREFIX],
        );

        abort_if($path === null, 404);

        $disk = Storage::disk('local');

        abort_unless($disk->exists($path), 404);

        $mimeType = $disk->mimeType($path);

        abort_unless(is_string($mimeType) && in_array($mimeType, self::ALLOWED_MIME_TYPES, true), 404);

        // Inline filename only — never expose the private storage path.
        $filename = basename($path);

        return $disk->response($path, $filename, [
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
