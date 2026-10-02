<?php

namespace Tests\Feature;

use App\Enums\GalleryCategory;
use App\Enums\PublicationStatus;
use App\Filament\Resources\Galleries\Pages\CreateGallery;
use App\Models\Event;
use App\Models\Gallery;
use App\Models\Media;
use App\Models\OrganizationUnit;
use App\Models\UnitAssignment;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PublicUiPublicationSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const MIGRATION = '2026_10_02_122128_add_public_ui_publication_fields.php';

    public function test_legacy_event_gallery_and_media_rows_load_with_null_and_default_values(): void
    {
        $unit = OrganizationUnit::factory()->create();

        $eventId = DB::table('events')->insertGetId($this->legacyEventRow($unit->id));
        $galleryId = DB::table('galleries')->insertGetId($this->legacyGalleryRow($unit->id));
        $mediaId = DB::table('media')->insertGetId($this->legacyMediaRow($unit->id, $galleryId));

        $event = Event::query()->findOrFail($eventId);
        $gallery = Gallery::query()->findOrFail($galleryId);
        $media = Media::query()->findOrFail($mediaId);

        $legacyMediaId = DB::table('media')->insertGetId([
            'gallery_id' => $galleryId,
            'organization_unit_id' => $unit->id,
            'disk' => 'public',
            'path' => 'gallery/legacy-raw.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'alt_text' => 'Raw legacy media',
            'is_public' => false,
            'created_at' => '2026-09-04 09:00:00',
            'updated_at' => '2026-09-04 09:00:00',
        ]);

        $this->assertNull($event->cover_path);
        $this->assertNull($event->cover_alt_text);

        $this->assertNull($gallery->category);

        $this->assertSame(0, $media->sort_order);
        $this->assertFalse($media->is_public);
        $this->assertFalse(Media::query()->findOrFail($legacyMediaId)->is_public);
    }

    public function test_event_cover_fields_are_fillable_and_cast_as_nullable_strings(): void
    {
        $event = Event::factory()->create([
            'cover_path' => 'events/covers/sampul.jpg',
            'cover_alt_text' => 'Peserta latihan bersama',
        ]);

        $fresh = $event->fresh();

        $this->assertSame('events/covers/sampul.jpg', $fresh->cover_path);
        $this->assertSame('Peserta latihan bersama', $fresh->cover_alt_text);

        $eventWithoutCover = Event::factory()->create(['cover_path' => null, 'cover_alt_text' => null]);

        $this->assertNull($eventWithoutCover->fresh()->cover_path);
        $this->assertNull($eventWithoutCover->fresh()->cover_alt_text);
    }

    public function test_gallery_category_casts_to_enum_and_stays_null_for_legacy(): void
    {
        $classified = Gallery::factory()->create(['category' => GalleryCategory::Latihan]);
        $legacy = Gallery::factory()->create(['category' => null]);

        $this->assertSame(GalleryCategory::Latihan, $classified->fresh()->category);
        $this->assertNull($legacy->fresh()->category);
    }

    public function test_media_sort_order_casts_to_integer_and_defaults_to_zero(): void
    {
        $media = Media::factory()->create(['sort_order' => 3]);
        $defaulted = Media::factory()->create();

        $this->assertSame(3, $media->fresh()->sort_order);
        $this->assertSame(0, $defaulted->fresh()->sort_order);
        $this->assertIsInt($defaulted->fresh()->sort_order);
    }

    public function test_write_boundary_rejects_category_outside_the_fixed_enum(): void
    {
        $this->expectException(ValidationException::class);

        $this->validateCategory('liburan');
    }

    public function test_write_boundary_accepts_each_allowed_category_and_null(): void
    {
        foreach (['latihan', 'kegiatan', 'pembinaan', null] as $value) {
            $validated = $this->validateCategory($value);

            $this->assertSame($value, $validated);
        }
    }

    public function test_enum_exposes_the_three_fixed_values_with_indonesian_labels(): void
    {
        $this->assertSame(['latihan', 'kegiatan', 'pembinaan'], array_keys(GalleryCategory::options()));
        $this->assertSame('Latihan', GalleryCategory::Latihan->label());
        $this->assertSame('Kegiatan', GalleryCategory::Kegiatan->label());
        $this->assertSame('Pembinaan', GalleryCategory::Pembinaan->label());
    }

    public function test_gallery_filament_create_rejects_category_outside_the_fixed_enum(): void
    {
        $this->actingAsOperator();

        Livewire::test(CreateGallery::class)
            ->fillForm([
                'organization_unit_id' => $this->operatorUnitId(),
                'title' => 'Album Tidak Sah',
                'slug' => 'album-tidak-sah',
                'category' => 'liburan',
            ])
            ->call('create')
            ->assertHasFormErrors(['category']);

        $this->assertDatabaseMissing('galleries', ['slug' => 'album-tidak-sah']);
    }

    public function test_gallery_filament_create_persists_each_allowed_category(): void
    {
        $this->actingAsOperator();

        $unitId = $this->operatorUnitId();

        foreach (GalleryCategory::cases() as $category) {
            $slug = 'album-'.$category->value;

            Livewire::test(CreateGallery::class)
                ->fillForm([
                    'organization_unit_id' => $unitId,
                    'title' => 'Album '.$category->label(),
                    'slug' => $slug,
                    'category' => $category->value,
                ])
                ->call('create')
                ->assertHasNoFormErrors();

            $this->assertSame($category, Gallery::query()->where('slug', $slug)->firstOrFail()->category);
        }

        Livewire::test(CreateGallery::class)
            ->fillForm([
                'organization_unit_id' => $unitId,
                'title' => 'Album Warisan',
                'slug' => 'album-warisan',
                'category' => null,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertNull(Gallery::query()->where('slug', 'album-warisan')->firstOrFail()->category);
    }

    public function test_gallery_media_relation_is_ordered_by_sort_order_then_id(): void
    {
        $gallery = Gallery::factory()->create();

        $lateId = Media::factory()->create(['gallery_id' => $gallery->id, 'sort_order' => 2])->id;
        $earlyId = Media::factory()->create(['gallery_id' => $gallery->id, 'sort_order' => 1])->id;
        $tieFirstId = Media::factory()->create(['gallery_id' => $gallery->id, 'sort_order' => 1])->id;
        $zeroId = Media::factory()->create(['gallery_id' => $gallery->id, 'sort_order' => 0])->id;

        $orderedIds = $gallery->media()->pluck('id')->all();

        $this->assertSame([$zeroId, $earlyId, $tieFirstId, $lateId], $orderedIds);
        $this->assertSame(
            [$zeroId, $earlyId, $tieFirstId, $lateId],
            $gallery->load('media')->media->pluck('id')->all(),
        );
    }

    public function test_migration_up_then_down_preserves_existing_publication_rows(): void
    {
        $unit = OrganizationUnit::factory()->create();

        $eventId = DB::table('events')->insertGetId($this->legacyEventRow($unit->id));
        $galleryId = DB::table('galleries')->insertGetId($this->legacyGalleryRow($unit->id));
        $mediaId = DB::table('media')->insertGetId($this->legacyMediaRow($unit->id, $galleryId));

        $migration = $this->publicationFieldsMigration();
        $migration->down();

        $this->assertFalse(Schema::hasColumn('events', 'cover_path'));
        $this->assertFalse(Schema::hasColumn('events', 'cover_alt_text'));
        $this->assertFalse(Schema::hasColumn('galleries', 'category'));
        $this->assertFalse(Schema::hasColumn('media', 'sort_order'));

        $migration->up();

        $this->assertTrue(Schema::hasColumn('events', 'cover_path'));
        $this->assertTrue(Schema::hasColumn('events', 'cover_alt_text'));
        $this->assertTrue(Schema::hasColumn('galleries', 'category'));
        $this->assertTrue(Schema::hasColumn('media', 'sort_order'));

        $event = DB::table('events')->where('id', $eventId)->first();
        $gallery = DB::table('galleries')->where('id', $galleryId)->first();
        $media = DB::table('media')->where('id', $mediaId)->first();

        $this->assertNotNull($event);
        $this->assertNotNull($gallery);
        $this->assertNotNull($media);
        $this->assertNull($event->cover_path);
        $this->assertNull($event->cover_alt_text);
        $this->assertNull($gallery->category);
        $this->assertSame(0, (int) $media->sort_order);
        $this->assertSame(0, (int) $media->is_public);
    }

    private function publicationFieldsMigration(): Migration
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/'.self::MIGRATION);

        return $migration;
    }

    private function validateCategory(?string $value): ?string
    {
        $validated = Validator::make(
            ['category' => $value],
            ['category' => ['nullable', 'string', Rule::enum(GalleryCategory::class)]],
        )->validate();

        return $validated['category'] ?? null;
    }

    private function actingAsOperator(): void
    {
        $unit = OrganizationUnit::factory()->create();
        $operator = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $operator->id,
            'organization_unit_id' => $unit->id,
        ]);

        $this->actingAs($operator);
    }

    private function operatorUnitId(): int
    {
        return UnitAssignment::query()
            ->where('user_id', auth()->id())
            ->value('organization_unit_id');
    }

    /**
     * @return array<string, mixed>
     */
    private function legacyEventRow(int $organizationUnitId): array
    {
        return [
            'organization_unit_id' => $organizationUnitId,
            'slug' => 'legacy-event-'.$organizationUnitId,
            'title' => 'Legacy Event',
            'description' => null,
            'starts_at' => '2026-09-01 09:00:00',
            'ends_at' => null,
            'venue' => null,
            'status' => PublicationStatus::Published->value,
            'published_at' => '2026-09-01 10:00:00',
            'created_at' => '2026-09-01 09:00:00',
            'updated_at' => '2026-09-01 09:00:00',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function legacyGalleryRow(int $organizationUnitId): array
    {
        return [
            'organization_unit_id' => $organizationUnitId,
            'slug' => 'legacy-gallery-'.$organizationUnitId,
            'title' => 'Legacy Gallery',
            'description' => null,
            'status' => PublicationStatus::Published->value,
            'published_at' => '2026-09-02 10:00:00',
            'created_at' => '2026-09-02 09:00:00',
            'updated_at' => '2026-09-02 09:00:00',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function legacyMediaRow(int $organizationUnitId, int $galleryId): array
    {
        return [
            'gallery_id' => $galleryId,
            'organization_unit_id' => $organizationUnitId,
            'disk' => 'public',
            'path' => 'gallery/legacy.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 2048,
            'alt_text' => 'Legacy media',
            'is_public' => false,
            'created_at' => '2026-09-03 09:00:00',
            'updated_at' => '2026-09-03 09:00:00',
        ];
    }
}
