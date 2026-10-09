<?php

namespace Database\Seeders;

use App\Enums\PublicationStatus;
use App\Models\Event;
use App\Models\Gallery;
use App\Models\Media;
use App\Support\SafeStoragePath;
use Database\Seeders\Support\DemoSeedContext;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Seeds the local media fixtures and the gallery/event storage that backs the
 * demo dataset.
 *
 * Every gallery receives exactly four deterministic media rows. Files are
 * written to the private `local` disk first, then, for the published subset,
 * copied into the `public` disk and the row is flipped to the public
 * contract (`disk = public`, `is_public = true`, path under
 * `public/gallery-media/`) — reproducing the final persisted state the
 * Filament publish action produces without requiring a real authenticated
 * actor. Private rows keep their `local` file and `is_public = false`.
 *
 * Event cover paths recorded by {@see DemoPublicationSeeder} are materialised
 * as real private image files so the `/kegiatan/{slug}/cover` route can stream
 * them. Only JPEG/PNG/WebP fixtures are used; no video rows or files exist.
 *
 * All work happens inside a single transaction and every write goes through
 * `Storage::disk(...)`, so a rerun simply overwrites the same deterministic
 * paths and updates the same natural-keyed rows instead of duplicating them.
 */
class DemoMediaSeeder extends Seeder
{
    /** Exactly four media rows per gallery. */
    public const MEDIA_PER_GALLERY = 4;

    /** Private gallery media disk. */
    private const LOCAL_DISK = 'local';

    /** Public gallery media disk. */
    private const PUBLIC_DISK = 'public';

    /** Checked-in PNG fixtures, cycled deterministically. */
    private const PLACEHOLDER_ASSETS = [
        'images/demo/demo-placeholder-1.png',
        'images/demo/demo-placeholder-2.png',
        'images/demo/demo-placeholder-3.png',
    ];

    /** Checked-in WebP reused for selected rows without mutating the source. */
    private const HERO_ASSET = 'images/hero-martial-arts-poster.webp';

    /** @var list<string> */
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function run(DemoSeedContext $context): void
    {
        DB::transaction(function (): void {
            $this->seedGalleryMedia();
            $this->seedEventCovers();
        });
    }

    /**
     * Four deterministic media rows per gallery: private source on the local
     * disk for every row, plus a public copy and public state for a subset.
     */
    private function seedGalleryMedia(): void
    {
        $localDisk = Storage::disk(self::LOCAL_DISK);
        $publicDisk = Storage::disk(self::PUBLIC_DISK);

        foreach (Gallery::query()->orderBy('id')->get() as $gallery) {
            $galleryKey = $this->galleryKey($gallery);
            $galleryDirectory = SafeStoragePath::GALLERY_MEDIA_PREFIX.'demo/'.$galleryKey;
            $publicDirectory = 'public/gallery-media/demo/'.$galleryKey;

            for ($slot = 1; $slot <= self::MEDIA_PER_GALLERY; $slot++) {
                $asset = $this->sourceAssetFor($galleryKey, $slot);
                $extension = pathinfo($asset, PATHINFO_EXTENSION);
                $filename = 'media-'.$slot.'.'.$extension;

                $privatePath = $galleryDirectory.'/'.$filename;

                if (! SafeStoragePath::isSafe($privatePath, [SafeStoragePath::GALLERY_MEDIA_PREFIX])) {
                    throw new RuntimeException("Jalur media demo tidak aman: {$privatePath}");
                }

                $localDisk->put($privatePath, $this->assetContents($asset));

                // Freeze the fixture's real content type and byte size so the
                // demo rows carry the same valid metadata the upload contract
                // would have probed.
                $mimeType = $this->probeMimeType($localDisk, $privatePath);
                $size = $this->probeSize($localDisk, $privatePath);
                $altText = $this->altTextFor($gallery, $slot);

                // Deterministic public subset: the lead image of every
                // published gallery is copied to the public tree exactly like
                // PublishMediaToPortal would, and the private original is
                // released.
                $isPublic = $gallery->status === PublicationStatus::Published && $slot === 1;

                if ($isPublic) {
                    $publicPath = $publicDirectory.'/'.$filename;
                    $publicDisk->put($publicPath, $localDisk->get($privatePath));
                    $localDisk->delete($privatePath);

                    $disk = self::PUBLIC_DISK;
                    $path = $publicPath;
                } else {
                    $disk = self::LOCAL_DISK;
                    $path = $privatePath;
                }

                Media::query()->updateOrCreate(
                    ['gallery_id' => $gallery->id, 'sort_order' => $slot],
                    [
                        'organization_unit_id' => $gallery->organization_unit_id,
                        'disk' => $disk,
                        'path' => $path,
                        'mime_type' => $mimeType,
                        'size' => $size,
                        'alt_text' => $altText,
                        'is_public' => $isPublic,
                    ],
                );
            }
        }
    }

