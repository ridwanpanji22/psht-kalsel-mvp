<?php

namespace App\Actions;

use App\Enums\PublicationStatus;
use App\Models\Media;
use App\Models\User;
use App\Support\SafeStoragePath;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * One-way transition of a single Media record from the private `local`
 * disk to the public `public` disk. It is only allowed after the owning
 * Gallery has been published (status published with a published_at).
 *
 * The public file is copied to a unique path first, the record is flipped
 * inside a database transaction, and only after that transaction commits is
 * the private local file removed. If the database write fails, the freshly
 * copied public file is deleted so no orphaned public asset survives.
 *
 * Only records that are currently backed by the private `local` disk and
 * whose path is a traversal-safe `private/gallery-media/` relative path whose
 * content is a JPEG/PNG/WebP image may be published. This prevents a
 * client-tampered path (including `../` traversal), a record already on the
 * public disk, or a hidden (already-disclosed) public file from being
 * republished or relocated.
 */
class PublishMediaToPortal
{
    private const LOCAL_DISK = 'local';

    private const PUBLIC_DISK = 'public';

    private const PUBLIC_PREFIX = 'public/gallery-media/';

    /**
     * @var list<string>
     */
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function publish(User $actor, Media $media, ?string $altText = null): Media
    {
        if ($media->is_public) {
            throw ValidationException::withMessages([
                'media' => 'Media sudah tayang di portal.',
            ]);
        }

        if ($media->disk !== self::LOCAL_DISK) {
            throw ValidationException::withMessages([
                'media' => 'Hanya media pada penyimpanan privat album yang dapat diterbitkan.',
            ]);
        }

        $sourcePath = SafeStoragePath::normalize(
            (string) $media->path,
            [SafeStoragePath::GALLERY_MEDIA_PREFIX],
        );

        if ($sourcePath === null) {
            throw ValidationException::withMessages([
                'media' => 'Lokasi berkas media tidak valid.',
            ]);
        }

        $altText = filled($altText) ? (string) $altText : $media->alt_text;

        if (blank($altText)) {
            throw ValidationException::withMessages([
                'alt_text' => 'Teks alternatif wajib diisi sebelum media diterbitkan.',
            ]);
        }

        $gallery = $media->gallery()->first();

        if (! $gallery) {
            throw ValidationException::withMessages([
                'media' => 'Media tidak memiliki album induk.',
            ]);
        }

        if ($gallery->status !== PublicationStatus::Published || $gallery->published_at === null) {
            throw ValidationException::withMessages([
                'gallery' => 'Album harus diterbitkan sebelum media dapat tayang.',
            ]);
        }

        $sourceDisk = Storage::disk(self::LOCAL_DISK);

        if (! $sourceDisk->exists($sourcePath)) {
            throw ValidationException::withMessages([
                'media' => 'Berkas media tidak ditemukan pada penyimpanan privat.',
            ]);
        }

        try {
            $mimeType = $sourceDisk->mimeType($sourcePath);
        } catch (Throwable $exception) {
            Log::error('Gagal membaca metadata berkas media privat.', [
                'path' => $sourcePath,
                'exception' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'media' => 'Berkas media tidak dapat dibaca.',
            ]);
        }

        if (! in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw ValidationException::withMessages([
                'media' => 'Berkas media harus berupa gambar JPEG, PNG, atau WebP.',
            ]);
        }

        $publicPath = $this->uniquePublicPath($sourcePath);
        $publicDisk = Storage::disk(self::PUBLIC_DISK);

        try {
            $copied = $publicDisk->put($publicPath, $sourceDisk->get($sourcePath));
        } catch (Throwable $exception) {
            $this->compensate($publicDisk, $publicPath, $exception);

            throw $exception;
        }

        if ($copied === false) {
            throw ValidationException::withMessages([
                'media' => 'Berkas media gagal disalin ke penyimpanan publik.',
            ]);
        }

        try {
            DB::transaction(function () use ($media, $publicPath, $altText): void {
                $media->update([
                    'disk' => self::PUBLIC_DISK,
                    'path' => $publicPath,
                    'alt_text' => $altText,
                    'is_public' => true,
                ]);
            });
        } catch (Throwable $exception) {
            // The DB write failed; remove the freshly copied public file so the
            // state flips atomically. The public file is new, so deleting it is
            // always safe and never removes a previously disclosed asset. A
            // cleanup failure is reported but must not mask the DB exception.
            $this->reportCleanupFailure(
                fn () => $publicDisk->delete($publicPath) && ! $publicDisk->exists($publicPath),
                $publicPath,
                'Gagal membersihkan berkas publik setelah kegagalan basis data.',
                $exception,
            );

            throw $exception;
        }

        // The database now reflects a public record; the local file is no longer
        // referenced. Deleting it is a best-effort cleanup after commit — a
        // failure here must be reported loudly but never rolls back the public
        // state or removes the public file.
        $this->reportCleanupFailure(
            fn () => $sourceDisk->delete($sourcePath) && ! $sourceDisk->exists($sourcePath),
            $sourcePath,
            'Gagal menghapus berkas privat media setelah penerbitan.',
            null,
        );

        return $media->refresh();
    }

    private function compensate(FilesystemAdapter $publicDisk, string $publicPath, Throwable $original): void
    {
        $this->reportCleanupFailure(
            fn () => $publicDisk->delete($publicPath) && ! $publicDisk->exists($publicPath),
            $publicPath,
            'Gagal membersihkan berkas publik setelah kegagalan salin.',
            $original,
        );
    }

    /**
     * Run a cleanup operation, reporting (never masking) any failure. The
     * `$operation` closure returns true when the target path is confirmed
     * gone. Thrown cleanup exceptions are logged with context and swallowed;
     * the caller's original exception is always the one that propagates.
     */
    private function reportCleanupFailure(\Closure $operation, string $path, string $message, ?Throwable $original): void
    {
        try {
            if ($operation()) {
                return;
            }
        } catch (Throwable $cleanupException) {
            Log::error($message, [
                'path' => $path,
                'original' => $original?->getMessage(),
                'cleanup' => $cleanupException->getMessage(),
            ]);

            return;
        }

        Log::error($message, [
            'path' => $path,
            'original' => $original?->getMessage(),
        ]);
    }

    private function uniquePublicPath(string $sourcePath): string
    {
        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);
        $base = self::PUBLIC_PREFIX.now()->format('YmdHis').'-'.bin2hex(random_bytes(8));

        return $extension !== '' ? $base.'.'.$extension : $base;
    }
}
