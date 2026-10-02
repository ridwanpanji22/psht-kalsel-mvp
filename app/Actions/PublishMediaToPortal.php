<?php

namespace App\Actions;

use App\Enums\PublicationStatus;
use App\Models\Media;
use App\Models\User;
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
 * Only records that are currently backed by the private `local` disk, live
 * under the gallery-media prefix, and whose file content is a JPEG/PNG/WebP
 * image may be published. This prevents a client-tampered path, a record
 * already on the public disk, or a hidden (already-disclosed) public file
 * from being republished or relocated.
 */
class PublishMediaToPortal
{
    private const LOCAL_DISK = 'local';

    private const LOCAL_PREFIX = 'private/gallery-media/';

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

        if ($media->disk !== self::LOCAL_DISK || ! str_starts_with((string) $media->path, self::LOCAL_PREFIX)) {
            throw ValidationException::withMessages([
                'media' => 'Hanya media pada penyimpanan privat album yang dapat diterbitkan.',
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
        $sourcePath = (string) $media->path;

        if (! $sourceDisk->exists($sourcePath)) {
            throw ValidationException::withMessages([
                'media' => 'Berkas media tidak ditemukan pada penyimpanan privat.',
            ]);
        }

        if (! in_array($sourceDisk->mimeType($sourcePath), self::ALLOWED_MIME_TYPES, true)) {
            throw ValidationException::withMessages([
                'media' => 'Berkas media harus berupa gambar JPEG, PNG, atau WebP.',
            ]);
        }

        $publicPath = $this->uniquePublicPath($sourcePath);
        $publicDisk = Storage::disk('public');

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
                    'disk' => 'public',
                    'path' => $publicPath,
                    'alt_text' => $altText,
                    'is_public' => true,
                ]);
            });
        } catch (Throwable $exception) {
            // The DB write failed; remove the freshly copied public file so the
            // state flips atomically. The public file is new, so deleting it is
            // always safe and never removes a previously disclosed asset.
            if (! $publicDisk->delete($publicPath) || $publicDisk->exists($publicPath)) {
                Log::error('Gagal membersihkan berkas publik setelah kegagalan basis data.', [
                    'path' => $publicPath,
                ]);
            }

            throw $exception;
        }

        // The database now reflects a public record; the local file is no longer
        // referenced. Deleting it is a best-effort cleanup after commit — a
        // failure here must be reported loudly but never rolls back the public
        // state or removes the public file.
        try {
            $sourceDisk->delete($sourcePath);

            if ($sourceDisk->exists($sourcePath)) {
                Log::error('Berkas privat media masih ada setelah penerbitan.', [
                    'path' => $sourcePath,
                ]);
            }
        } catch (Throwable $exception) {
            Log::error('Gagal menghapus berkas privat media setelah penerbitan.', [
                'path' => $sourcePath,
                'exception' => $exception->getMessage(),
            ]);
        }

        return $media->refresh();
    }

    private function compensate(FilesystemAdapter $publicDisk, string $publicPath, Throwable $original): void
    {
        try {
            $deleted = $publicDisk->delete($publicPath);
        } catch (Throwable $cleanupException) {
            Log::error('Gagal membersihkan berkas publik setelah kegagalan salin.', [
                'path' => $publicPath,
                'original' => $original->getMessage(),
                'cleanup' => $cleanupException->getMessage(),
            ]);

            return;
        }

        if (! $deleted || $publicDisk->exists($publicPath)) {
            Log::error('Berkas publik masih ada setelah kegagalan salin.', [
                'path' => $publicPath,
                'original' => $original->getMessage(),
            ]);
        }
    }

    private function uniquePublicPath(string $sourcePath): string
    {
        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);
        $base = self::PUBLIC_PREFIX.now()->format('YmdHis').'-'.bin2hex(random_bytes(8));

        return $extension !== '' ? $base.'.'.$extension : $base;
    }
}