    /**
     * Materialise a real private image file for every recorded event cover.
     */
    private function seedEventCovers(): void
    {
        $localDisk = Storage::disk(self::LOCAL_DISK);

        foreach (Event::query()->whereNotNull('cover_path')->orderBy('id')->get() as $event) {
            $path = SafeStoragePath::normalize(
                (string) $event->cover_path,
                [SafeStoragePath::EVENT_COVER_PREFIX],
            );

            if ($path === null) {
                throw new RuntimeException("Jalur sampul demo tidak aman: {$event->cover_path}");
            }

            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $asset = $extension === 'jpg' || $extension === 'jpeg'
                ? self::PLACEHOLDER_ASSETS[1] // a real JPEG-compatible substitute
                : self::HERO_ASSET;

            $localDisk->put($path, $this->assetContents($asset));

            $mimeType = $this->probeMimeType($localDisk, $path);

            if (! in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
                throw new RuntimeException("Sampul demo bukan gambar yang diizinkan: {$path}");
            }
        }
    }

    /**
     * Choose a checked-in fixture for a gallery slot. Slots 3 and 4 reuse the
     * existing WebP hero asset (copied, never mutated); slots 1 and 2 cycle two
     * distinct checked-in PNG fixtures.
     */
    private function sourceAssetFor(int $galleryKey, int $slot): string
    {
        if ($slot >= 3) {
            return self::HERO_ASSET;
        }

        return self::PLACEHOLDER_ASSETS[($galleryKey + $slot) % 2];
    }

    private function assetContents(string $asset): string
    {
        $absolute = public_path($asset);

        if (! is_file($absolute)) {
            throw new RuntimeException("Aset demo tidak ditemukan: {$absolute}");
        }

        return (string) file_get_contents($absolute);
    }

    private function probeMimeType(Filesystem $disk, string $path): string
    {
        $mimeType = $disk->mimeType($path);

        if (! is_string($mimeType) || ! in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new RuntimeException("Media demo bukan gambar JPEG/PNG/WebP: {$path}");
        }

        return $mimeType;
    }

    private function probeSize(Filesystem $disk, string $path): int
    {
        $size = $disk->size($path);

        if (! is_int($size) || $size < 1) {
            throw new RuntimeException("Ukuran media demo tidak valid: {$path}");
        }

        return $size;
    }

    /**
     * Deterministic numeric key shared with the publication seeder's
     * `galeri-XX-...` slugs so storage paths stay stable across runs.
     */
    private function galleryKey(Gallery $gallery): int
    {
        if (preg_match('/^galeri-(\d+)-/', (string) $gallery->slug, $matches) === 1) {
            return (int) $matches[1];
        }

        // Fallback keeps the path deterministic even for a legacy slug shape.
        return (int) $gallery->id;
    }

    private function altTextFor(Gallery $gallery, int $slot): string
    {
        $category = $gallery->category?->label() ?? 'Dokumentasi';

        return $category.' '.$gallery->title.' - media '.$slot.' (demo sintetis)';
    }
}
