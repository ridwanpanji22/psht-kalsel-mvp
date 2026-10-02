<?php

namespace App\Actions;

use App\Models\Event;
use App\Support\SafeStoragePath;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

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
 *
 * Any path accepted here must be a traversal-safe `private/event-covers/`
 * relative path; unsafe paths are rejected before they can be persisted or
 * deleted, so a crafted value cannot reach another private file.
 */
class AturSampulEvent
{
    public function apply(Event $event, int|string|null $rawNewCoverPath, ?string $altText): Event
    {
        $oldCoverPath = $event->cover_path;

        if (filled($rawNewCoverPath)) {
            $newCoverPath = SafeStoragePath::normalize(
                (string) $rawNewCoverPath,
                [SafeStoragePath::EVENT_COVER_PREFIX],
            );

            if ($newCoverPath === null) {
                throw ValidationException::withMessages([
                    'cover_path' => 'Lokasi berkas sampul tidak valid.',
                ]);
            }
        } else {
            $newCoverPath = null;
        }

        try {
            $event->update([
                'cover_path' => $newCoverPath,
                'cover_alt_text' => $newCoverPath === null ? null : $altText,
            ]);
        } catch (Throwable $exception) {
            if ($newCoverPath !== null && $newCoverPath !== $oldCoverPath) {
                $this->deleteQuietly($newCoverPath);
            }

            throw $exception;
        }

        if ($oldCoverPath !== null && $oldCoverPath !== $newCoverPath) {
            $this->deletePreviousCoverQuietly($event, (string) $oldCoverPath);
        }

        return $event->refresh();
    }

    /**
     * Delete a persisted previous cover only when it is a traversal-safe
     * event-cover path. A malformed value (traversal, backslash, null byte,
     * absolute path, dot segment) must never reach the filesystem; it is
     * reported and skipped so the record update still proceeds safely.
     */
    private function deletePreviousCoverQuietly(Event $event, string $oldCoverPath): void
    {
        if (! SafeStoragePath::isSafe($oldCoverPath, [SafeStoragePath::EVENT_COVER_PREFIX])) {
            Log::error('Lokasi sampul lama tidak aman dan dilewati penghapusannya.', [
                'event_id' => $event->getKey(),
                'path' => $oldCoverPath,
            ]);

            return;
        }

        $this->deleteQuietly($oldCoverPath);
    }

    private function deleteQuietly(string $path): void
    {
        try {
            $disk = Storage::disk('local');

            if (! $disk->delete($path) || $disk->exists($path)) {
                Log::error('Berkas sampul privat masih ada setelah operasi.', ['path' => $path]);
            }
        } catch (Throwable $exception) {
            Log::error('Gagal menghapus berkas sampul privat.', [
                'path' => $path,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
