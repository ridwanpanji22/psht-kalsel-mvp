<?php

namespace Database\Seeders;

use App\Enums\GalleryCategory;
use App\Enums\PublicationStatus;
use App\Models\Event;
use App\Models\Gallery;
use App\Models\OrganizationUnit;
use App\Models\Page;
use App\Models\TrainingLocation;
use App\Models\User;
use App\OrganizationUnitType;
use Database\Seeders\Support\DemoSeedContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Seeds the deterministic public content: pages, activities, training
 * locations, and galleries.
 *
 * Every record is resolved by a deterministic natural key (page kind+slug,
 * slug elsewhere) so reruns update instead of duplicating. Ownership follows
 * the revised hierarchy: pages may be unit-less for the general profile, branch
 * histories point at their branch, activities belong to branches/rantings, and
 * training locations cover branches, rantings, rayons, UKM, and Komisariat.
 *
 * Only `published` rows carry `published_at` and `published_by_id`; the public
 * controllers already exclude everything else. Media rows and cover files are
 * owned by the Task 6 media seeder — this seeder only records the intended
 * `cover_path` / `cover_alt_text` metadata.
 */
class DemoPublicationSeeder extends Seeder
{
    /** Exactly eight public pages. */
    public const PAGE_TOTAL = 8;

    /** Exactly 39 activities. */
    public const EVENT_TOTAL = 39;

    /** Exactly 65 training locations. */
    public const LOCATION_TOTAL = 65;

    /** Exactly 26 galleries. */
    public const GALLERY_TOTAL = 26;

    /** Deterministic publication timestamp for the "published" rows. */
    private const PUBLISHED_AT = '2026-09-20 09:00:00';

    /** Deterministic review timestamp for the "pending_review" rows. */
    private const REVIEWED_AT = '2026-09-18 09:00:00';

    /** Geographic anchors around Kalimantan Selatan; cycled for locations. */
    private const COORDINATES = [
        // [latitude, longitude] city anchors.
        ['-3.3186', '114.5944'], // Banjarmasin
        ['-3.4572', '114.8115'], // Banjarbaru
        ['-3.4109', '114.8647'], // Martapura
        ['-3.7846', '114.7649'], // Pelaihari
        ['-2.7879', '115.2625'], // Kandangan
        ['-2.5829', '115.3745'], // Barabai
        ['-2.4177', '115.2494'], // Amuntai
        ['-2.1710', '115.4393'], // Tanjung
        ['-3.2355', '116.2186'], // Kotabaru
        ['-3.4421', '115.9876'], // Batulicin
        ['-2.8047', '114.7955'], // Rantau
        ['-3.0000', '114.7500'], // Marabahan
        ['-2.4000', '115.1000'], // Paringin
    ];

    /** @var list<string> */
    private const EVENT_TITLES = [
        'Latihan Gabungan', 'Ujian Kenaikan Tingkat', 'Kejuaraan Daerah',
        'Sosialisasi Penerimaan Siswa Baru', 'Latihan Bersama Antar Ranting',
        'Bakti Sosial', 'Peringatan Hari Jadi', 'Dialog Warga',
        'Pelatihan Pelatih', 'Silaturahmi Pengurus',
    ];

    /** @var list<string> */
    private const VENUES = [
        'Padepokan PSHT Banjarmasin', 'Lapangan Murung Raya', 'GOR Hasanuddin',
        'Padepokan Cabang', 'Aula Ranting', 'Lapangan Desa', 'Gelanggang Rayon',
    ];

    /** @var list<string> */
    private const GALLERY_CATEGORIES = ['latihan', 'kegiatan', 'pembinaan'];

    /** @var list<string> */
    private const SHIRT_COLORS = [
        '#1f2937', '#991b1b', '#065f46', '#1e3a8a', '#7c2d12',
        '#4c1d95', '#0f766e', '#a16207',
    ];

    public function run(DemoSeedContext $context): void
    {
        $operatorByUnitId = $this->resolveUnitOperators($context);
        $fallbackOperator = $context->operator('operator-provinsi');
        $reviewers = $this->resolveReviewers($context);

        DB::transaction(function () use ($context, $operatorByUnitId, $fallbackOperator, $reviewers): void {
            $this->seedPages($context, $operatorByUnitId, $fallbackOperator, $reviewers);
            $this->seedEvents($context, $operatorByUnitId, $fallbackOperator, $reviewers);
            $this->seedTrainingLocations($context, $operatorByUnitId, $fallbackOperator, $reviewers);
            $this->seedGalleries($context, $operatorByUnitId, $fallbackOperator, $reviewers);
        });
    }

