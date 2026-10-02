<?php

namespace Tests\Feature;

use App\Enums\GalleryCategory;
use App\Enums\PublicationStatus;
use App\Models\Event;
use App\Models\Gallery;
use App\Models\Media;
use App\Models\OrganizationUnit;
use App\Models\Page;
use App\Models\TrainingLocation;
use App\OrganizationUnitType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicUiRenderTest extends TestCase
{
    use LazilyRefreshDatabase;

    // -----------------------------------------------------------------
    // Shared helpers
    // -----------------------------------------------------------------

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
            'alt_text' => 'Dokumentasi latihan',
            'is_public' => true,
            ...$attributes,
        ]);
    }

    // -----------------------------------------------------------------
    // Shared shell / theme
    // -----------------------------------------------------------------

    public function test_public_subpages_share_one_dark_editorial_shell(): void
    {
        foreach ([
            route('public.organization.index'),
            route('public.galleries.index'),
            route('public.events.index'),
            route('public.training-locations.index'),
        ] as $url) {
            $response = $this->get($url)->assertOk();

            $response->assertSee('border-b border-white/10 bg-psht-bg', false);
            $response->assertSee('psht-page-heading', false);
            $response->assertSee('psht-footer', false);
            $response->assertDontSee('bg-stone-50 text-stone-900', false);
        }
    }

    public function test_public_shell_marks_the_current_nav_link_with_aria_current(): void
    {
        $this->get(route('public.galleries.index'))
            ->assertOk()
            ->assertSee('aria-current="page"', false);
    }

    public function test_public_shell_keeps_an_accessible_mobile_menu(): void
    {
        $this->get(route('public.events.index'))
            ->assertOk()
            ->assertSee('<details', false)
            ->assertSee('aria-label="Buka menu navigasi"', false);
    }

    public function test_auth_activation_page_keeps_its_light_contrast_theme(): void
    {
        $this->get('/admin/activate')
            ->assertOk()
            ->assertSee('bg-stone-50 text-stone-900', false)
            ->assertDontSee('psht-footer', false)
            ->assertDontSee('bg-psht-bg', false);
    }

    // -----------------------------------------------------------------
    // Homepage contracts (preserved)
    // -----------------------------------------------------------------

    public function test_homepage_hero_and_carousel_hooks_are_preserved(): void
    {
        Storage::fake('public');
        $gallery = $this->publishedGallery(['title' => 'Album Beranda']);
        $this->publicMedia($gallery, ['alt_text' => 'Sampul beranda']);

        $response = $this->get(route('public.home'))->assertOk();

        $response->assertSee('videos/hero-martial-arts.mp4', false);
        $response->assertSee('data-gallery-carousel', false);
        $response->assertSee('data-carousel-prev', false);
        $response->assertSee('data-carousel-next', false);
        $response->assertSee('data-carousel-dot="0"', false);
    }

    public function test_homepage_event_card_uses_the_cover_route_only_when_a_cover_exists(): void
    {
        Storage::fake('local');

        $withCover = $this->publishedEvent([
            'title' => 'Kegiatan Bersampul',
            'slug' => 'kegiatan-bersampul',
            'cover_path' => 'private/event-covers/sampul.jpg',
            'cover_alt_text' => 'Sampul kegiatan bersampul',
            'starts_at' => now()->addDays(2),
        ]);
        $this->publishedEvent([
            'title' => 'Kegiatan Tanpa Sampul',
            'slug' => 'kegiatan-tanpa-sampul',
            'cover_path' => null,
            'starts_at' => now()->addDays(3),
        ]);

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee(route('public.events.cover', $withCover), false)
            ->assertSee('Sampul kegiatan bersampul', false)
            ->assertSee($withCover->title, false)
            ->assertDontSee(route('public.events.cover', 'kegiatan-tanpa-sampul'), false)
            ->assertDontSee('private/event-covers', false);
    }

    public function test_homepage_location_cards_link_to_the_detail_route(): void
    {
        $location = TrainingLocation::factory()->create([
            'name' => 'Padepokan Beranda',
            'slug' => 'padepokan-beranda',
            'status' => PublicationStatus::Published,
            'published_at' => now(),
            'verified_at' => now(),
        ]);

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee(route('public.training-locations.show', $location->slug), false);
    }

    // -----------------------------------------------------------------
    // Event list
    // -----------------------------------------------------------------

    public function test_event_list_renders_one_card_per_event_with_date_and_venue(): void
    {
        $event = $this->publishedEvent([
            'title' => 'Latihan Gabungan',
            'venue' => 'Padepokan PSHT Banjarmasin',
            'starts_at' => now()->addDays(4),
        ]);

        $response = $this->get(route('public.events.index'))->assertOk();

        $response->assertSee('Latihan Gabungan');
        $response->assertSee('Padepokan PSHT Banjarmasin');
        $response->assertSee(route('public.events.show', $event->slug), false);
        $response->assertSee('<time', false);
    }

    public function test_event_list_filter_links_reflect_the_active_period(): void
    {
        $this->publishedEvent(['starts_at' => now()->addDays(4)]);

        $upcoming = $this->get(route('public.events.index'))->assertOk();
        $upcoming->assertSee('data-event-filter="upcoming"', false);
        $upcoming->assertSee('aria-current="true"', false);
        $upcoming->assertSee('period=all', false);

        $past = $this->get(route('public.events.index', ['period' => 'past']))->assertOk();
        $past->assertSee('data-event-filter="past"', false);

        $all = $this->get(route('public.events.index', ['period' => 'all']))->assertOk();
        $all->assertSee('data-event-filter="all"', false);
    }

    public function test_event_list_cover_is_optional_and_never_leaks_the_private_path(): void
    {
        Storage::fake('local');
        $withCover = $this->publishedEvent([
            'title' => 'Bersampul',
            'slug' => 'bersampul',
            'cover_path' => 'private/event-covers/a.jpg',
            'cover_alt_text' => 'Sampul resmi',
            'starts_at' => now()->addDays(1),
        ]);
        $this->publishedEvent([
            'title' => 'Tanpa Sampul',
            'slug' => 'tanpa-sampul',
            'cover_path' => null,
            'starts_at' => now()->addDays(2),
        ]);

        $this->get(route('public.events.index'))
            ->assertOk()
            ->assertSee(route('public.events.cover', $withCover), false)
            ->assertSee('Sampul resmi', false)
            ->assertDontSee('private/event-covers', false)
            ->assertDontSee(route('public.events.cover', 'tanpa-sampul'), false);
    }

    public function test_event_list_empty_filter_offers_a_recovery_link(): void
    {
        // Past filter with only upcoming events: empty result with a recovery link.
        $this->publishedEvent(['starts_at' => now()->addDays(4)]);

        $this->get(route('public.events.index', ['period' => 'past']))
            ->assertOk()
            ->assertSee('Belum ada kegiatan pada rentang ini.')
            ->assertSee(route('public.events.index', ['period' => 'all']), false);
    }

    // -----------------------------------------------------------------
    // Event detail
    // -----------------------------------------------------------------

    public function test_event_detail_renders_breadcrumb_metadata_exactly_one_h1_and_no_map(): void
    {
        Storage::fake('local');
        $event = $this->publishedEvent([
            'title' => 'Detail Kegiatan',
            'slug' => 'detail-kegiatan',
            'venue' => 'Padepokan PSHT',
            'description' => 'Deskripsi lengkap kegiatan.',
            'cover_path' => 'private/event-covers/detail.jpg',
            'cover_alt_text' => 'Sampul detail kegiatan',
            'starts_at' => now()->addDays(5),
        ]);

        $response = $this->get(route('public.events.show', $event->slug))->assertOk();

        $this->assertSame(1, substr_count($response->getContent(), '<h1'), 'Exactly one H1 on event detail.');
        $response->assertSee('aria-label="Breadcrumb"', false);
        $response->assertSee('Detail Kegiatan');
        $response->assertSee('Padepokan PSHT');
        $response->assertSee('Deskripsi lengkap kegiatan.');
        $response->assertSee(route('public.events.cover', $event), false);
        $response->assertSee('Sampul detail kegiatan');
        $response->assertDontSee('private/event-covers', false);
        $response->assertDontSee('id="event-map', false);
        $response->assertDontSee('id="training-map', false);
    }

    public function test_event_detail_without_cover_renders_no_image_element(): void
    {
        $event = $this->publishedEvent([
            'slug' => 'tanpa-gambar',
            'cover_path' => null,
        ]);

        $this->get(route('public.events.show', $event->slug))
            ->assertOk()
            ->assertDontSee(route('public.events.cover', $event), false);
    }

    // -----------------------------------------------------------------
    // Gallery
    // -----------------------------------------------------------------

    public function test_gallery_category_filters_preserve_query_state(): void
    {
        $this->publishedGallery(['title' => 'Album Latihan', 'category' => GalleryCategory::Latihan]);

        $response = $this->get(route('public.galleries.index'))->assertOk();

        $response->assertSee('data-gallery-filter="all"', false);
        $response->assertSee('data-gallery-filter="latihan"', false);
        $response->assertSee('data-gallery-filter="kegiatan"', false);
        $response->assertSee('data-gallery-filter="pembinaan"', false);
        $response->assertSee(route('public.galleries.index', ['category' => 'latihan']), false);
        $response->assertSee('aria-current="true"', false);
    }

    public function test_gallery_media_uses_the_application_route_and_never_a_private_path(): void
    {
        Storage::fake('public');
        $gallery = $this->publishedGallery(['title' => 'Album Publik']);
        $public = $this->publicMedia($gallery, ['alt_text' => 'Foto publik']);
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
            ->assertSee(route('public.media.show', $public), false)
            ->assertSee('Foto publik')
            ->assertDontSee('private/gallery-media', false)
            ->assertDontSee('Dokumentasi internal rahasia');
    }

    public function test_gallery_empty_category_links_back_to_all(): void
    {
        $this->publishedGallery(['title' => 'Album Latihan', 'category' => GalleryCategory::Latihan]);

        $this->get(route('public.galleries.index', ['category' => 'pembinaan']))
            ->assertOk()
            ->assertSee(route('public.galleries.index', ['category' => 'all']), false);
    }

    public function test_gallery_renders_the_shared_native_dialog_with_lightbox_hooks(): void
    {
        Storage::fake('public');
        $gallery = $this->publishedGallery(['title' => 'Album Dialog']);
        $this->publicMedia($gallery, ['alt_text' => 'Foto dialog']);

        $response = $this->get(route('public.galleries.index'))->assertOk();

        $response->assertSee('<dialog', false);
        $response->assertSee('id="gallery-dialog"', false);
        $response->assertSee('data-gallery-open', false);
        $response->assertSee('data-gallery-dialog-image', false);
        $response->assertSee('data-gallery-dialog-caption', false);
        $response->assertSee('data-lightbox-prev', false);
        $response->assertSee('data-lightbox-next', false);
        $response->assertSee('aria-label="Tutup foto"', false);
    }

    public function test_gallery_dialog_places_close_and_navigation_in_separate_rows(): void
    {
        Storage::fake('public');
        $gallery = $this->publishedGallery(['title' => 'Album Baris Dialog']);
        $this->publicMedia($gallery, ['alt_text' => 'Foto baris dialog']);

        $html = $this->get(route('public.galleries.index'))->assertOk()->getContent();

        // The close button and the prev/next navigation live in two distinct
        // stacked wrappers (header row + navigation row) rather than two
        // absolutely positioned blocks sharing one top row, which is what
        // caused them to collide on a 320px viewport.
        $this->assertMatchesRegularExpression(
            '/data-lightbox-close/s',
            $html,
            'Dialog must expose a close control.',
        );
        $this->assertMatchesRegularExpression(
            '/class="gallery-dialog__header".*?data-lightbox-close.*?<\/div>\s*<img/s',
            $html,
            'Close control must sit in its own header row before the image.',
        );
        $this->assertMatchesRegularExpression(
            '/class="gallery-dialog__controls".*?data-lightbox-prev.*?data-lightbox-next/s',
            $html,
            'Prev/next controls must share a dedicated navigation row.',
        );
        // The two wrappers must be siblings (navigation is not nested inside
        // the header), so CSS can stack them instead of overlapping them.
        $header = str($html)->after('class="gallery-dialog__header"')->before('</div>')->toString();
        $this->assertStringContainsString('data-lightbox-close', $header, 'Header row must contain the close control.');
        $this->assertStringNotContainsString('data-lightbox-prev', $header, 'Header row must not contain the navigation controls.');
        $this->assertStringNotContainsString('data-lightbox-next', $header, 'Header row must not contain the navigation controls.');
    }

    public function test_gallery_pagination_retains_the_category_filter(): void
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

        $this->get(route('public.galleries.index', ['category' => 'kegiatan']))
            ->assertOk()
            ->assertSee('category=kegiatan', false);
    }

    // -----------------------------------------------------------------
    // Training locations
    // -----------------------------------------------------------------

    public function test_location_list_cards_link_to_detail_and_keep_map_hooks(): void
    {
        $unit = OrganizationUnit::factory()->create(['name' => 'Cabang Banjarmasin']);
        $location = TrainingLocation::factory()->create([
            'slug' => 'padepokan-ui',
            'name' => 'Padepokan UI',
            'address' => 'Jl. Merdeka 1',
            'schedule' => 'Selasa dan Jumat',
            'latitude' => '-3.3186000',
            'longitude' => '114.5944000',
            'organization_unit_id' => $unit->id,
            'status' => PublicationStatus::Published,
            'published_at' => now(),
            'verified_at' => now(),
        ]);

        $response = $this->get(route('public.training-locations.index'))->assertOk();

        $response->assertSee(route('public.training-locations.show', $location->slug), false);
        $response->assertSee('Padepokan UI');
        $response->assertSee('training-location-data', false);
        $response->assertSee('114.5944', false);
    }

    public function test_location_detail_renders_breadcrumb_single_h1_and_only_non_empty_fields(): void
    {
        $unit = OrganizationUnit::factory()->create(['name' => 'Cabang Banjarbaru']);
        $location = TrainingLocation::factory()->create([
            'slug' => 'padepokan-detail',
            'name' => 'Padepokan Detail',
            'address' => 'Jl. Contoh 10',
            'schedule' => 'Rabu, 19.30',
            'contact' => null,
            'organization_unit_id' => $unit->id,
            'status' => PublicationStatus::Published,
            'published_at' => now(),
            'verified_at' => now(),
        ]);

        $response = $this->get(route('public.training-locations.show', $location->slug))->assertOk();

        $this->assertSame(1, substr_count($response->getContent(), '<h1'), 'Exactly one H1 on location detail.');
        $response->assertSee('aria-label="Breadcrumb"', false);
        $response->assertSee('Padepokan Detail');
        $response->assertSee('Cabang Banjarbaru');
        $response->assertSee('Jl. Contoh 10');
        $response->assertSee('Rabu, 19.30');
        $response->assertDontSee('Kontak:');
        $response->assertSee(route('public.training-locations.index'), false);
    }

    public function test_location_detail_exposes_a_single_marker_map_with_attribution(): void
    {
        $location = TrainingLocation::factory()->create([
            'slug' => 'padepokan-peta',
            'name' => 'Padepokan Peta',
            'latitude' => '-3.3186000',
            'longitude' => '114.5944000',
            'status' => PublicationStatus::Published,
            'published_at' => now(),
            'verified_at' => now(),
        ]);

        $response = $this->get(route('public.training-locations.show', $location->slug))->assertOk();

        $response->assertSee('id="training-map-detail"', false);
        $response->assertSee('training-location-detail-data', false);
        $response->assertSee('OpenStreetMap', false);
    }

    // -----------------------------------------------------------------
    // Structure and profile
    // -----------------------------------------------------------------

    public function test_structure_page_distinguishes_parent_and_supervising_branch_with_dark_hierarchy(): void
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
            'name' => 'UKM Kampus',
            'type' => OrganizationUnitType::Ukm,
            'parent_id' => $province->id,
            'supervising_branch_id' => $branch->id,
        ]);

        $response = $this->get(route('public.organization.index'))->assertOk();

        $response->assertSee('Dibina oleh Cabang Banjarmasin');
        $response->assertSee('organization-tree', false);
        $response->assertSee('data-unit-name', false);
    }

    /**
     * Deeply nested units must not widen the page or collapse their names.
     * The local scroll container bounds page overflow; fixed card width
     * preserves readable content at every recursive depth.
     */
    public function test_structure_nested_unit_name_cannot_overflow_the_viewport(): void
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
        $ranting = OrganizationUnit::factory()->create([
            'name' => 'Ranting Banjarmasin Utara',
            'type' => OrganizationUnitType::Ranting,
            'parent_id' => $branch->id,
        ]);
        OrganizationUnit::factory()->create([
            'name' => 'Rayon ULM',
            'type' => OrganizationUnitType::Rayon,
            'parent_id' => $ranting->id,
        ]);

        $html = $this->get(route('public.organization.index'))->assertOk()->getContent();

        // Depth 1 and depth 2 render 1.25rem and 2.5rem of inline-start margin;
        // depth 3+ is capped at 2.5rem so cumulative indentation stops growing.
        $this->assertStringContainsString('margin-inline-start: 1.25rem', $html);
        $this->assertStringContainsString('margin-inline-start: 2.5rem', $html);
        $this->assertStringNotContainsString('margin-inline-start: 3.75rem', $html);
        $this->assertStringNotContainsString('margin-inline-start: 5rem', $html);
        // The tree is wrapped in a locally scrollable container so deep
        // indentation cannot widen the page.
        $this->assertStringContainsString('organization-tree-scroll', $html);
        // Valid hierarchy cards fill their parent; only over-depth data gets local-scroll floor.
        $this->assertStringContainsString('organization-unit-card', $html);
        $this->assertStringContainsString('style="width: 100%; margin-inline-start: 2.5rem"', $html);
        $this->assertStringNotContainsString('min-width: 40rem', $html);
        // The heading must be allowed to wrap inside its flex row.
        $this->assertMatchesRegularExpression(
            '/<h2 class="text-lg[^"]*break-words[^"]*">/',
            $html,
            'Nested unit headings must break long names instead of overflowing.',
        );
    }

    public function test_very_deep_structure_keeps_each_unit_card_wide_enough_to_read(): void
    {
        $parent = OrganizationUnit::factory()->create([
            'name' => 'Pengurus Provinsi',
            'type' => OrganizationUnitType::Provinsi,
        ]);

        for ($depth = 1; $depth <= 12; $depth++) {
            $parent = OrganizationUnit::factory()->create([
                'name' => "Unit tingkat {$depth}",
                'type' => $depth === 1 ? OrganizationUnitType::Cabang : OrganizationUnitType::Ranting,
                'parent_id' => $parent->id,
            ]);
        }

        $html = $this->get(route('public.organization.index'))->assertOk()->getContent();

        $this->assertSame(9, substr_count($html, 'min-width: 40rem;'));
        $this->assertSame(13, substr_count($html, 'style="width: 100%;'));
    }

    public function test_profile_page_uses_editorial_width_and_no_invented_sections(): void
    {
        $page = Page::factory()->create([
            'slug' => 'profil-ui',
            'title' => 'Profil UI',
            'body' => 'Naskah profil resmi yang telah diterbitkan.',
            'status' => PublicationStatus::Published,
            'published_at' => now(),
        ]);

        $response = $this->get(route('public.pages.show', $page->slug))->assertOk();

        $response->assertSee('editorial-drop-cap', false);
        $response->assertSee('Naskah profil resmi yang telah diterbitkan.');
        $response->assertDontSee('Sejarah organisasi</h2>', false);
        $response->assertSee('max-w-3xl', false);
    }
}
