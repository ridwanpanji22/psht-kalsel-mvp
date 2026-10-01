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
}