    /**
     * Map each seeded unit id to the operator assigned directly to it.
     *
     * @return array<int, User>
     */
    private function resolveUnitOperators(DemoSeedContext $context): array
    {
        $operators = [];

        foreach ($context->operators() as $operator) {
            foreach ($operator->unitAssignments()->where('is_active', true)->pluck('organization_unit_id') as $unitId) {
                $operators[(int) $unitId] = $operator;
            }
        }

        return $operators;
    }

    /**
     * An ordered pool of active, onboarded operators used as publishers so that
     * `published_by_id` never equals `submitted_by_id`.
     *
     * The pool excludes no one; {@see self::publisherFor()} skips the submitter.
     * The province operator leads the pool so branch content is reviewed at the
     * province level, matching the seeded hierarchy.
     *
     * @return list<User>
     */
    private function resolveReviewers(DemoSeedContext $context): array
    {
        $reviewers = [];
        $seen = [];

        $provinceOperator = $context->operator('operator-provinsi');
        $reviewers[] = $provinceOperator;
        $seen[$provinceOperator->id] = true;

        foreach ($context->operators() as $operator) {
            if (isset($seen[$operator->id])) {
                continue;
            }

            $reviewers[] = $operator;
            $seen[$operator->id] = true;
        }

        return $reviewers;
    }

    /**
     * Pick a deterministic publisher for a submitter, never the submitter.
     *
     * @param  list<User>  $reviewers
     */
    private function publisherFor(User $submitter, array $reviewers, int $salt = 0): User
    {
        $count = count($reviewers);

        for ($offset = 0; $offset < $count; $offset++) {
            $candidate = $reviewers[($salt + $offset) % $count];

            if ($candidate->id !== $submitter->id) {
                return $candidate;
            }
        }

        // Unreachable with a non-empty pool containing at least two operators.
        throw new LogicException('Seeder demo memerlukan minimal dua operator untuk review publikasi.');
    }

    /**
     * Pages: a general organization profile, the province history, and one
     * history per branch for six branches. Branch histories own their branch.
     *
     * @param  array<int, User>  $operatorByUnitId
     * @param  list<User>  $reviewers
     */
    private function seedPages(DemoSeedContext $context, array $operatorByUnitId, User $fallback, array $reviewers): void
    {
        $province = $context->province();
        $provinceOperator = $operatorByUnitId[$province->id] ?? $fallback;

        $this->upsertPage(
            slug: 'profil-organisasi-psht-kalimantan-selatan',
            kind: 'organization_profile',
            title: 'Profil Organisasi PSHT Kalimantan Selatan',
            unit: null,
            status: PublicationStatus::Published,
            submittedBy: $provinceOperator,
            publishedBy: $this->publisherFor($provinceOperator, $reviewers, 0),
        );

        $this->upsertPage(
            slug: 'sejarah-psht-kalimantan-selatan',
            kind: 'general_history',
            title: 'Sejarah PSHT Kalimantan Selatan',
            unit: null,
            status: PublicationStatus::Published,
            submittedBy: $provinceOperator,
            publishedBy: $this->publisherFor($provinceOperator, $reviewers, 1),
        );

        $branches = array_values($context->branches());
        $historyStatuses = [
            PublicationStatus::Published,
            PublicationStatus::Draft,
            PublicationStatus::PendingReview,
            PublicationStatus::Archived,
        ];

        for ($index = 0; $index < 6; $index++) {
            $branch = $branches[$index];
            $operator = $operatorByUnitId[$branch->id] ?? $fallback;
            $status = $historyStatuses[$index % count($historyStatuses)];

            $this->upsertPage(
                slug: 'sejarah-'.$this->slugify($branch->name),
                kind: 'branch_history',
                title: 'Sejarah '.$branch->name,
                unit: $branch,
                status: $status,
                submittedBy: $operator,
                publishedBy: $status === PublicationStatus::Published
                    ? $this->publisherFor($operator, $reviewers, $index + 2)
                    : null,
            );
        }

        $context->putPublicationKeys(Page::class, Page::query()->orderBy('id')->pluck('slug')->all());
    }

