<?php

namespace Database\Seeders;

use App\Actions\PublishMediaToPortal;
use App\Enums\PublicationStatus;
use App\Models\DemoArtifact;
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
 * 1. **Trusted ownership.** Every artifact this seeder creates is registered in
 *    the {@see DemoArtifact} manifest inside the same transaction, recording the
 *    exact `namespace` + `disk` + `path`, its kind, and the owning seeded row.
 *    Reconciliation (and any cleanup) may remove a row/file only when a manifest
 *    row proves this seeder owns it — a matching path or the `demo/` prefix
 *    alone is never proof, so foreign/manual artifacts survive.
 * 2. **Staged, failure-safe writes.** New bytes are staged to unique temporary
 *    paths and verified before anything destructive happens. The pre-run bytes
 *    of every path the run overwrites are journaled. All DB + manifest state is
 *    written in one transaction; destructive cleanup of superseded files runs
 *    only after commit. A pre-commit failure restores the journaled originals
 *    (verified byte-for-byte) and, if restoration itself fails, raises a
 *    distinct hard failure instead of claiming success.
 * 3. **Determinism.** Paths and natural keys are derived deterministically, so
 *    reruns idempotently update the same rows/files and the manifest converges.
 */
class DemoMediaSeeder extends Seeder
{
    /** Exactly four media rows per gallery. */
    public const MEDIA_PER_GALLERY = 4;

    /** Deterministic ownership namespace written to the manifest. */
    public const NAMESPACE = 'mvp-demo-seed';

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
     * Pre-run state of every path this run overwrites or deletes, keyed by disk
     * then path, so a pre-commit failure can restore the exact original. A
     * `null` entry records that the path did not exist before the run.
     *
     * @var array<string, array<string, string|null>>
     */
    private array $journal = [];

    /**
     * Files staged at unique temporary paths during this run, keyed by disk then
     * final path. Emptied as each staged file is promoted.
     *
     * @var array<string, array<string, string>>
     */
    private array $staged = [];

    public function run(DemoSeedContext $context): void
    {
        $this->journal = [];
        $this->staged = [];

        // Artifacts that must be removed after the transaction commits: the
        // file deletions are destructive, so they are deferred until the DB
        // (media rows + manifest) has been durably updated.
        $cleanup = [];

        try {
            DB::transaction(function () use ($context, &$cleanup): void {
                $this->seedGalleryMedia($context, $cleanup);
                $this->seedEventCovers($context, $cleanup);

                // The DB + manifest state is staged; now promote the verified
                // bytes into their final paths (journaling every overwritten
                // original so a failure can restore it).
                $this->promoteStaged(self::LOCAL_DISK);
                $this->promoteStaged(self::PUBLIC_DISK);
            });
        } catch (Throwable $exception) {
            $this->rollbackStorage();

            throw $exception;
        }

        try {
            $this->runCleanup($cleanup);
        } finally {
            $this->discardStagedFiles();
            $this->journal = [];
            $this->staged = [];
        }
    }

    /**
     * Four deterministic media rows per gallery: private source on the local
     * disk for every row, plus a public copy and public state for a subset.
     *
     * Only the galleries recorded in the shared context by
     * {@see DemoPublicationSeeder} are touched. Stale artifacts (rows/files the
     * manifest proves this seeder owns but that are no longer part of the seeded
     * key set, or a previously-public lead whose gallery is no longer published)
     * are queued for removal after commit.
     *
     * @param  list<array{disk: string, path: string}>  $cleanup
     */
    private function seedGalleryMedia(DemoSeedContext $context, array &$cleanup): void
    {
        $localDisk = Storage::disk(self::LOCAL_DISK);
        $publicDisk = Storage::disk(self::PUBLIC_DISK);

        $slugs = $context->publicationKeys(Gallery::class);

        $this->reconcileStaleGalleryMedia($slugs, $cleanup);

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

                $contents = $this->assetContents($asset);
                $this->stage(self::LOCAL_DISK, $privatePath, $contents);

                // Freeze the fixture's real content type and byte size so the
                // demo rows carry the same valid metadata the upload contract
                // would have probed.
                $mimeType = $this->probeStagedMimeType(self::LOCAL_DISK, $privatePath);
                $size = strlen($contents);

                // Deterministic public subset: the lead image of every
                // published gallery is copied to the public tree exactly like
                // PublishMediaToPortal would, and the private source is released
                // only after the public copy is confirmed on disk (post-commit).
                $isPublic = $gallery->status === PublicationStatus::Published && $slot === 1;

                if ($isPublic) {
                    $publicPath = $publicDirectory.'/'.$filename;
                    $this->stage(self::PUBLIC_DISK, $publicPath, $contents);

                    $disk = self::PUBLIC_DISK;
                    $path = $publicPath;

                    // The private staging is now redundant. It is released via
                    // the manifest-driven supersession pass below, never by a
                    // blind path delete: a file this seeder never wrote (and so
                    // never registered in the manifest) must not be removed just
                    // because it sits at the deterministic private lead path.
                    $this->discardStaged(self::LOCAL_DISK, $privatePath);
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
                        'alt_text' => $this->altTextFor($gallery, $slot),
                        'is_public' => $isPublic,
                    ],
                );

