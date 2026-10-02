<?php

namespace App\Actions;

use App\Enums\PublicationStatus;
use App\Models\Media;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
 */
class PublishMediaToPortal
{
    public function publish(User $actor, Media $media, ?string $altText = null): Media
    {
        if ($media->is_public) {
            throw ValidationException::withMessages([
                'media' => 'Media sudah tayang di portal.',
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

        $sourceDisk = Storage::disk($media->disk);
        $sourceDiskName = $media->disk;
        $sourcePath = $media->path;

        if (! $sourceDisk->exists($sourcePath)) {
            throw ValidationException::withMessages([
                'media' => 'Berkas media tidak ditemukan pada penyimpanan privat.',
            ]);
        }

        $publicPath = $this->uniquePublicPath($sourcePath);
        $publicDisk = Storage::disk('public');

        $copied = false;

        try {
            $copied = $publicDisk->put($publicPath, $sourceDisk->get($sourcePath));
        } catch (Throwable $exception) {
            if ($copied && $publicDisk->exists($publicPath)) {
                $publicDisk->delete($publicPath);
            }

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
            $publicDisk->delete($publicPath);

            throw $exception;
        }

        Storage::disk($sourceDiskName)->delete($sourcePath);

        return $media->refresh();
    }

    private function uniquePublicPath(string $sourcePath): string
    {
        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);
        $base = 'public/gallery-media/'.now()->format('YmdHis').'-'.bin2hex(random_bytes(8));

        return $extension !== '' ? $base.'.'.$extension : $base;
    }
}
