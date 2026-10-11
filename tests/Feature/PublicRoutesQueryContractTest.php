<?php

namespace Tests\Feature;

use App\Enums\GalleryCategory;
use App\Enums\PublicationStatus;
use App\OrganizationUnitType;
use App\Models\Event;
use App\Models\Gallery;
use App\Models\Media;
use App\Models\OrganizationUnit;
use App\Models\TrainingLocation;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicRoutesQueryContractTest extends TestCase
{
    use LazilyRefreshDatabase;

    // -----------------------------------------------------------------
    // Shared helpers
    // -----------------------------------------------------------------

    /**
     * Bytes of a real, tiny 8x8 JPEG so filesystem MIME detection returns
     * `image/jpeg` rather than the fake disk's default octet-stream.
     */
    private function jpegBytes(): string
    {
        return base64_decode(
            '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0a'
            .'HBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAAIAAgBAREA/8QAHwAAAQUBAQEB'
            .'AQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1Fh'
            .'ByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZ'
            .'WmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXG'
            .'x8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/9oACAEBAAA/APn+v//Z',
        );
    }

    private function publishedEvent(array $attributes = []): Event
    {
        return Event::factory()->create([
            'status' => PublicationStatus::Published,
            'published_at' => now(),
            ...$attributes,
        ]);
    }

    private function publishedGallery(array $attributes = []): Gallery
    {
        return Gallery::factory()->create([
            'status' => PublicationStatus::Published,
            'published_at' => now(),
            ...$attributes,
        ]);
    }

    private function publicMedia(Gallery $gallery, array $attributes = []): Media
    {
        return Media::factory()->create([
            'gallery_id' => $gallery->id,
            'organization_unit_id' => $gallery->organization_unit_id,
            'disk' => 'public',
            'path' => 'public/gallery-media/'.fake()->unique()->uuid().'.jpg',
            'is_public' => true,
            ...$attributes,
        ]);
    }

    // -----------------------------------------------------------------
    // Events: period filter contract
    // -----------------------------------------------------------------

    public function test_events_index_defaults_to_upcoming_published_events(): void
    {
        $upcoming = $this->publishedEvent(['title' => 'Kegiatan Mendatang', 'starts_at' => now()->addDays(3)]);
        $past = $this->publishedEvent(['title' => 'Kegiatan Lampau', 'starts_at' => now()->subDays(3)]);
        $draft = Event::factory()->create(['title' => 'Draf Tersembunyi', 'starts_at' => now()->addDays(5)]);

        $response = $this->get(route('public.events.index'))->assertOk();

        $response->assertSee('Kegiatan Mendatang');
        $response->assertDontSee('Kegiatan Lampau');
        $response->assertDontSee('Draf Tersembunyi');
        $response->assertViewHas('events', fn ($events) => $events->pluck('id')->all() === [$upcoming->id]);
        $response->assertViewHas('period', 'upcoming');
    }

    public function test_events_index_period_past_only_returns_started_events(): void
    {
        $upcoming = $this->publishedEvent(['title' => 'Kegiatan Mendatang', 'starts_at' => now()->addDays(3)]);
        $past = $this->publishedEvent(['title' => 'Kegiatan Lampau', 'starts_at' => now()->subDays(3)]);

        $response = $this->get(route('public.events.index', ['period' => 'past']))->assertOk();

        $response->assertSee('Kegiatan Lampau');
        $response->assertDontSee('Kegiatan Mendatang');
        $response->assertViewHas('events', fn ($events) => $events->pluck('id')->all() === [$past->id]);
        $response->assertViewHas('period', 'past');
    }

    public function test_events_index_period_all_returns_both_without_order_restriction(): void
    {
        $upcoming = $this->publishedEvent(['title' => 'Kegiatan Mendatang', 'starts_at' => now()->addDays(3)]);
        $past = $this->publishedEvent(['title' => 'Kegiatan Lampau', 'starts_at' => now()->subDays(3)]);

        $response = $this->get(route('public.events.index', ['period' => 'all']))->assertOk();

        $response->assertSee('Kegiatan Mendatang');
        $response->assertSee('Kegiatan Lampau');
        $response->assertViewHas('events', function ($events) use ($past, $upcoming) {
            $ids = $events->pluck('id')->all();
            sort($ids);

            $expected = [$past->id, $upcoming->id];
            sort($expected);

            return $ids === $expected;
        });
        $response->assertViewHas('period', 'all');
    }

    public function test_events_index_treats_invalid_period_as_default_upcoming(): void
    {
        $this->publishedEvent(['title' => 'Kegiatan Mendatang', 'starts_at' => now()->addDays(3)]);
        $this->publishedEvent(['title' => 'Kegiatan Lampau', 'starts_at' => now()->subDays(3)]);

        $response = $this->get(route('public.events.index', ['period' => 'drop-table']))->assertOk();

        $response->assertSee('Kegiatan Mendatang');
        $response->assertDontSee('Kegiatan Lampau');
        $response->assertViewHas('period', 'upcoming');
    }

    public function test_events_index_orders_upcoming_chronologically_and_paginates_twelve_per_page(): void
    {
        foreach (range(1, 13) as $day) {
            $this->publishedEvent(['title' => "Kegiatan Hari {$day}", 'starts_at' => now()->addDays($day)]);
        }

        $pageOne = $this->get(route('public.events.index'))->assertOk();
        $pageOne->assertViewHas('events', function ($events) {
            $ordered = $events->pluck('starts_at')->values();

            return $events->count() === 12
                && $ordered->every(
                    fn ($startsAt, $index) => $index === 0 || $startsAt->greaterThan($ordered[$index - 1])
                );
        });

        // The 13th (and latest) event falls to page two.
        $this->get(route('public.events.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('Kegiatan Hari 13');
    }

    public function test_events_index_upcoming_breaks_tied_starts_at_by_id_ascending_without_skips_or_duplicates(): void
    {
        // 15 upcoming events all sharing the exact same starts_at, created
        // oldest-id-first, so the expected order is simply ascending id.
        $tied = now()->addDays(5)->startOfSecond();
        $ids = [];

        foreach (range(1, 15) as $index) {
            $ids[] = $this->publishedEvent([
                'title' => "Seri {$index}",
                'starts_at' => $tied->copy(),
            ])->id;
        }

        $pageOneIds = $this->collectEventIds(route('public.events.index', ['period' => 'upcoming']), 1);
        $pageTwoIds = $this->collectEventIds(route('public.events.index', ['period' => 'upcoming', 'page' => 2]), 2);

        $this->assertSame(array_slice($ids, 0, 12), $pageOneIds, 'Page 1 must be ids 1..12 in ascending order.');
        $this->assertSame(array_slice($ids, 12, 3), $pageTwoIds, 'Page 2 must be the remaining ids 13..15.');
        $this->assertSame([], array_intersect($pageOneIds, $pageTwoIds), 'Pages must not overlap.');
        $this->assertCount(15, array_merge($pageOneIds, $pageTwoIds), 'All tied events must appear exactly once.');
    }

    public function test_events_index_past_breaks_tied_starts_at_by_id_descending_recent_first(): void
    {
        $tied = now()->subDays(5)->startOfSecond();
        $ids = [];

        foreach (range(1, 15) as $index) {
            $ids[] = $this->publishedEvent([
                'title' => "Lampau Seri {$index}",
                'starts_at' => $tied->copy(),
            ])->id;
        }

        // Past is recent-first, and ties resolve to the newest (highest) id.
        $expectedDescending = array_reverse($ids);

        $pageOneIds = $this->collectEventIds(route('public.events.index', ['period' => 'past']), 1);
        $pageTwoIds = $this->collectEventIds(route('public.events.index', ['period' => 'past', 'page' => 2]), 2);

        $this->assertSame(array_slice($expectedDescending, 0, 12), $pageOneIds);
        $this->assertSame(array_slice($expectedDescending, 12, 3), $pageTwoIds);
        $this->assertSame([], array_intersect($pageOneIds, $pageTwoIds));
        $this->assertCount(15, array_merge($pageOneIds, $pageTwoIds));
    }

    public function test_events_index_all_breaks_tied_starts_at_by_id_ascending_deterministically(): void
    {
        // Mix of past and future events sharing the same starts_at; `all`
        // orders by starts_at ASC then id ASC regardless of period.
        $tied = now()->startOfSecond();
        $ids = [];

        foreach (range(1, 15) as $index) {
            $ids[] = $this->publishedEvent([
                'title' => "Semua Seri {$index}",
                'starts_at' => $tied->copy(),
            ])->id;
        }

        $pageOneIds = $this->collectEventIds(route('public.events.index', ['period' => 'all']), 1);
        $pageTwoIds = $this->collectEventIds(route('public.events.index', ['period' => 'all', 'page' => 2]), 2);

        $this->assertSame(array_slice($ids, 0, 12), $pageOneIds);
        $this->assertSame(array_slice($ids, 12, 3), $pageTwoIds);
        $this->assertSame([], array_intersect($pageOneIds, $pageTwoIds));
        $this->assertCount(15, array_merge($pageOneIds, $pageTwoIds));
    }

    public function test_events_index_preserves_period_filter_on_pagination(): void
    {
        foreach (range(1, 13) as $day) {
            $this->publishedEvent(['title' => "Lampau {$day}", 'starts_at' => now()->subDays($day)]);
        }

        $this->get(route('public.events.index', ['period' => 'past']))
            ->assertOk()
            ->assertViewHas('events', fn ($events) => str_contains($events->url(2), 'period=past'));
    }

    /**
     * Collect the ordered event ids returned in the `events` paginator for a
     * request, failing the test if the view data is missing.
     *
     * @return list<int>
     */
    private function collectEventIds(string $url, int $expectedPage): array
    {
        $ids = null;

        $this->get($url)
            ->assertOk()
            ->assertViewHas('events', function ($events) use (&$ids, $expectedPage) {
                if ($events->currentPage() !== $expectedPage) {
                    return false;
                }

                $ids = $events->pluck('id')->values()->all();

                return true;
            });

        $this->assertIsArray($ids, "No `events` paginator was resolved for {$url}.");

        return $ids;
    }

    public function test_event_detail_resolves_its_own_published_slug(): void
    {
        $event = $this->publishedEvent(['title' => 'Kegiatan Spesifik', 'slug' => 'kegiatan-spesifik']);

        $this->get(route('public.events.show', $event->slug))
            ->assertOk()
            ->assertSee('Kegiatan Spesifik');
    }

    public function test_event_detail_returns_404_for_draft_or_unknown_slug(): void
    {
        $draft = Event::factory()->create(['slug' => 'draf-kegiatan']);

        $this->get(route('public.events.show', 'draf-kegiatan'))->assertNotFound();
        $this->get(route('public.events.show', 'tidak-ada-kegiatan'))->assertNotFound();
        $this->assertSame(PublicationStatus::Draft, $draft->status);
    }

    // -----------------------------------------------------------------
    // Event cover route
    // -----------------------------------------------------------------

    public function test_event_cover_route_url_uses_the_event_slug_not_its_id(): void
    {
        $event = $this->publishedEvent(['slug' => 'kegiatan-bersampul']);

        $url = route('public.events.cover', $event);

        $this->assertSame('/kegiatan/kegiatan-bersampul/cover', parse_url($url, PHP_URL_PATH));
        $this->assertStringContainsString('kegiatan-bersampul', $url);
    }

    public function test_event_cover_route_streams_a_published_local_cover_as_inline_image(): void
    {
        Storage::fake('local');
        $event = $this->publishedEvent([
            'slug' => 'kegiatan-bersampul',
            'cover_path' => 'private/event-covers/sampul.jpg',
        ]);
        Storage::disk('local')->put('private/event-covers/sampul.jpg', $this->jpegBytes());

        // Resolve explicitly by slug to prove the endpoint binds on slug.
        $response = $this->get('/kegiatan/kegiatan-bersampul/cover')->assertOk();

        $this->assertSame('image/jpeg', $response->headers->get('content-type'));
        $this->assertSame('nosniff', $response->headers->get('x-content-type-options'));
        $this->assertStringContainsString('inline', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('sampul', (string) $response->headers->get('content-disposition'));
        $this->assertStringNotContainsString('private/event-covers/sampul.jpg', (string) $response->headers->get('content-disposition'));
    }

    public function test_event_cover_route_does_not_resolve_by_numeric_id_when_slug_differs(): void
    {
        Storage::fake('local');
        $event = $this->publishedEvent([
            'slug' => 'kegiatan-bersampul',
            'cover_path' => 'private/event-covers/sampul.jpg',
        ]);
        Storage::disk('local')->put('private/event-covers/sampul.jpg', $this->jpegBytes());

        // A numeric ID path must not accidentally resolve the record: the
        // binding is on slug, so `/kegiatan/{id}/cover` is a 404.
        $this->get('/kegiatan/'.$event->id.'/cover')->assertNotFound();
        $this->assertNotSame((string) $event->id, $event->slug);
    }

    public function test_event_cover_route_resolves_when_the_slug_is_numeric(): void
    {
        Storage::fake('local');
        // The URL occasionally may look like an ID; resolution must still be
        // slug-based, so a slug of "12345" only resolves when it is that
        // event's actual slug.
        $matching = $this->publishedEvent(['slug' => '12345', 'cover_path' => 'private/event-covers/a.jpg']);
        $other = $this->publishedEvent(['slug' => 'kegiatan-lain', 'cover_path' => 'private/event-covers/b.jpg']);
        Storage::disk('local')->put('private/event-covers/a.jpg', $this->jpegBytes());
        Storage::disk('local')->put('private/event-covers/b.jpg', $this->jpegBytes());

        $this->get('/kegiatan/12345/cover')->assertOk();

        // The other event's numeric ID (which differs from the slug "12345")
        // must not resolve.
        $this->assertNotSame('12345', (string) $other->id);
        $this->get('/kegiatan/'.$other->id.'/cover')->assertNotFound();
    }

    public function test_event_cover_route_returns_404_for_draft_event_with_a_valid_cover(): void
    {
        Storage::fake('local');
        $event = Event::factory()->create([
            'slug' => 'kegiatan-draf',
            'status' => PublicationStatus::Draft,
            'cover_path' => 'private/event-covers/draf.jpg',
        ]);
        Storage::disk('local')->put('private/event-covers/draf.jpg', $this->jpegBytes());

        $this->get('/kegiatan/kegiatan-draf/cover')->assertNotFound();
    }

    public function test_event_cover_route_returns_404_for_published_event_without_a_cover(): void
    {
        Storage::fake('local');
        $event = $this->publishedEvent(['slug' => 'kegiatan-tanpa-sampul', 'cover_path' => null]);

        $this->get('/kegiatan/kegiatan-tanpa-sampul/cover')->assertNotFound();
    }

    public function test_event_cover_route_returns_404_when_the_local_file_is_missing(): void
    {
        Storage::fake('local');
        $event = $this->publishedEvent([
            'slug' => 'kegiatan-hilang',
            'cover_path' => 'private/event-covers/hilang.jpg',
        ]);

        $this->get('/kegiatan/kegiatan-hilang/cover')->assertNotFound();
    }

    public function test_event_cover_route_returns_404_for_an_unsafe_persisted_path(): void
    {
        Storage::fake('local');
        // A sentinel file sitting outside the event-cover prefix that a
        // traversal path would resolve to.
        Storage::disk('local')->put('private/gallery-media/sentinel.jpg', $this->jpegBytes());
        $event = $this->publishedEvent([
            'slug' => 'kegiatan-tidak-aman',
            'cover_path' => 'private/event-covers/../gallery-media/sentinel.jpg',
        ]);

        $this->get('/kegiatan/kegiatan-tidak-aman/cover')->assertNotFound();

        Storage::disk('local')->assertExists('private/gallery-media/sentinel.jpg');
    }

    public function test_event_list_html_never_leaks_the_private_cover_path(): void
    {
        Storage::fake('local');
        $this->publishedEvent([
            'title' => 'Kegiatan Bersampul',
            'cover_path' => 'private/event-covers/rahasia.jpg',
            'starts_at' => now()->addDays(2),
        ]);

        $this->get(route('public.events.index'))
            ->assertOk()
            ->assertDontSee('private/event-covers', false);
    }

    // -----------------------------------------------------------------
    // Gallery: category filter contract
    // -----------------------------------------------------------------

    public function test_gallery_index_defaults_to_all_categories_and_includes_legacy_null(): void
    {
        $latihan = $this->publishedGallery(['title' => 'Album Latihan', 'category' => GalleryCategory::Latihan]);
        $legacy = $this->publishedGallery(['title' => 'Album Warisan', 'category' => null]);

        $response = $this->get(route('public.galleries.index'))->assertOk();

        $response->assertSee('Album Latihan');
        $response->assertSee('Album Warisan');
        $response->assertViewHas('category', 'all');
        $response->assertViewHas('galleries', function ($galleries) use ($latihan, $legacy) {
            $ids = $galleries->pluck('id')->all();
            sort($ids);

            $expected = [$latihan->id, $legacy->id];
            sort($expected);

            return $ids === $expected;
        });
    }

    public function test_gallery_index_filters_by_allowlisted_category(): void
    {
        $this->publishedGallery(['title' => 'Album Latihan', 'category' => GalleryCategory::Latihan]);
        $this->publishedGallery(['title' => 'Album Kegiatan', 'category' => GalleryCategory::Kegiatan]);
        $this->publishedGallery(['title' => 'Album Warisan', 'category' => null]);

        $response = $this->get(route('public.galleries.index', ['category' => 'latihan']))->assertOk();

        $response->assertSee('Album Latihan');
        $response->assertDontSee('Album Kegiatan');
        $response->assertDontSee('Album Warisan');
        $response->assertViewHas('category', 'latihan');
    }

    public function test_gallery_index_legacy_null_is_excluded_from_specific_categories(): void
    {
        $this->publishedGallery(['title' => 'Album Warisan', 'category' => null]);

        $this->get(route('public.galleries.index', ['category' => 'pembinaan']))
            ->assertOk()
            ->assertDontSee('Album Warisan');
    }

    public function test_gallery_index_treats_invalid_category_as_default_all(): void
    {
        $this->publishedGallery(['title' => 'Album Latihan', 'category' => GalleryCategory::Latihan]);
        $this->publishedGallery(['title' => 'Album Warisan', 'category' => null]);

        $response = $this->get(route('public.galleries.index', ['category' => 'drop-table']))->assertOk();

        $response->assertSee('Album Latihan');
        $response->assertSee('Album Warisan');
        $response->assertViewHas('category', 'all');
    }

    public function test_gallery_index_only_loads_public_media_ordered_by_sort_order_then_id(): void
    {
        Storage::fake('public');
        $gallery = $this->publishedGallery(['title' => 'Album Urut']);

        $late = $this->publicMedia($gallery, ['sort_order' => 5, 'path' => 'public/gallery-media/late.jpg', 'alt_text' => 'Paling akhir']);
        $first = $this->publicMedia($gallery, ['sort_order' => 1, 'path' => 'public/gallery-media/first.jpg', 'alt_text' => 'Paling awal']);

        $private = Media::factory()->create([
            'gallery_id' => $gallery->id,
            'organization_unit_id' => $gallery->organization_unit_id,
            'disk' => 'local',
            'path' => 'private/gallery-media/rahasia.jpg',
            'is_public' => false,
            'sort_order' => 0,
        ]);
        $hidden = $this->publicMedia($gallery, ['is_public' => false, 'sort_order' => 2, 'path' => 'public/gallery-media/hidden.jpg']);
        $localOnly = Media::factory()->create([
            'gallery_id' => $gallery->id,
            'organization_unit_id' => $gallery->organization_unit_id,
            'disk' => 'local',
            'path' => 'public/gallery-media/not-public-disk.jpg',
            'is_public' => true,
            'sort_order' => 3,
        ]);

        $this->get(route('public.galleries.index'))
            ->assertOk()
            ->assertViewHas('galleries', function ($galleries) use ($gallery, $first, $late) {
                $album = $galleries->firstWhere('id', $gallery->id);

                return $album !== null
                    && $album->relationLoaded('media')
                    && $album->media->pluck('id')->all() === [$first->id, $late->id];
            });
    }

    public function test_gallery_index_renders_each_album_with_one_lightbox_cover(): void
    {
        Storage::fake('public');
        $gallery = $this->publishedGallery(['title' => 'Album Grid']);
        $media = $this->publicMedia($gallery, [
            'path' => 'public/gallery-media/grid.webp',
            'alt_text' => 'Sampul album grid',
        ]);

        $html = $this->get(route('public.galleries.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('alt="Sampul album grid"', $html);
        $this->assertStringContainsString(route('public.media.show', $media), $html);
        $this->assertStringContainsString('data-gallery-open', $html);
        $this->assertStringContainsString('Album Grid', $html);
    }

    public function test_structure_page_renders_every_unit_with_tree_hooks_for_progressive_enhancement(): void
    {
        $province = OrganizationUnit::factory()->create([
            'name' => 'Pengurus Provinsi Kalimantan Selatan',
            'type' => OrganizationUnitType::Provinsi,
        ]);
        $branch = OrganizationUnit::factory()->create([
            'name' => 'Cabang Banjarmasin',
            'type' => OrganizationUnitType::Cabang,
            'parent_id' => $province->id,
        ]);
        OrganizationUnit::factory()->create([
            'name' => 'Ranting Banjarmasin Timur',
            'type' => OrganizationUnitType::Ranting,
            'parent_id' => $branch->id,
        ]);

        $html = $this->get(route('public.organization.index'))->assertOk()->getContent();

        // Every unit is present in the server-rendered HTML before JS runs, so
        // the page stays complete for crawlers and no-JS visitors.
        $this->assertStringContainsString('Pengurus Provinsi Kalimantan Selatan', $html);
        $this->assertStringContainsString('Cabang Banjarmasin', $html);
        $this->assertStringContainsString('Ranting Banjarmasin Timur', $html);

        // The progressive-enhancement contract the tree script relies on.
        $this->assertStringContainsString('id="organization-tree"', $html);
        $this->assertStringContainsString('data-tree-node', $html);
        $this->assertStringContainsString('data-tree-toggle', $html);
        $this->assertSame(3, substr_count($html, 'data-unit-name='));
        $this->assertStringContainsString('data-unit-type="cabang"', $html);

        // Only the units that own children expose a toggle.
        $this->assertSame(2, substr_count($html, 'data-tree-toggle'));
    }

    public function test_structure_page_renders_the_tree_toolbar_for_search_and_filtering(): void
    {
        OrganizationUnit::factory()->create([
            'name' => 'Pengurus Provinsi Kalimantan Selatan',
            'type' => OrganizationUnitType::Provinsi,
        ]);

        $this->get(route('public.organization.index'))
            ->assertOk()
            ->assertSee('id="organization-search"', false)
            ->assertSee('data-tree-filter="all"', false)
            ->assertSee('data-tree-filter="cabang"', false)
            ->assertSee('data-tree-expand-all', false)
            ->assertSee('data-tree-collapse-all', false)
            ->assertSee('role="status"', false);
    }

    public function test_gallery_index_orders_by_published_at_desc_and_paginates_twelve_while_preserving_category(): void
    {
        Storage::fake('public');

        foreach (range(1, 13) as $index) {
            $gallery = $this->publishedGallery([
                'title' => "Album Kegiatan {$index}",
                'category' => GalleryCategory::Kegiatan,
                'published_at' => now()->subDays($index),
            ]);
            $this->publicMedia($gallery, ['path' => "public/gallery-media/cover-{$index}.jpg"]);
        }

        $this->publishedGallery(['title' => 'Album Latihan Lain', 'category' => GalleryCategory::Latihan, 'published_at' => now()]);

        $pageOne = $this->get(route('public.galleries.index', ['category' => 'kegiatan']))->assertOk();

        $pageOne->assertViewHas('galleries', function ($galleries) {
            return $galleries->count() === 12
                && $galleries->pluck('title')->first() === 'Album Kegiatan 1'
                && str_contains($galleries->url(2), 'category=kegiatan');
        });

        $this->get(route('public.galleries.index', ['category' => 'kegiatan', 'page' => 2]))
            ->assertOk()
            ->assertSee('Album Kegiatan 13');
    }

    public function test_gallery_index_suppresses_media_that_would_leak_private_paths(): void
    {
        Storage::fake('public');
        $gallery = $this->publishedGallery(['title' => 'Album Aman']);
        Media::factory()->create([
            'gallery_id' => $gallery->id,
            'organization_unit_id' => $gallery->organization_unit_id,
            'disk' => 'local',
            'path' => 'private/gallery-media/rahasia.jpg',
            'alt_text' => 'Dokumentasi internal rahasia',
            'is_public' => false,
        ]);

        $this->get(route('public.galleries.index'))
            ->assertOk()
            ->assertDontSee('rahasia.jpg', false)
            ->assertDontSee('Dokumentasi internal rahasia');
    }

    // -----------------------------------------------------------------
    // PublicMediaController application route predicate
    // -----------------------------------------------------------------

    public function test_public_media_route_serves_public_media_of_a_published_album(): void
    {
        Storage::fake('public');
        $gallery = $this->publishedGallery();
        $media = $this->publicMedia($gallery);
        Storage::disk('public')->put($media->path, $this->jpegBytes());

        $this->get(route('public.media.show', $media))->assertOk();
    }

    public function test_public_media_route_returns_404_when_the_album_is_draft(): void
    {
        Storage::fake('public');
        $gallery = Gallery::factory()->create(['status' => PublicationStatus::Draft, 'published_at' => null]);
        $media = $this->publicMedia($gallery);
        Storage::disk('public')->put($media->path, $this->jpegBytes());

        $this->get(route('public.media.show', $media))->assertNotFound();
    }

    public function test_public_media_route_returns_404_when_the_album_is_published_without_published_at(): void
    {
        Storage::fake('public');
        $gallery = Gallery::factory()->create([
            'status' => PublicationStatus::Published,
            'published_at' => null,
        ]);
        $media = $this->publicMedia($gallery);
        Storage::disk('public')->put($media->path, $this->jpegBytes());

        $this->get(route('public.media.show', $media))->assertNotFound();
    }

    public function test_public_media_route_returns_404_when_media_is_not_public(): void
    {
        Storage::fake('public');
        $gallery = $this->publishedGallery();
        $media = $this->publicMedia($gallery, ['is_public' => false]);
        Storage::disk('public')->put($media->path, $this->jpegBytes());

        $this->get(route('public.media.show', $media))->assertNotFound();
    }

    public function test_public_media_route_returns_404_when_media_is_not_on_the_public_disk(): void
    {
        Storage::fake('public');
        $gallery = $this->publishedGallery();
        $media = $this->publicMedia($gallery, ['disk' => 'local', 'path' => 'private/gallery-media/local.jpg']);

        $this->get(route('public.media.show', $media))->assertNotFound();
    }

    public function test_public_media_route_returns_404_for_media_whose_album_was_hidden(): void
    {
        Storage::fake('public');
        $gallery = $this->publishedGallery();
        $media = $this->publicMedia($gallery);
        Storage::disk('public')->put($media->path, $this->jpegBytes());

        $this->get(route('public.media.show', $media))->assertOk();

        // Hiding the album (status back to draft) must immediately take its
        // media off the application route even though the record and its file
        // remain public on disk.
        $gallery->update(['status' => PublicationStatus::Draft]);

        $this->get(route('public.media.show', $media))->assertNotFound();
        Storage::disk('public')->assertExists($media->path);
    }

    // -----------------------------------------------------------------
    // Training location detail route
    // -----------------------------------------------------------------

    public function test_training_location_detail_renders_a_verified_published_location_with_its_unit(): void
    {
        $unit = OrganizationUnit::factory()->create(['name' => 'Cabang Banjarmasin']);
        $location = TrainingLocation::factory()->create([
            'slug' => 'padepokan-banjarmasin',
            'name' => 'Padepokan Banjarmasin',
            'address' => 'Jl. Merdeka 1',
            'organization_unit_id' => $unit->id,
            'status' => PublicationStatus::Published,
            'published_at' => now(),
            'verified_at' => now(),
        ]);

        $response = $this->get(route('public.training-locations.show', $location->slug))->assertOk();

        $response->assertSee('Padepokan Banjarmasin');
        $response->assertSee('Cabang Banjarmasin');
        $response->assertViewHas(
            'location',
            fn ($resolved) => $resolved->id === $location->id && $resolved->relationLoaded('organizationUnit'),
        );
    }

    public function test_training_location_detail_returns_404_for_missing_slug(): void
    {
        $this->get(route('public.training-locations.show', 'tidak-ada'))->assertNotFound();
    }

    public function test_training_location_detail_returns_404_for_draft_pending_or_unverified_locations(): void
    {
        $draft = TrainingLocation::factory()->create(['slug' => 'draf', 'status' => PublicationStatus::Draft, 'verified_at' => now()]);
        $pending = TrainingLocation::factory()->create(['slug' => 'pending', 'status' => PublicationStatus::PendingReview, 'verified_at' => now()]);
        $unverified = TrainingLocation::factory()->create(['slug' => 'belum-verifikasi', 'status' => PublicationStatus::Published, 'published_at' => now(), 'verified_at' => null]);
        $noPublishedAt = TrainingLocation::factory()->create(['slug' => 'tanpa-published-at', 'status' => PublicationStatus::Published, 'published_at' => null, 'verified_at' => now()]);

        foreach ([$draft, $pending, $unverified, $noPublishedAt] as $location) {
            $this->get(route('public.training-locations.show', $location->slug))->assertNotFound();
        }
    }

    public function test_training_location_detail_does_not_fall_back_to_a_default_location(): void
    {
        $verified = TrainingLocation::factory()->create([
            'slug' => 'satu-satunya',
            'name' => 'Padepokan Satu-satunya',
            'status' => PublicationStatus::Published,
            'published_at' => now(),
            'verified_at' => now(),
        ]);

        $this->get(route('public.training-locations.show', 'satu-satunya'))->assertOk()->assertSee('Padepokan Satu-satunya');
        $this->get(route('public.training-locations.show', 'slug-lain'))->assertNotFound();
    }

    public function test_training_location_detail_exposes_no_map_data_for_an_invalid_slug(): void
    {
        TrainingLocation::factory()->create([
            'slug' => 'tempat-valid',
            'status' => PublicationStatus::Published,
            'published_at' => now(),
            'verified_at' => now(),
        ]);

        $response = $this->get(route('public.training-locations.show', 'tempat-tidak-valid'))->assertNotFound();

        $response->assertDontSee('training-location-data', false);
        $response->assertDontSee('data-map', false);
        $response->assertDontSee('114.5944');
    }

    public function test_training_location_index_cards_link_to_the_detail_route_and_keep_map_payload(): void
    {
        $location = TrainingLocation::factory()->create([
            'slug' => 'padepokan-index',
            'name' => 'Padepokan Index',
            'latitude' => '-3.3186000',
            'longitude' => '114.5944000',
            'status' => PublicationStatus::Published,
            'published_at' => now(),
            'verified_at' => now(),
        ]);

        $response = $this->get(route('public.training-locations.index'))->assertOk();

        $response->assertSee(route('public.training-locations.show', $location->slug), false);
        $response->assertSee('training-location-data', false);
        $response->assertSee('114.5944', false);
    }
}
