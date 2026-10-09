<?php

namespace Database\Seeders;

use App\Actions\PublishMediaToPortal;
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
use Throwable;

/**
 * Seeds the local media fixtures and the gallery/event storage that backs the
 * demo dataset.
 *
 * Every gallery in the seeded key set receives exactly four deterministic media
 * rows. Files are written to the private `local` disk first, then, for the
 * published subset, copied into the `public` disk and the row is flipped to the
 * public contract (`disk = public`, `is_public = true`, path under
 * `public/gallery-media/`) — reproducing the final persisted state the Filament
 * publish action produces without requiring a real authenticated actor. Private
 * rows keep their `local` file and `is_public = false`.
 *
 * Event cover paths recorded by {@see DemoPublicationSeeder} are materialised as
 * real private image files so the `/kegiatan/{slug}/cover` route can stream
 * them. The bytes written always match the recorded extension (a `.jpg` path
 * gets JPEG bytes, a `.webp` path gets WebP bytes). Only JPEG/PNG/WebP fixtures
 * are used; no video rows or files exist.
 *
 * Three correctness properties are enforced here:
 *
 * 1. **Scoping.** Only galleries/events recorded in the shared context are
 *    touched, and only files inside the deterministic `demo/` namespaces are
 *    written or removed. Unrelated local rows and files are never modified.
 * 2. **Reconciliation.** Because these rows/files are owned by the seeder, a
 *    rerun removes stale demo artifacts: media rows and files whose gallery or
 *    event is no longer in the current seeded key set, and previously-public
 *    lead files of a gallery that is no longer published.
 * 3. **Failure safety.** Every `put`/`copy`/`delete` return value is checked and
 *    files created or promoted during the run are tracked so a mid-run failure
 *    compensates by deleting exactly what this run created; a private source is
 *    only released after its public copy is confirmed present.
 */
class DemoMediaSeeder extends Seeder
{
    /** Exactly four media rows per gallery. */
    public const MEDIA_PER_GALLERY = 4;

    /** Private gallery media disk. */
    private const LOCAL_DISK = 'local';

    /** Public gallery media disk. */
    private const PUBLIC_DISK = 'public';

    /** Deterministic public namespace prefix on the `public` disk. */
    private const PUBLIC_DEMO_PREFIX = 'public/gallery-media/demo/';

    /** Checked-in PNG fixtures, cycled deterministically. */
    private const PLACEHOLDER_ASSETS = [
        'images/demo/demo-placeholder-1.png',
        'images/demo/demo-placeholder-2.png',
        'images/demo/demo-placeholder-3.png',
    ];

    /** Checked-in JPEG fixture used for `.jpg` event covers. */
    private const JPEG_ASSET = 'images/demo/demo-placeholder-1.jpg';

    /** Checked-in WebP reused for selected rows without mutating the source. */
    private const HERO_ASSET = 'images/hero-martial-arts-poster.webp';

    /** @var list<string> */
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * Files written or promoted on the `local` disk during this run, so a
     * failure can remove exactly what this run created.
     *
     * @var list<string>
     */
    private array $createdLocalFiles = [];

    /**
     * Files written or promoted on the `public` disk during this run.
     *
     * @var list<string>
     */
    private array $createdPublicFiles = [];

    public function run(DemoSeedContext $context): void
    {
        $this->createdLocalFiles = [];
        $this->createdPublicFiles = [];

        try {
            DB::transaction(function () use ($context): void {
                $this->seedGalleryMedia($context);
                $this->seedEventCovers($context);
            });
        } catch (Throwable $exception) {
            $this->compensate();

            throw $exception;
        }
    }

