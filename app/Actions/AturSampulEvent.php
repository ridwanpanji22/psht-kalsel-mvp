<?php

namespace App\Actions;

use App\Models\Event;
use Illuminate\Support\Facades\Storage;

/**
 * Persists an optional event cover to the private `local` disk and keeps the
 * file lifecycle failure-safe:
 *
 * - The new file has already been written to its final local path by the
 *   upload component before the record update runs.
 * - If the record update fails, the freshly written file is removed and the
 *   previous cover file is left untouched.
 * - The replaced/removed old local file is only deleted after the record
 *   update has succeeded.
 */
class AturSampulEvent
{
    public function apply(Event $event, int|string|null $newCoverPath, ?string $altText): Event
    {
        $oldCoverPath = $event->cover_path;
        $newCoverPath = filled($newCoverPath) ? (string) $newCoverPath : null;

        try {
            $event->update([
                'cover_path' => $newCoverPath,
                'cover_alt_text' => $newCoverPath === null ? null : $altText,
            ]);
        } catch (\Throwable $exception) {
            if ($newCoverPath !== null && $newCoverPath !== $oldCoverPath) {
                Storage::disk('local')->delete($newCoverPath);
            }

            throw $exception;
        }

        if ($oldCoverPath !== null && $oldCoverPath !== $newCoverPath) {
            Storage::disk('local')->delete($oldCoverPath);
        }

        return $event->refresh();
    }
}
