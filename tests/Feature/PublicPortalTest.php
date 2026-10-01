<?php

namespace Tests\Feature;

use App\Enums\PublicationStatus;
use App\Models\Event;
use App\Models\Gallery;
use App\Models\Media;
use App\Models\OrganizationUnit;
use App\Models\Page;
use App\Models\Person;
use App\Models\TrainingLocation;
use App\OrganizationUnitType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicPortalTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_homepage_lists_only_published_content(): void
    {
        Page::factory()->create([
            'slug' => 'sejarah-umum',
            'title' => 'Sejarah PSHT',
            'status' => PublicationStatus::Published,
            'published_at' => now(),
        ]);
        Page::factory()->create([
            'title' => 'Konten Rahasia',
            'status' => PublicationStatus::Draft,
        ]);
        Event::factory()->create([
            'title' => 'Latihan Bersama',
            'status' => PublicationStatus::Published,
            'starts_at' => now()->addDays(7),
            'published_at' => now(),
        ]);

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('Sejarah PSHT')
            ->assertSee('Latihan Bersama')
            ->assertDontSee('Konten Rahasia');
    }

    public function test_structure_page_distinguishes_formal_parent_and_supervising_branch(): void
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

        $this->get(route('public.organization.index'))
            ->assertOk()
            ->assertSee('Pengurus Provinsi Kalimantan Selatan')
            ->assertSee('UKM Kampus')
            ->assertSee('Dibina oleh Cabang Banjarmasin');
    }

    public function test_gallery_shows_only_public_media_from_published_album(): void
    {
        Storage::fake('public');
        $gallery = Gallery::factory()->create([
            'title' => 'Dokumentasi Kegiatan',
            'status' => PublicationStatus::Published,
            'published_at' => now(),
        ]);
        Media::factory()->create([
            'gallery_id' => $gallery->id,
            'organization_unit_id' => $gallery->organization_unit_id,
            'disk' => 'public',
            'path' => 'gallery/public.jpg',
            'alt_text' => 'Latihan bersama',
            'is_public' => true,
        ]);
        Media::factory()->create([
            'gallery_id' => $gallery->id,
            'organization_unit_id' => $gallery->organization_unit_id,
            'disk' => 'local',
            'path' => 'private/internal.jpg',
            'alt_text' => 'Dokumentasi internal rahasia',
            'is_public' => false,
        ]);

        $this->get(route('public.galleries.index'))
            ->assertOk()
            ->assertSee('Dokumentasi Kegiatan')
            ->assertSee('Latihan bersama')
            ->assertDontSee('Dokumentasi internal rahasia');
    }

    public function test_homepage_lists_only_verified_published_training_locations(): void
    {
        Storage::fake('public');

        foreach (['Latihan Alfa', 'Latihan Bravo', 'Latihan Charlie', 'Latihan Delta'] as $name) {
            TrainingLocation::factory()->create([
                'name' => $name,
                'status' => PublicationStatus::Published,
                'published_at' => now(),
                'verified_at' => now(),
            ]);
        }

        TrainingLocation::factory()->create([
            'name' => 'Latihan Belum Terverifikasi',
            'status' => PublicationStatus::Published,
            'published_at' => now(),
            'verified_at' => null,
        ]);

        $gallery = Gallery::factory()->create([
            'title' => 'Album Beranda',
            'status' => PublicationStatus::Published,
            'published_at' => now(),
        ]);
        Media::factory()->create([
            'gallery_id' => $gallery->id,
            'organization_unit_id' => $gallery->organization_unit_id,
            'disk' => 'public',
            'path' => 'gallery/beranda-publik.jpg',
            'alt_text' => 'Sampul publik beranda',
            'is_public' => true,
        ]);
        Media::factory()->create([
            'gallery_id' => $gallery->id,
            'organization_unit_id' => $gallery->organization_unit_id,
            'disk' => 'local',
            'path' => 'private/beranda-rahasia.jpg',
            'alt_text' => 'Sampul privat beranda',
            'is_public' => false,
        ]);

        $response = $this->get(route('public.home'))->assertOk();

        $response->assertViewHas('trainingLocations', function ($locations) {
            $names = $locations->pluck('name')->all();
            $selected = ['id', 'name', 'slug', 'address', 'latitude', 'longitude'];

            return $locations->count() === 3
                && ! in_array('Latihan Delta', $names, true)
                && ! in_array('Latihan Belum Terverifikasi', $names, true)
                && $locations->every(fn ($location) => empty(array_diff(
                    array_keys($location->getAttributes()),
                    $selected,
                )));
        });

        $response->assertViewHas('galleries', function ($galleries) {
            return $galleries->pluck('media')->flatten()->every(
                fn ($media) => $media->path === 'gallery/beranda-publik.jpg'
                    && $media->only(['id', 'gallery_id', 'path', 'alt_text']) === $media->getAttributes(),
            );
        });
    }

    public function test_training_locations_page_exposes_no_member_data(): void
    {
        Person::factory()->create(['name' => 'Nama Anggota Privat']);
        TrainingLocation::factory()->create([
            'name' => 'Latihan Banjarbaru',
            'status' => PublicationStatus::Published,
            'published_at' => now(),
            'verified_at' => now(),
        ]);

        $this->get(route('public.training-locations.index'))
            ->assertOk()
            ->assertSee('Latihan Banjarbaru')
            ->assertDontSee('Nama Anggota Privat');
    }

    public function test_homepage_hero_uses_local_media_and_expected_structure(): void
    {
        $response = $this->get(route('public.home'))->assertOk();

        $response->assertSee('<h1', false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));

        $response->assertSee('Persaudaraan dan pembinaan di Kalimantan Selatan.', false);
        $response->assertSee('Portal resmi PSHT Kalimantan Selatan untuk mengenal organisasi, kegiatan, galeri, dan tempat latihan.', false);

        $response->assertSee('videos/hero-martial-arts.mp4', false);
        $response->assertSee('images/hero-martial-arts-poster.webp', false);
        $response->assertSee('preload="metadata"', false);
        $response->assertSee('muted', false);
        $response->assertSee('playsinline', false);
        $response->assertSee('loop', false);
        $response->assertSee('aria-hidden="true"', false);
        $response->assertDontSee(' autoplay', false);

        $response->assertSee('Jelajahi struktur', false);
        $response->assertSee('Temukan tempat latihan', false);
        $response->assertSee(route('public.organization.index'), false);
        $response->assertSee(route('public.training-locations.index'), false);
        $response->assertSee(route('public.events.index'), false);
        $response->assertSee(route('public.galleries.index'), false);
    }

    public function test_homepage_renders_empty_states_when_no_content_published(): void
    {
        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('Informasi sejarah belum diterbitkan.')
            ->assertSee('Belum ada kegiatan mendatang.')
            ->assertSee('Belum ada dokumentasi yang diterbitkan.')
            ->assertSee('Informasi tempat latihan belum tersedia.');
    }

    public function test_homepage_shows_verified_locations_only(): void
    {
        TrainingLocation::factory()->create([
            'name' => 'Latihan Terverifikasi',
            'status' => PublicationStatus::Published,
            'published_at' => now(),
            'verified_at' => now(),
        ]);
        TrainingLocation::factory()->create([
            'name' => 'Latihan Belum Verifikasi',
            'status' => PublicationStatus::Published,
            'published_at' => now(),
            'verified_at' => null,
        ]);

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('Latihan Terverifikasi')
            ->assertDontSee('Latihan Belum Verifikasi');
    }

    public function test_homepage_uses_homepage_navigation_variant(): void
    {
        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('<details', false)
            ->assertSee('max-w-none p-0', false);
    }

    public function test_events_page_keeps_shared_navigation_and_no_homepage_video(): void
    {
        $this->get(route('public.events.index'))
            ->assertOk()
            ->assertSee('Navigasi utama')
            ->assertSee('Struktur')
            ->assertSee('Galeri')
            ->assertSee('Kegiatan')
            ->assertSee('Tempat latihan')
            ->assertSee('mx-auto max-w-6xl px-4 py-10', false)
            ->assertDontSee('hero-martial-arts.mp4')
            ->assertDontSee('<details', false);
    }

    public function test_homepage_supplies_up_to_eight_published_galleries_with_public_covers(): void
    {
        // Create galleries newest-first (i=1 newest, i=9 oldest among the public set).
        // Controller orders by publishedAt DESC and caps at 8, so galleries 1–8 must appear;
        // gallery-9 (Album Foto 9, published 9 days ago) must be absent.
        $base = now()->startOfDay();

        for ($i = 1; $i <= 9; $i++) {
            $g = Gallery::factory()->create([
                'title'        => "Album Foto {$i}",
                'status'       => PublicationStatus::Published,
                'published_at' => $base->copy()->subDays($i),
            ]);
            Media::factory()->create([
                'gallery_id'           => $g->id,
                'organization_unit_id' => $g->organization_unit_id,
                'disk'                 => 'public',
                'path'                 => "gallery/cover-{$i}.jpg",
                'alt_text'             => "Sampul {$i}",
                'is_public'            => true,
            ]);
        }

        // Published gallery with ONLY private media — its cover must not appear at all.
        $private = Gallery::factory()->create([
            'title'        => 'Album Privat',
            'status'       => PublicationStatus::Published,
            'published_at' => $base->copy()->subDays(20),
        ]);
        Media::factory()->create([
            'gallery_id'           => $private->id,
            'organization_unit_id' => $private->organization_unit_id,
            'disk'                 => 'local',
            'path'                 => 'private/secret.jpg',
            'alt_text'             => 'Rahasia',
            'is_public'            => false,
        ]);

        $response = $this->get(route('public.home'))->assertOk();

        $response->assertViewHas('galleries', function ($galleries) {
            // Exactly 8 albums returned (the 9th and private-only are excluded).
            if ($galleries->count() !== 8) {
                return false;
            }

            // Expected titles in DESC published_at order: i=1 is newest.
            $expectedTitles = array_map(fn ($i) => "Album Foto {$i}", range(1, 8));
            $expectedPaths  = array_map(fn ($i) => "gallery/cover-{$i}.jpg", range(1, 8));

            $actualTitles = $galleries->pluck('title')->all();
            $actualPaths  = $galleries->pluck('media')->flatten()->pluck('path')->all();

            // 9th gallery and private album must not appear.
            if (in_array('Album Foto 9', $actualTitles, true)) {
                return false;
            }
            if (in_array('Album Privat', $actualTitles, true)) {
                return false;
            }
            if (in_array('private/secret.jpg', $actualPaths, true)) {
                return false;
            }

            // Each returned album must carry exactly one eager-loaded public cover.
            $everyAlbumHasOnePublicCover = $galleries->every(
                fn ($gallery) => $gallery->relationLoaded('media') && $gallery->media->count() === 1
            );

            // Exact ordered titles and exactly 8 distinct public cover paths.
            return $everyAlbumHasOnePublicCover
                && $actualTitles === $expectedTitles
                && count($actualPaths) === 8
                && $actualPaths === $expectedPaths;
        });
    }
}
