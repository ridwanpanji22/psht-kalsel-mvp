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
 *    written, and only at the exact deterministic paths this seeder produces.
 *    Unrelated local rows and files are never modified.
 * 2. **Ownership.** Reconciliation deletes only rows/files whose path matches
 *    the exact deterministic demo shape this seeder writes (a gallery-media
 *    `demo/{key}/media-{1..4}.{ext}` path for a known demo gallery key, or a
 *    `demo/kegiatan-{n}.{ext}` cover). The `demo/` namespace prefix alone is
 *    never treated as ownership proof, so foreign rows/files that merely live
 *    under `demo/` are left untouched.
 * 3. **Failure safety.** Every `put`/`copy`/`delete` return value is checked,
 *    the pre-run state of every overwritten or deleted path is snapshotted, and
 *    a mid-run failure restores those bytes (and removes paths that did not
 *    previously exist); a private source is released only after its public copy
 *    is confirmed present.
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
     * Exact deterministic shape of a gallery media path this seeder owns:
     * `private/gallery-media/demo/{key}/media-{slot}.{ext}` or the public
     * mirror `public/gallery-media/demo/{key}/media-{slot}.{ext}`, where the
     * slot is 1–4 and the extension is a still-image type. Ownership is proved
     * by the path shape and a matching demo gallery key — never by the `demo/`
     * prefix alone.
     */
    private const GALLERY_MEDIA_PATTERN = '#^(?:private/gallery-media/demo|public/gallery-media/demo)/(\d+)/media-([1-4])\.(png|webp|jpg)$#';

    /**
     * Exact deterministic shape of an event cover this seeder owns:
     * `private/event-covers/demo/kegiatan-{n}.{jpg|webp}`.
     */
    private const EVENT_COVER_PATTERN = '#^private/event-covers/demo/kegiatan-\d+\.(png|webp|jpg)$#';

    /**
     * Original bytes of every file this run overwrites or deletes, keyed by
     * disk then path, so a mid-run failure can restore the pre-run state
     * exactly. A `null` entry records that the path did not exist before the
     * run (and so must be removed on rollback).
     *
     * @var array<string, array<string, string|null>>
     */
    private array $journal = [];

    public function run(DemoSeedContext $context): void
    {
        $this->journal = [];

        try {
            DB::transaction(function () use ($context): void {
                $this->seedGalleryMedia($context);
                $this->seedEventCovers($context);
            });
        } catch (Throwable $exception) {
            $this->rollbackStorage();

            throw $exception;
        }

        $this->journal = [];
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

                $this->writeFile($localDisk, $privatePath, $this->assetContents($asset));

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
     * Ownership is proved by the **exact deterministic path shape** this seeder
     * writes (`demo/{key}/media-{1..4}.{ext}`) together with a known demo
     * gallery key — the `demo/` namespace prefix alone is never treated as
     * proof. A row is reconciled only when its path matches that shape and its
     * gallery is no longer among the seeded galleries, or when it is a
     * previously-public lead whose gallery is no longer published. Both the
     * row's own path and its deterministic sibling (private/public counterpart)
     * are removed. Foreign rows and files that merely live under `demo/` are
     * left untouched.
     *
     * @param  list<string>  $seededGallerySlugs
     */
    private function reconcileStaleGalleryMedia(array $seededGallerySlugs): void
    {
        $ownedKeys = $this->demoGalleryKeys();

        $candidates = Media::query()
            ->with('gallery')
            ->get();

        foreach ($candidates as $media) {
            $path = (string) $media->path;
            $match = $this->matchOwnedGalleryMediaPath($path);

            if ($match === null) {
                continue;
            }

            [, $key, $slot, $extension] = $match;
            $key = (string) (int) $key;

            // The path must belong to a real demo gallery key AND that gallery
            // must exist for the row to be considered demo-owned.
            if (! in_array($key, $ownedKeys, true)) {
                continue;
            }

            $gallery = $media->gallery;
            $seeded = $gallery !== null && in_array($gallery->slug, $seededGallerySlugs, true);
            $stillPublic = $media->is_public && $gallery?->status === PublicationStatus::Published;

            if ($seeded && (! $media->is_public || $stillPublic)) {
                continue;
            }

            // Remove the row's exact path on either disk plus its deterministic
            // sibling (the private original or the public lead copy), each only
            // when the path matches the owned shape.
            $this->deleteOwnedFile(self::LOCAL_DISK, $path);
            $this->deleteOwnedFile(self::PUBLIC_DISK, $path);
            $this->deleteOwnedFile(self::LOCAL_DISK, SafeStoragePath::GALLERY_MEDIA_PREFIX.'demo/'.$key.'/media-'.$slot.'.'.$extension);
            $this->deleteOwnedFile(self::PUBLIC_DISK, self::PUBLIC_DEMO_PREFIX.$key.'/media-'.$slot.'.'.$extension);

            $media->delete();
        }
    }

    /**
     * Gallery keys that identify a real demo gallery: every gallery whose slug
     * follows the seeder's `galeri-{key}-…` convention. Used to prove that a
     * `demo/{key}/…` path shape really belongs to a demo gallery.
     *
     * @return list<string>
     */
    private function demoGalleryKeys(): array
    {
        return Gallery::query()
            ->where('slug', 'like', 'galeri-%')
            ->pluck('slug')
            ->map(static function (string $slug): ?string {
                return preg_match('#^galeri-(\d+)-#', $slug, $matches) === 1
                    ? (string) (int) $matches[1]
                    : null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Match a path against the exact deterministic gallery-media shape this
     * seeder owns. Returns `[full, key, slot, extension]` or null.
     *
     * @return array{0: string, 1: string, 2: string, 3: string}|null
     */
    private function matchOwnedGalleryMediaPath(string $path): ?array
    {
        if (preg_match(self::GALLERY_MEDIA_PATTERN, $path, $matches) !== 1) {
            return null;
        }

        return [$matches[0], $matches[1], $matches[2], $matches[3]];
    }

    /**
     * Delete a file only when its path matches an exact deterministic demo
     * shape this seeder owns, snapshotting the original first so a failure can
     * restore it.
     */
    private function deleteOwnedFile(string $diskName, string $path): void
    {
        if (! $this->isOwnedDemoPath($path)) {
            return;
        }

        $disk = Storage::disk($diskName);

        if (! $disk->exists($path)) {
            return;
        }

        $this->snapshot($diskName, $disk, $path);

        if ($disk->delete($path) === false || $disk->exists($path)) {
            throw new RuntimeException("Gagal menghapus berkas demo usang: {$path}");
        }
    }

    /**
     * Whether a path has one of the exact deterministic shapes this seeder
     * writes (gallery media or event cover) — the ownership proof used before
     * any deletion.
     */
    private function isOwnedDemoPath(string $path): bool
    {
        return preg_match(self::GALLERY_MEDIA_PATTERN, $path) === 1
            || preg_match(self::EVENT_COVER_PATTERN, $path) === 1;
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

            $this->writeFile($localDisk, $path, $this->assetContents($asset));

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
     * Ownership is proved by the exact deterministic cover shape
     * (`private/event-covers/demo/kegiatan-{n}.{ext}`) — a file merely living in
     * the `demo/` cover directory under any other name is never touched.
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

            // Only sweep files whose name is an exact deterministic demo cover.
            if (preg_match(self::EVENT_COVER_PATTERN, $file) !== 1) {
                continue;
            }

            $this->deleteOwnedFile(self::LOCAL_DISK, $file);
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
     * goes through this guard. The pre-run state of the path is snapshotted so a
     * later failure can restore an overwritten original.
     */
    private function writeFile(Filesystem $disk, string $path, string $contents): void
    {
        $this->snapshot($this->diskName($disk), $disk, $path);

        if ($disk->put($path, $contents) === false || ! $disk->exists($path)) {
            throw new RuntimeException("Gagal menulis berkas media demo: {$path}");
        }
    }

    /**
     * Copy a private file to the public disk, then release the private original
     * only once the public copy is confirmed present.
     *
     * Mirroring {@see PublishMediaToPortal}, the destructive delete
     * is sequenced strictly after a verified copy so a failed copy can never
     * leave the record pointing at a public path whose file was never written
     * while the only real copy was deleted. Both paths are snapshotted so a
     * later failure restores the exact pre-run state.
     */
    private function copyToPublicDisk(Filesystem $localDisk, Filesystem $publicDisk, string $privatePath, string $publicPath): void
    {
        $contents = $localDisk->get($privatePath);

        if (! is_string($contents) || $contents === '') {
            throw new RuntimeException("Berkas privat media demo tidak terbaca: {$privatePath}");
        }

        $this->snapshot(self::PUBLIC_DISK, $publicDisk, $publicPath);

        if ($publicDisk->put($publicPath, $contents) === false || ! $publicDisk->exists($publicPath)) {
            throw new RuntimeException("Gagal menyalin media demo ke penyimpanan publik: {$publicPath}");
        }

        $this->snapshot(self::LOCAL_DISK, $localDisk, $privatePath);

        if ($localDisk->delete($privatePath) === false || $localDisk->exists($privatePath)) {
            throw new RuntimeException("Gagal menghapus berkas privat media demo: {$privatePath}");
        }
    }

    /**
     * Record the pre-run state (existence + bytes) of a path before this run
     * modifies it, so a failure can restore it. Only the first snapshot for a
     * path is kept: the earliest state is the true pre-run state.
     */
    private function snapshot(string $diskName, Filesystem $disk, string $path): void
    {
        if (array_key_exists($path, $this->journal[$diskName] ?? [])) {
            return;
        }

        $this->journal[$diskName][$path] = $disk->exists($path) ? $disk->get($path) : null;
    }

    /**
     * Restore every path this run overwrote or deleted to its exact pre-run
     * state after a failure: previously existing files get their original
     * bytes back, and paths that did not exist before are removed. Runs
     * best-effort for every snapshotted path and never masks the original
     * exception.
     */
    private function rollbackStorage(): void
    {
        foreach ($this->journal as $diskName => $entries) {
            $disk = Storage::disk($diskName);

            foreach ($entries as $path => $original) {
                try {
                    if ($original === null) {
                        if ($disk->exists($path)) {
                            $disk->delete($path);
                        }

                        continue;
                    }

                    $disk->put($path, $original);
                } catch (Throwable) {
                    // Best-effort: the original exception is what matters.
                }
            }
        }

        $this->journal = [];
    }

    /**
     * Resolve a filesystem instance back to its configured disk name, so
     * snapshots are journaled per disk.
     */
    private function diskName(Filesystem $disk): string
    {
        return $disk === Storage::disk(self::LOCAL_DISK) ? self::LOCAL_DISK : self::PUBLIC_DISK;
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