    private function upsertPage(
        string $slug,
        string $kind,
        string $title,
        ?OrganizationUnit $unit,
        PublicationStatus $status,
        User $submittedBy,
        ?User $publishedBy,
    ): Page {
        $isPublished = $status === PublicationStatus::Published;

        return Page::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'organization_unit_id' => $unit?->id,
                'kind' => $kind,
                'title' => $title,
                'body' => $this->pageBody($title),
                'status' => $status,
                'submitted_by_id' => $submittedBy->id,
                'published_by_id' => $isPublished ? $publishedBy?->id : null,
                'published_at' => $isPublished ? self::PUBLISHED_AT : null,
            ],
        );
    }

    /**
     * Activities distributed across branches and rantings, with a fixed
     * near-future and a fixed past block so `upcoming`/`past` both render.
     *
     * @param  array<int, User>  $operatorByUnitId
     * @param  list<User>  $reviewers
     */
    private function seedEvents(DemoSeedContext $context, array $operatorByUnitId, User $fallback, array $reviewers): void
    {
        $owners = array_values([...$context->branches(), ...$context->rantings()]);
        $ownerCount = count($owners);

        // Deterministic offsets: the first group is upcoming, the second past.
        $upcomingTotal = 20;

        for ($index = 0; $index < self::EVENT_TOTAL; $index++) {
            $owner = $owners[$index % $ownerCount];
            $operator = $operatorByUnitId[$owner->id] ?? $fallback;
            $status = PublicationStatus::cases()[$index % count(PublicationStatus::cases())];
            $isPublished = $status === PublicationStatus::Published;

            $isUpcoming = $index < $upcomingTotal;
            $dayOffset = $isUpcoming
                ? 7 + $index * 3            // +7, +10, ... into the future
                : -(14 + ($index - $upcomingTotal) * 5); // behind now

            $startsAt = now()->startOfDay()->addDays($dayOffset)->setTime(9, 0);
            $endsAt = (clone $startsAt)->setTime(12, 0);

            $title = self::EVENT_TITLES[$index % count(self::EVENT_TITLES)].' '.$owner->name;
            $slug = 'kegiatan-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT).'-'.$this->slugify($owner->name);

            $hasCover = $isPublished || $index % 3 === 0;

            Event::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'organization_unit_id' => $owner->id,
                    'title' => $title,
                    'description' => 'Kegiatan demo '.$title.' di lingkungan '.$owner->name.'. '
                        .'Agenda sintetis untuk keperluan presentasi portal.',
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'venue' => self::VENUES[$index % count(self::VENUES)],
                    'cover_path' => $hasCover ? $this->eventCoverPath($index) : null,
                    'cover_alt_text' => $hasCover ? 'Sampul kegiatan '.$title : null,
                    'status' => $status,
                    'submitted_by_id' => $operator->id,
                    'published_by_id' => $isPublished
                        ? $this->publisherFor($operator, $reviewers, $index)->id
                        : null,
                    'published_at' => $isPublished ? self::PUBLISHED_AT : null,
                ],
            );
        }

        $context->putPublicationKeys(Event::class, Event::query()->orderBy('id')->pluck('slug')->all());
    }

    /**
     * Training locations across branches, rantings, rayons, UKM, and
     * Komisariat with deterministic Kalimantan Selatan coordinates.
     *
     * @param  array<int, User>  $operatorByUnitId
     * @param  list<User>  $reviewers
     */
    private function seedTrainingLocations(DemoSeedContext $context, array $operatorByUnitId, User $fallback, array $reviewers): void
    {
        $owners = array_values([
            ...$context->branches(),
            ...$context->rantings(),
            ...array_values($context->rayons()),
            ...array_values($context->specialUnits()),
        ]);
        $ownerCount = count($owners);

        // Ensure every required owner type is represented from the first slots.
        $ownerTypes = [OrganizationUnitType::Cabang, OrganizationUnitType::Ranting, OrganizationUnitType::Rayon, OrganizationUnitType::Ukm, OrganizationUnitType::Komisariat];
        $forcedOwners = [];
        foreach ($ownerTypes as $type) {
            foreach ($owners as $owner) {
                if ($owner->type === $type) {
                    $forcedOwners[] = $owner;

                    break;
                }
            }
        }

        for ($index = 0; $index < self::LOCATION_TOTAL; $index++) {
            $owner = $forcedOwners[$index] ?? $owners[($index * 7) % $ownerCount];
            $operator = $operatorByUnitId[$owner->id] ?? $fallback;
            $status = PublicationStatus::cases()[$index % count(PublicationStatus::cases())];

            $coordinate = self::COORDINATES[$index % count(self::COORDINATES)];
            $latitude = number_format((float) $coordinate[0] + (($index % 10) / 1000), 7, '.', '');
            $longitude = number_format((float) $coordinate[1] + (($index % 10) / 1000), 7, '.', '');

            $slug = 'tempat-latihan-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT).'-'.$this->slugify($owner->name);

            // A deterministic published subset is verified; at least one
            // published location stays unverified to exercise verified-only.
            // Published rows are those with `index % 4 === 2`; every 3rd of them
            // (indices 2, 14, 26, ...) is deliberately left unverified.
            $isPublished = $status === PublicationStatus::Published;
            $isVerified = $isPublished && ($index % 12) !== 2;

            TrainingLocation::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'organization_unit_id' => $owner->id,
                    'name' => 'Padepokan '.$owner->name,
                    'address' => 'Jl. Demo No. '.($index + 1).', '.$owner->name.', Kalimantan Selatan',
                    'contact' => '08'.str_pad((string) (1000000000 + $index), 10, '0', STR_PAD_LEFT),
                    'schedule' => ['Senin & Rabu 19.30 WITA', 'Selasa & Kamis 19.30 WITA', 'Sabtu 15.30 WITA'][$index % 3],
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'status' => $status,
                    'verified_at' => $isVerified ? self::PUBLISHED_AT : null,
                    'submitted_by_id' => $operator->id,
                    'published_by_id' => $isPublished
                        ? $this->publisherFor($operator, $reviewers, $index)->id
                        : null,
                    'published_at' => $isPublished ? self::PUBLISHED_AT : null,
                ],
            );
        }

        $context->putPublicationKeys(TrainingLocation::class, TrainingLocation::query()->orderBy('id')->pluck('slug')->all());
    }

    /**
     * Galleries across branches, rantings, and special units, with all three
     * categories and all four publication states. Media rows are Task 6.
     *
     * The owner slot list is ordered branch → ranting → special so every UKM
     * and Komisariat is reachable; the first slots force one owner of each
     * type so the `ukm` and `komisariat` owner types are provably covered.
     *
     * @param  array<int, User>  $operatorByUnitId
     * @param  list<User>  $reviewers
     */
    private function seedGalleries(DemoSeedContext $context, array $operatorByUnitId, User $fallback, array $reviewers): void
    {
        $owners = array_values([
            ...$context->branches(),
            ...$context->rantings(),
            ...array_values($context->specialUnits()),
        ]);
        $ownerCount = count($owners);

        // Force one gallery per required owner type into the first slots so the
        // coverage invariant holds: branch, ranting, UKM, and Komisariat.
        $forcedOwners = [];
        foreach ([OrganizationUnitType::Cabang, OrganizationUnitType::Ranting, OrganizationUnitType::Ukm, OrganizationUnitType::Komisariat] as $type) {
            foreach ($owners as $owner) {
                if ($owner->type === $type) {
                    $forcedOwners[] = $owner;

                    break;
                }
            }
        }

        $categories = [
            GalleryCategory::Latihan,
            GalleryCategory::Kegiatan,
            GalleryCategory::Pembinaan,
        ];
        $statuses = PublicationStatus::cases();

        for ($index = 0; $index < self::GALLERY_TOTAL; $index++) {
            $owner = $forcedOwners[$index] ?? $owners[($index * 3) % $ownerCount];
            $operator = $operatorByUnitId[$owner->id] ?? $fallback;
            $category = $categories[$index % count($categories)];
            $status = $statuses[$index % count($statuses)];
            $isPublished = $status === PublicationStatus::Published;

            $title = 'Galeri '.$category->label().' '.$owner->name;
            $slug = 'galeri-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT).'-'.$category->value.'-'.$this->slugify($owner->name);

            Gallery::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'organization_unit_id' => $owner->id,
                    'title' => $title,
                    'description' => 'Dokumentasi '.$category->label().' '.$owner->name.'.',
                    'category' => $category,
                    'status' => $status,
                    'submitted_by_id' => $operator->id,
                    'published_by_id' => $isPublished
                        ? $this->publisherFor($operator, $reviewers, $index)->id
                        : null,
                    'published_at' => $isPublished ? self::PUBLISHED_AT : null,
                ],
            );
        }

        $context->putPublicationKeys(Gallery::class, Gallery::query()->orderBy('id')->pluck('slug')->all());
    }

    private function eventCoverPath(int $index): string
    {
        $extension = $index % 2 === 0 ? 'jpg' : 'webp';

        return 'private/event-covers/demo/kegiatan-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT).'.'.$extension;
    }

    private function pageBody(string $title): string
    {
        return $title.' merupakan naskah sintetis untuk keperluan demo portal PSHT Kalimantan Selatan. '
            .'Seluruh nama, angka, dan alamat bersifat fiktif dan tidak mewakili data pribadi nyata.'
            ."\n\n"
            .'Halaman ini disusun agar pengurus dapat meninjau alur publikasi, penyuntingan, dan penyaringan konten publik. '
            .'Teks disimpan sebagai teks biasa agar konsisten dengan tampilan editorial portal.';
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;

        return trim($value, '-');
    }
}