    /**
     * Four deterministic media rows per gallery: private source on the local
     * disk for every row, plus a public copy and public state for a subset.
     *
     * Only the galleries recorded in the shared context by
     * {@see DemoPublicationSeeder} are touched. Stale demo media rows/files for
     * galleries no longer in the key set (and public lead files of a gallery
     * that is no longer published) are reconciled away.
     */
    private function seedGalleryMedia(DemoSeedContext $context): void
    {
        $localDisk = Storage::disk(self::LOCAL_DISK);
        $publicDisk = Storage::disk(self::PUBLIC_DISK);

        $slugs = $context->publicationKeys(Gallery::class);

        $this->reconcileStaleGalleryMedia($slugs);

        if ($slugs === []) {
            return;
        }

        foreach (Gallery::query()->whereIn('slug', $slugs)->orderBy('id')->get() as $gallery) {
            $galleryKey = $this->galleryKey($gallery);
            $galleryDirectory = SafeStoragePath::GALLERY_MEDIA_PREFIX.'demo/'.$galleryKey;
            $publicDirectory = self::PUBLIC_DEMO_PREFIX.$galleryKey;

            for ($slot = 1; $slot <= self::MEDIA_PER_GALLERY; $slot++) {
                $asset = $this->sourceAssetFor($galleryKey, $slot);
                $extension = pathinfo($asset, PATHINFO_EXTENSION);
                $filename = 'media-'.$slot.'.'.$extension;

                $privatePath = $galleryDirectory.'/'.$filename;

                if (! SafeStoragePath::isSafe($privatePath, [SafeStoragePath::GALLERY_MEDIA_PREFIX])) {
                    throw new RuntimeException("Jalur media demo tidak aman: {$privatePath}");
                }

                $this->writeFile($localDisk, $privatePath, $this->assetContents($asset), $this->createdLocalFiles);

                // Freeze the fixture's real content type and byte size so the
                // demo rows carry the same valid metadata the upload contract
                // would have probed.
                $mimeType = $this->probeMimeType($localDisk, $privatePath);
                $size = $this->probeSize($localDisk, $privatePath);
                $altText = $this->altTextFor($gallery, $slot);

                // Deterministic public subset: the lead image of every
                // published gallery is copied to the public tree exactly like
                // PublishMediaToPortal would, and the private original is
                // released only after the public copy is confirmed on disk.
                $isPublic = $gallery->status === PublicationStatus::Published && $slot === 1;

                if ($isPublic) {
                    $publicPath = $publicDirectory.'/'.$filename;

                    $this->copyToPublicDisk($localDisk, $publicDisk, $privatePath, $publicPath);

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
     * Remove demo media rows/files that this seeder previously created but that
     * are no longer part of the current seeded key set.
     *
     * Scoped to demo namespaces and demo-owned rows only: a media row is stale
     * when its gallery is no longer among the seeded galleries, or when it is a
     * previously-public row whose gallery is no longer published. Unrelated
     * rows/files are never touched.
     *
     * @param  list<string>  $seededGallerySlugs
     */
    private function reconcileStaleGalleryMedia(array $seededGallerySlugs): void
    {
        $localDisk = Storage::disk(self::LOCAL_DISK);
        $publicDisk = Storage::disk(self::PUBLIC_DISK);

        // Stale rows: any seeded-namespace media row whose gallery is gone, or
        // any previously-public row whose gallery is no longer published.
        $candidates = Media::query()
            ->where(function ($query): void {
                $query->where('path', 'like', SafeStoragePath::GALLERY_MEDIA_PREFIX.'demo/%')
                    ->orWhere('path', 'like', self::PUBLIC_DEMO_PREFIX.'%');
            })
            ->with('gallery')
            ->get();

        foreach ($candidates as $media) {
            $gallery = $media->gallery;
            $seeded = $gallery !== null && in_array($gallery->slug, $seededGallerySlugs, true);
            $stillPublic = $media->is_public && $gallery?->status === PublicationStatus::Published;

            if ($seeded && (! $media->is_public || $stillPublic)) {
                continue;
            }

            // A row that is no longer seeded, or a previously-public row whose
            // gallery stopped being published. Delete its file only when it
            // sits inside the demo namespace (never a hand-uploaded file).
            $this->deleteDemoFileIfOwned($localDisk, (string) $media->path);
            $this->deleteDemoFileIfOwned($publicDisk, (string) $media->path);

            $media->delete();
        }

        // Sweep orphan demo files that no surviving row references (e.g. a
        // previously-public lead file whose row was reconciled away, or a file
        // left behind by an earlier demo dataset). Scoped strictly to the
        // deterministic demo namespaces.
        $livePaths = Media::query()->pluck('path')->all();

        $this->sweepOrphanDemoFiles($localDisk, rtrim(SafeStoragePath::GALLERY_MEDIA_PREFIX.'demo', '/'), $livePaths);
        $this->sweepOrphanDemoFiles($publicDisk, rtrim(self::PUBLIC_DEMO_PREFIX, '/'), $livePaths);
    }

    /**
     * Delete demo-namespace files under `$directory` that are not referenced by
     * any surviving media row.
     *
     * @param  list<string>  $livePaths
     */
    private function sweepOrphanDemoFiles(Filesystem $disk, string $directory, array $livePaths): void
    {
        if (! $disk->directoryExists($directory)) {
            return;
        }

        foreach ($disk->files($directory, true) as $file) {
            if (in_array($file, $livePaths, true)) {
                continue;
            }

            $this->deleteDemoFileIfOwned($disk, $file);
        }
    }

    /**
     * Materialise a real private image file for every recorded event cover.
     *
     * Scoped to the events recorded in the shared context so an unrelated local
     * event that happens to carry a `cover_path` is never written to. Stale
     * demo cover files whose event is no longer in the key set are removed.
     */
    private function seedEventCovers(DemoSeedContext $context): void
    {
        $localDisk = Storage::disk(self::LOCAL_DISK);

        $slugs = $context->publicationKeys(Event::class);

        $this->reconcileStaleEventCovers($slugs);

        if ($slugs === []) {
            return;
        }

        foreach (Event::query()->whereIn('slug', $slugs)->whereNotNull('cover_path')->orderBy('id')->get() as $event) {
            $path = SafeStoragePath::normalize(
                (string) $event->cover_path,
                [SafeStoragePath::EVENT_COVER_PREFIX],
            );

            if ($path === null) {
                throw new RuntimeException("Jalur sampul demo tidak aman: {$event->cover_path}");
            }

            $asset = $this->coverAssetFor($path);

            $this->writeFile($localDisk, $path, $this->assetContents($asset), $this->createdLocalFiles);

            $mimeType = $this->probeMimeType($localDisk, $path);

            if ($mimeType !== $this->expectedMimeTypeFor($asset)) {
                throw new RuntimeException("Sampul demo tidak sesuai ekstensi: {$path}");
            }
        }
    }

    /**
     * Remove demo event-cover files that this seeder previously created but that
     * no longer back a seeded event.
     *
     * @param  list<string>  $seededEventSlugs
     */
    private function reconcileStaleEventCovers(array $seededEventSlugs): void
    {
        $localDisk = Storage::disk(self::LOCAL_DISK);

        $currentPaths = [];

        if ($seededEventSlugs !== []) {
            $currentPaths = Event::query()
                ->whereIn('slug', $seededEventSlugs)
                ->whereNotNull('cover_path')
                ->pluck('cover_path')
                ->all();
        }

        $directory = rtrim(SafeStoragePath::EVENT_COVER_PREFIX, '/');

        if (! $localDisk->directoryExists($directory)) {
            return;
        }

        foreach ($localDisk->files($directory, true) as $file) {
            if (in_array($file, $currentPaths, true)) {
                continue;
            }

            $this->deleteDemoFileIfOwned($localDisk, $file);
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

    /**
     * Choose a fixture whose real content type matches the covered path's
     * extension, so a `.jpg` cover never contains PNG bytes.
     */
    private function coverAssetFor(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => self::JPEG_ASSET,
            'webp' => self::HERO_ASSET,
            'png' => self::PLACEHOLDER_ASSETS[0],
            default => throw new RuntimeException("Ekstensi sampul demo tidak didukung: {$path}"),
        };
    }

    private function expectedMimeTypeFor(string $asset): string
    {
        return match (strtolower(pathinfo($asset, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => throw new RuntimeException("Aset demo tidak didukung: {$asset}"),
        };
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
     * Write file contents and fail loudly when the disk refuses the write.
     *
     * The configured disks use `throw => false`, so `put()` signals a failed
     * write by returning `false` rather than raising. Any caller that then
     * probes or deletes the path would otherwise act on a file that was never
     * created (or, worse, destroy a surviving private original), so every write
     * goes through this guard. The written path is tracked so a later failure
     * can compensate.
     *
     * @param  list<string>  $created
     */
    private function writeFile(Filesystem $disk, string $path, string $contents, array &$created): void
    {
        $existedBefore = $disk->exists($path);

        if ($disk->put($path, $contents) === false || ! $disk->exists($path)) {
            throw new RuntimeException("Gagal menulis berkas media demo: {$path}");
        }

        // Only track files this run actually created so compensation never
        // deletes a file that pre-existed the run and was merely overwritten.
        if (! $existedBefore) {
            $created[] = $path;
        }
    }

    /**
     * Copy a private file to the public disk, then release the private original
     * only once the public copy is confirmed present.
     *
     * Mirroring {@see PublishMediaToPortal}, the destructive delete
     * is sequenced strictly after a verified copy so a failed copy can never
     * leave the record pointing at a public path whose file was never written
     * while the only real copy was deleted. Both written paths are tracked for
     * compensation.
     */
    private function copyToPublicDisk(Filesystem $localDisk, Filesystem $publicDisk, string $privatePath, string $publicPath): void
    {
        $contents = $localDisk->get($privatePath);

        if (! is_string($contents) || $contents === '') {
            throw new RuntimeException("Berkas privat media demo tidak terbaca: {$privatePath}");
        }

        $publicExistedBefore = $publicDisk->exists($publicPath);

        if ($publicDisk->put($publicPath, $contents) === false || ! $publicDisk->exists($publicPath)) {
            throw new RuntimeException("Gagal menyalin media demo ke penyimpanan publik: {$publicPath}");
        }

        if (! $publicExistedBefore) {
            $this->createdPublicFiles[] = $publicPath;
        }

        if ($localDisk->delete($privatePath) === false || $localDisk->exists($privatePath)) {
            throw new RuntimeException("Gagal menghapus berkas privat media demo: {$privatePath}");
        }

        $this->createdLocalFiles = array_values(array_diff($this->createdLocalFiles, [$privatePath]));
    }

    /**
     * Delete a file only when it lives inside one of the deterministic demo
     * namespaces this seeder owns, and confirm the deletion.
     */
    private function deleteDemoFileIfOwned(Filesystem $disk, string $path): void
    {
        $isLocalDemo = SafeStoragePath::isSafe($path, [SafeStoragePath::GALLERY_MEDIA_PREFIX]);
        $isEventDemo = SafeStoragePath::isSafe($path, [SafeStoragePath::EVENT_COVER_PREFIX]);
        $isPublicDemo = str_starts_with($path, self::PUBLIC_DEMO_PREFIX);

        if (! $isLocalDemo && ! $isEventDemo && ! $isPublicDemo) {
            return;
        }

        if (! $disk->exists($path)) {
            return;
        }

        if ($disk->delete($path) === false || $disk->exists($path)) {
            throw new RuntimeException("Gagal menghapus berkas demo usang: {$path}");
        }
    }

    /**
     * Remove everything this run created on either disk after a failure, so a
     * partially-seeded state never leaves orphan files behind. Runs best-effort
     * for every tracked path and never masks the original exception.
     */
    private function compensate(): void
    {
        $localDisk = Storage::disk(self::LOCAL_DISK);
        $publicDisk = Storage::disk(self::PUBLIC_DISK);

        foreach (array_unique($this->createdLocalFiles) as $path) {
            try {
                if ($localDisk->exists($path)) {
                    $localDisk->delete($path);
                }
            } catch (Throwable) {
                // Best-effort: the original exception is what matters.
            }
        }

        foreach (array_unique($this->createdPublicFiles) as $path) {
            try {
                if ($publicDisk->exists($path)) {
                    $publicDisk->delete($path);
                }
            } catch (Throwable) {
                // Best-effort.
            }
        }

        $this->createdLocalFiles = [];
        $this->createdPublicFiles = [];
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