                $this->registerArtifact(
                    $disk,
                    $path,
                    DemoArtifact::KIND_GALLERY_MEDIA,
                    $gallery->slug.'#'.$slot,
                    $gallery,
                );
            }

            // Any gallery-media artifact this seeder owns for this gallery that
            // is not one of the four current final paths is superseded (e.g. a
            // lead row that moved from private to public). Queue it for removal.
            $this->queueSupersededGalleryArtifacts($gallery, $cleanup);
        }
    }

    /**
     * Queue removal of this gallery's manifest-proven artifacts that are not the
     * four deterministic paths the seed loop just wrote.
     *
     * @param  list<array{disk: string, path: string}>  $cleanup
     */
    private function queueSupersededGalleryArtifacts(Gallery $gallery, array &$cleanup): void
    {
        $livePaths = [];

        foreach (Media::query()->where('gallery_id', $gallery->id)->get() as $row) {
            $livePaths[] = (string) $row->path;
        }

        $artifacts = DemoArtifact::query()
            ->where('namespace', self::NAMESPACE)
            ->where('kind', DemoArtifact::KIND_GALLERY_MEDIA)
            ->where('owner_type', $gallery::class)
            ->where('owner_id', $gallery->getKey())
            ->get();

        foreach ($artifacts as $artifact) {
            if (in_array($artifact->path, $livePaths, true)) {
                continue;
            }

            // The manifest already proves ownership, so retire it.
            $this->retireArtifact($artifact, $cleanup);
        }
    }

    /**
     * Materialise a real private image file for every recorded event cover,
     * scoped to the events recorded in the shared context.
     *
     * @param  list<array{disk: string, path: string}>  $cleanup
     */
    private function seedEventCovers(DemoSeedContext $context, array &$cleanup): void
    {
        $slugs = $context->publicationKeys(Event::class);

        $this->reconcileStaleEventCovers($slugs, $cleanup);

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
            $contents = $this->assetContents($asset);

            $expectedMime = $this->expectedMimeTypeFor($asset);

            if (! in_array($expectedMime, self::ALLOWED_MIME_TYPES, true)) {
                throw new RuntimeException("Sampul demo bukan gambar yang diizinkan: {$path}");
            }

            $this->stage(self::LOCAL_DISK, $path, $contents);

            $probed = $this->probeStagedMimeType(self::LOCAL_DISK, $path);

            if ($probed !== $expectedMime) {
                throw new RuntimeException("Sampul demo tidak sesuai ekstensi: {$path}");
            }

            $this->registerArtifact(
                self::LOCAL_DISK,
                $path,
                DemoArtifact::KIND_EVENT_COVER,
                $event->slug,
                $event,
            );
        }
    }

    /**
     * Register (or refresh) the trusted ownership manifest row for an artifact.
     * Runs inside the caller's transaction so manifest and media rows commit
     * together.
     */
    private function registerArtifact(string $disk, string $path, string $kind, string $seedKey, object $owner): void
    {
        DemoArtifact::query()->updateOrCreate(
            ['namespace' => self::NAMESPACE, 'disk' => $disk, 'path' => $path],
            [
                'kind' => $kind,
                'seed_key' => $seedKey,
                'owner_type' => $owner::class,
                'owner_id' => $owner->getKey(),
            ],
        );
    }

    /**
     * Remove the manifest rows for gallery artifacts this seeder owns but that
     * are no longer part of the seeded key set, queueing their files for
     * post-commit deletion.
     *
     * Only artifacts with a matching manifest row are considered, so a foreign
     * row/file with an identical path shape but no manifest entry survives.
     *
     * @param  list<string>  $seededGallerySlugs
     * @param  list<array{disk: string, path: string}>  $cleanup
     */
    private function reconcileStaleGalleryMedia(array $seededGallerySlugs, array &$cleanup): void
    {
        $artifacts = DemoArtifact::query()
            ->where('namespace', self::NAMESPACE)
            ->where('kind', DemoArtifact::KIND_GALLERY_MEDIA)
            ->get();

        foreach ($artifacts as $artifact) {
            $gallery = $this->ownerGallery($artifact);

            if ($gallery !== null && in_array($gallery->slug, $seededGallerySlugs, true)) {
                continue;
            }

            // Retire the manifest-proven artifact and, when the owner gallery
            // still exists, drop the demo-owned media row at this exact path.
            // The manifest row is the proof of ownership, so a foreign row at
            // the same path but without a manifest entry is never touched.
            $this->retireArtifact($artifact, $cleanup);

            if ($gallery !== null) {
                Media::query()
                    ->where('gallery_id', $gallery->id)
                    ->where('path', $artifact->path)
                    ->delete();
            }
        }
    }

    /**
     * Remove the manifest rows for event covers this seeder owns but that no
     * longer back a seeded event, queueing their files.
     *
     * @param  list<string>  $seededEventSlugs
     * @param  list<array{disk: string, path: string}>  $cleanup
     */
    private function reconcileStaleEventCovers(array $seededEventSlugs, array &$cleanup): void
    {
        $artifacts = DemoArtifact::query()
            ->where('namespace', self::NAMESPACE)
            ->where('kind', DemoArtifact::KIND_EVENT_COVER)
            ->get();

        foreach ($artifacts as $artifact) {
            $event = $this->ownerEvent($artifact);

            if ($event !== null && in_array($event->slug, $seededEventSlugs, true)) {
                continue;
            }

            $this->retireArtifact($artifact, $cleanup);
        }
    }

    /**
     * Retire a manifest-proven artifact: delete its manifest row now (inside the
     * transaction) and queue its file for post-commit deletion.
     *
     * @param  list<array{disk: string, path: string}>  $cleanup
     */
    private function retireArtifact(DemoArtifact $artifact, array &$cleanup): void
    {
        $cleanup[] = ['disk' => $artifact->disk, 'path' => $artifact->path];
        $artifact->delete();
    }

    private function ownerGallery(DemoArtifact $artifact): ?Gallery
    {
        if ($artifact->owner_type !== Gallery::class || $artifact->owner_id === null) {
            return null;
        }

        return Gallery::query()->find($artifact->owner_id);
    }

    private function ownerEvent(DemoArtifact $artifact): ?Event
    {
        if ($artifact->owner_type !== Event::class || $artifact->owner_id === null) {
            return null;
        }

        return Event::query()->find($artifact->owner_id);
    }

    /**
     * Stage new bytes at a unique temporary path on the target disk and verify
     * the write. Nothing at the final path is touched yet.
     */
    private function stage(string $diskName, string $finalPath, string $contents): void
    {
        $disk = Storage::disk($diskName);

        if (isset($this->staged[$diskName][$finalPath])) {
            return;
        }

        $tempPath = $this->tempPathFor($finalPath);

        if ($disk->put($tempPath, $contents) === false || ! $disk->exists($tempPath)) {
            throw new RuntimeException("Gagal menulis berkas media demo sementara: {$tempPath}");
        }

        if ($disk->get($tempPath) !== $contents) {
            $disk->delete($tempPath);

            throw new RuntimeException("Berkas media demo sementara tidak utuh: {$tempPath}");
        }

        $this->staged[$diskName][$finalPath] = $tempPath;
    }

    /**
     * Promote every staged file to its final path for a disk: journal the
     * original at the final path, move the verified staged bytes into place, and
     * confirm the resulting bytes.
     */
    private function promoteStaged(string $diskName): void
    {
        $disk = Storage::disk($diskName);

        foreach ($this->staged[$diskName] ?? [] as $finalPath => $tempPath) {
            $this->journalOriginal($diskName, $disk, $finalPath);

            $contents = $disk->get($tempPath);

            if (! is_string($contents) || $contents === '') {
                throw new RuntimeException("Berkas media demo sementara hilang: {$tempPath}");
            }

            if ($disk->put($finalPath, $contents) === false || $disk->get($finalPath) !== $contents) {
                throw new RuntimeException("Gagal mempromosikan berkas media demo: {$finalPath}");
            }

            $disk->delete($tempPath);
        }

        $this->staged[$diskName] = [];
    }

    /**
     * Record the pre-run state (existence + bytes) of a path before this run
     * overwrites it. Only the first snapshot is kept.
     */
    private function journalOriginal(string $diskName, Filesystem $disk, string $path): void
    {
        if (array_key_exists($path, $this->journal[$diskName] ?? [])) {
            return;
        }

        $this->journal[$diskName][$path] = $disk->exists($path) ? $disk->get($path) : null;
    }

    /**
     * Delete superseded/orphaned files after the transaction has committed, and
     * remove any lingering staged temp files.
     *
     * The DB + manifest are already durable, so a cleanup failure cannot be
     * rolled back; but because the disks use `throw => false`, a refused delete
     * returns `false` (or silently leaves the file). Such a refusal must never
     * be swallowed: it is verified and, if it cannot be confirmed, surfaced as a
     * distinct hard failure so committed DB state never diverges from storage
     * without a signal.
     *
     * @param  list<array{disk: string, path: string}>  $cleanup
     */
    private function runCleanup(array $cleanup): void
    {
        $failures = [];

        foreach (array_unique($cleanup, SORT_REGULAR) as $entry) {
            $disk = Storage::disk($entry['disk']);

            if (! $disk->exists($entry['path'])) {
                continue;
            }

            $deleted = $disk->delete($entry['path']);

            if ($deleted === false || $disk->exists($entry['path'])) {
                $failures[] = $entry['disk'].':'.$entry['path'];
            }
        }

        if ($failures !== []) {
            throw new RuntimeException(
                'Gagal menghapus berkas media demo yang sudah tidak terpakai: '.implode(', ', array_unique($failures)),
            );
        }
    }

    /**
     * Restore every path this run overwrote to its exact pre-run state after a
     * pre-commit failure. Restoration is verified byte-for-byte; if a restore
     * cannot be confirmed, a distinct hard failure is raised so the run is never
     * reported as successful with diverged storage.
     */
    private function rollbackStorage(): void
    {
        $failures = [];

        foreach ($this->journal as $diskName => $entries) {
            $disk = Storage::disk($diskName);

            foreach ($entries as $path => $original) {
                try {
                    if ($original === null) {
                        if ($disk->exists($path) && $disk->delete($path) === false) {
                            $failures[] = $path;
                        }

                        continue;
                    }

                    if ($disk->put($path, $original) === false || $disk->get($path) !== $original) {
                        $failures[] = $path;
                    }
                } catch (Throwable) {
                    $failures[] = $path;
                }
            }
        }

        $this->discardStagedFiles();

        if ($failures !== []) {
            throw new RuntimeException(
                'Gagal memulihkan berkas media demo setelah kegagalan: '.implode(', ', array_unique($failures)),
            );
        }
    }

    /**
     * Remove any staged temp files that were not promoted (on success they are
     * already gone; on failure they must not linger).
     */
    private function discardStagedFiles(): void
    {
        foreach ($this->staged as $diskName => $entries) {
            $disk = Storage::disk($diskName);

            foreach ($entries as $tempPath) {
                try {
                    if ($disk->exists($tempPath)) {
                        $disk->delete($tempPath);
                    }
                } catch (Throwable) {
                    // Best-effort; a lingering temp file is harmless.
                }
            }
        }

        $this->staged = [];
    }

    private function discardStaged(string $diskName, string $finalPath): void
    {
        if (! isset($this->staged[$diskName][$finalPath])) {
            return;
        }

        $tempPath = $this->staged[$diskName][$finalPath];
        $disk = Storage::disk($diskName);

        try {
            if ($disk->exists($tempPath)) {
                $disk->delete($tempPath);
            }
        } catch (Throwable) {
            // Best-effort.
        }

        unset($this->staged[$diskName][$finalPath]);
    }

    private function tempPathFor(string $finalPath): string
    {
        return dirname($finalPath).'/.tmp-'.bin2hex(random_bytes(8));
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

    /**
     * Probe the MIME type of a staged temp file, falling back to the final path
     * once promoted.
     */
    private function probeStagedMimeType(string $diskName, string $finalPath): string
    {
        $disk = Storage::disk($diskName);
        $tempPath = $this->staged[$diskName][$finalPath] ?? $finalPath;

        $mimeType = $disk->mimeType($tempPath);

        if (! is_string($mimeType) || ! in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new RuntimeException("Media demo bukan gambar JPEG/PNG/WebP: {$finalPath}");
        }

        return $mimeType;
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
