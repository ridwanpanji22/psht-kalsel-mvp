<?php

namespace Tests\Feature;

use App\Actions\AturSampulEvent;
use App\Actions\PublishMediaToPortal;
use App\Enums\GalleryCategory;
use App\Enums\PublicationStatus;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Galleries\Pages\EditGallery;
use App\Filament\Resources\Galleries\RelationManagers\GalleryMediaRelationManager;
use App\Models\Event;
use App\Models\Gallery;
use App\Models\Media;
use App\Models\OrganizationUnit;
use App\Models\UnitAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentPublicationWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function operator(OrganizationUnit $unit): User
    {
        $operator = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $operator->id,
            'organization_unit_id' => $unit->id,
        ]);

        return $operator;
    }

    private function jpeg(string $name = 'cover.jpg', int $kilobytes = 100): UploadedFile
    {
        return UploadedFile::fake()->image($name, 1200, 800)->size($kilobytes);
    }

    // ---------------------------------------------------------------------
    // Event cover upload
    // ---------------------------------------------------------------------

    public function test_event_edit_accepts_jpeg_png_and_webp_covers_on_the_private_local_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $unit = OrganizationUnit::factory()->create();
        $operator = $this->operator($unit);
        $event = Event::factory()->create(['organization_unit_id' => $unit->id]);

        $this->actingAs($operator);

        foreach ([
            UploadedFile::fake()->image('cover.jpg', 1200, 800),
            UploadedFile::fake()->image('cover.png', 1200, 800),
            UploadedFile::fake()->image('cover.webp', 1200, 800),
        ] as $upload) {
            Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
                ->fillForm(['cover_path' => $upload, 'cover_alt_text' => 'Peserta berlatih'])
                ->call('save')
                ->assertHasNoFormErrors();

            $event->refresh();
            $this->assertNotNull($event->cover_path);
            $this->assertStringStartsWith('private/event-covers/', $event->cover_path);
            Storage::disk('local')->assertExists($event->cover_path);
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }

    public function test_event_cover_upload_rejects_svg_non_image_and_oversized_files(): void
    {
        Storage::fake('local');
        $unit = OrganizationUnit::factory()->create();
        $operator = $this->operator($unit);
        $event = Event::factory()->create(['organization_unit_id' => $unit->id]);

        $this->actingAs($operator);

        $svg = UploadedFile::fake()->createWithContent(
            'cover.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"></svg>',
        );
        $pdf = UploadedFile::fake()->create('cover.pdf', 100, 'application/pdf');
        $tooBig = UploadedFile::fake()->image('cover.jpg', 1200, 800)->size(5121);

        foreach (['cover.svg' => $svg, 'cover.pdf' => $pdf, 'too-big.jpg' => $tooBig] as $upload) {
            Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
                ->fillForm(['cover_path' => $upload, 'cover_alt_text' => 'Apa saja'])
                ->call('save')
                ->assertHasFormErrors(['cover_path']);
        }

        $this->assertNull($event->refresh()->cover_path);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_event_without_a_cover_can_be_submitted_and_published(): void
    {
        Storage::fake('local');
        $unit = OrganizationUnit::factory()->create();
        $owner = $this->operator($unit);
        $reviewer = $this->operator($unit);
        $event = Event::factory()->create([
            'organization_unit_id' => $unit->id,
            'status' => PublicationStatus::Draft,
            'cover_path' => null,
        ]);

        $this->actingAs($owner);

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->assertActionVisible('submitForReview')
            ->callAction('submitForReview');

        $this->assertSame(PublicationStatus::PendingReview, $event->refresh()->status);

        $this->actingAs($reviewer);

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->assertActionVisible('publishReviewedContent')
            ->callAction('publishReviewedContent');

        $this->assertSame(PublicationStatus::Published, $event->refresh()->status);
        $this->assertNull($event->refresh()->cover_path);
    }

    public function test_replacing_a_missing_orphaned_event_cover_writes_the_new_file_and_clears_the_old_path(): void
    {
        Storage::fake('local');
        $unit = OrganizationUnit::factory()->create();
        $operator = $this->operator($unit);
        $event = Event::factory()->create([
            'organization_unit_id' => $unit->id,
            'cover_path' => 'private/event-covers/missing.jpg',
            'cover_alt_text' => 'Lama',
        ]);

        $this->actingAs($operator);

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->fillForm(['cover_path' => $this->jpeg('new.jpg'), 'cover_alt_text' => 'Baru'])
            ->call('save')
            ->assertHasNoFormErrors();

        $event->refresh();
        $this->assertNotSame('private/event-covers/missing.jpg', $event->cover_path);
        $this->assertStringStartsWith('private/event-covers/', $event->cover_path);
        Storage::disk('local')->assertExists($event->cover_path);
    }

    public function test_atur_sampul_action_keeps_the_old_file_until_the_new_path_is_persisted_then_removes_it(): void
    {
        Storage::fake('local');
        $unit = OrganizationUnit::factory()->create();
        $event = Event::factory()->create([
            'organization_unit_id' => $unit->id,
            'cover_path' => 'private/event-covers/old.jpg',
            'cover_alt_text' => 'Lama',
        ]);
        Storage::disk('local')->put('private/event-covers/old.jpg', 'old-cover');
        Storage::disk('local')->put('private/event-covers/new.jpg', 'new-cover');

        app(AturSampulEvent::class)->apply($event, 'private/event-covers/new.jpg', 'Baru');

        $event->refresh();
        $this->assertSame('private/event-covers/new.jpg', $event->cover_path);
        $this->assertSame('Baru', $event->cover_alt_text);
        Storage::disk('local')->assertMissing('private/event-covers/old.jpg');
        Storage::disk('local')->assertExists('private/event-covers/new.jpg');
    }

    public function test_atur_sampul_action_keeps_the_old_file_when_the_record_update_fails(): void
    {
        Storage::fake('local');
        $unit = OrganizationUnit::factory()->create();
        $event = Event::factory()->create([
            'organization_unit_id' => $unit->id,
            'cover_path' => 'private/event-covers/old.jpg',
            'cover_alt_text' => 'Lama',
        ]);
        Storage::disk('local')->put('private/event-covers/old.jpg', 'old-cover');
        Storage::disk('local')->put('private/event-covers/new.jpg', 'new-cover');

        Event::updating(function (): void {
            throw new \RuntimeException('database down');
        });

        try {
            app(AturSampulEvent::class)->apply($event, 'private/event-covers/new.jpg', 'Baru');
            $this->fail('Expected the failing update to bubble up.');
        } catch (\RuntimeException) {
            // expected
        } finally {
            Event::flushEventListeners();
        }

        $this->assertSame('private/event-covers/old.jpg', $event->refresh()->cover_path);
        Storage::disk('local')->assertExists('private/event-covers/old.jpg');
        Storage::disk('local')->assertMissing('private/event-covers/new.jpg');
    }

    public function test_atur_sampul_action_clears_alt_text_when_the_cover_is_removed(): void
    {
        Storage::fake('local');
        $unit = OrganizationUnit::factory()->create();
        $event = Event::factory()->create([
            'organization_unit_id' => $unit->id,
            'cover_path' => 'private/event-covers/old.jpg',
            'cover_alt_text' => 'Lama',
        ]);
        Storage::disk('local')->put('private/event-covers/old.jpg', 'old-cover');

        app(AturSampulEvent::class)->apply($event, null, null);

        $event->refresh();
        $this->assertNull($event->cover_path);
        $this->assertNull($event->cover_alt_text);
        Storage::disk('local')->assertMissing('private/event-covers/old.jpg');
    }

    public function test_event_cover_alt_text_is_required_when_a_cover_is_present(): void
    {
        Storage::fake('local');
        $unit = OrganizationUnit::factory()->create();
        $operator = $this->operator($unit);
        $event = Event::factory()->create(['organization_unit_id' => $unit->id]);

        $this->actingAs($operator);

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->fillForm(['cover_path' => $this->jpeg(), 'cover_alt_text' => null])
            ->call('save')
            ->assertHasFormErrors(['cover_alt_text']);

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    // ---------------------------------------------------------------------
    // Gallery category form
    // ---------------------------------------------------------------------

    public function test_gallery_edit_accepts_enum_category_and_preserves_legacy_null_on_load(): void
    {
        $unit = OrganizationUnit::factory()->create();
        $operator = $this->operator($unit);
        $legacy = Gallery::factory()->create([
            'organization_unit_id' => $unit->id,
            'category' => null,
        ]);

        $this->actingAs($operator);

        Livewire::test(EditGallery::class, ['record' => $legacy->getRouteKey()])
            ->assertOk()
            ->assertFormSet(['category' => null])
            ->fillForm(['category' => GalleryCategory::Kegiatan->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(GalleryCategory::Kegiatan, $legacy->refresh()->category);
    }

    public function test_gallery_edit_rejects_category_outside_the_fixed_enum(): void
    {
        $unit = OrganizationUnit::factory()->create();
        $operator = $this->operator($unit);
        $gallery = Gallery::factory()->create(['organization_unit_id' => $unit->id]);

        $this->actingAs($operator);

        Livewire::test(EditGallery::class, ['record' => $gallery->getRouteKey()])
            ->fillForm(['category' => 'tidak-ada'])
            ->call('save')
            ->assertHasFormErrors(['category']);

        $this->assertNull($gallery->refresh()->category);
    }

    public function test_gallery_edit_actions_use_existing_publish_content_workflow(): void
    {
        $unit = OrganizationUnit::factory()->create();
        $owner = $this->operator($unit);
        $reviewer = $this->operator($unit);
        $gallery = Gallery::factory()->create([
            'organization_unit_id' => $unit->id,
            'status' => PublicationStatus::Draft,
        ]);

        $this->actingAs($owner);

        Livewire::test(EditGallery::class, ['record' => $gallery->getRouteKey()])
            ->assertActionVisible('submitForReview')
            ->callAction('submitForReview');

        $this->assertSame(PublicationStatus::PendingReview, $gallery->refresh()->status);

        $this->actingAs($reviewer);

        Livewire::test(EditGallery::class, ['record' => $gallery->getRouteKey()])
            ->assertActionVisible('publishReviewedContent')
            ->callAction('publishReviewedContent');

        $gallery->refresh();
        $this->assertSame(PublicationStatus::Published, $gallery->status);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'content.published',
            'subject_id' => $gallery->id,
        ]);
    }

    public function test_gallery_edit_page_is_denied_for_an_operator_outside_the_owner_unit(): void
    {
        $unit = OrganizationUnit::factory()->create();
        $other = OrganizationUnit::factory()->create();
        $outsider = $this->operator($other);
        $gallery = Gallery::factory()->create(['organization_unit_id' => $unit->id]);

        $this->actingAs($outsider)
            ->get('/admin/galleries/'.$gallery->getRouteKey().'/edit')
            ->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // Gallery media relation manager
    // ---------------------------------------------------------------------

    private function attachMedia(Gallery $gallery, User $operator, string $name = 'foto.jpg'): void
    {
        Livewire::test(GalleryMediaRelationManager::class, [
            'ownerRecord' => $gallery,
            'pageClass' => EditGallery::class,
        ])
            ->callTableAction('create', data: [
                'file' => UploadedFile::fake()->image($name, 800, 600),
                'alt_text' => 'Peserta latihan',
                'sort_order' => 1,
            ])
            ->assertHasNoTableActionErrors();
    }

    public function test_media_upload_stores_on_local_private_disk_and_never_on_public(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $unit = OrganizationUnit::factory()->create();
        $operator = $this->operator($unit);
        $gallery = Gallery::factory()->create(['organization_unit_id' => $unit->id]);

        $this->actingAs($operator);
        $this->attachMedia($gallery, $operator);

        $media = Media::query()->firstOrFail();
        $this->assertSame('local', $media->disk);
        $this->assertFalse($media->is_public);
        $this->assertSame($gallery->id, $media->gallery_id);
        $this->assertSame($unit->id, $media->organization_unit_id);
        $this->assertSame('Peserta latihan', $media->alt_text);
        $this->assertSame(1, $media->sort_order);
        Storage::disk('local')->assertExists($media->path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_media_relation_manager_query_only_lists_media_of_the_owner_gallery(): void
    {
        Storage::fake('local');
        $unit = OrganizationUnit::factory()->create();
        $operator = $this->operator($unit);
        $gallery = Gallery::factory()->create(['organization_unit_id' => $unit->id]);
        $other = Gallery::factory()->create(['organization_unit_id' => $unit->id]);
        $mine = Media::factory()->create(['gallery_id' => $gallery->id, 'organization_unit_id' => $unit->id]);
        $theirs = Media::factory()->create(['gallery_id' => $other->id, 'organization_unit_id' => $unit->id]);

        $this->actingAs($operator);

        Livewire::test(GalleryMediaRelationManager::class, [
            'ownerRecord' => $gallery,
            'pageClass' => EditGallery::class,
        ])
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_media_relation_manager_is_hidden_for_an_operator_outside_the_owner_unit(): void
    {
        $unit = OrganizationUnit::factory()->create();
        $other = OrganizationUnit::factory()->create();
        $outsider = $this->operator($other);
        $gallery = Gallery::factory()->create(['organization_unit_id' => $unit->id]);

        $this->actingAs($outsider);

        $this->assertFalse(
            GalleryMediaRelationManager::canViewForRecord($gallery, EditGallery::class),
        );
    }

    public function test_publish_media_action_is_hidden_and_refused_for_a_draft_gallery(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $unit = OrganizationUnit::factory()->create();
        $operator = $this->operator($unit);
        $gallery = Gallery::factory()->create([
            'organization_unit_id' => $unit->id,
            'status' => PublicationStatus::Draft,
        ]);
        $media = Media::factory()->create([
            'gallery_id' => $gallery->id,
            'organization_unit_id' => $unit->id,
            'disk' => 'local',
            'path' => 'private/gallery-media/foto.jpg',
            'is_public' => false,
        ]);
        Storage::disk('local')->put('private/gallery-media/foto.jpg', 'image-bytes');

        $this->actingAs($operator);

        Livewire::test(GalleryMediaRelationManager::class, [
            'ownerRecord' => $gallery,
            'pageClass' => EditGallery::class,
        ])->assertTableActionHidden('publishToPortal', $media);

        $this->expectException(ValidationException::class);
        app(PublishMediaToPortal::class)->publish($operator, $media);
    }

    public function test_publish_media_requires_alt_text(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $unit = OrganizationUnit::factory()->create();
        $operator = $this->operator($unit);
        $gallery = $this->publishedGallery($unit);
        $media = Media::factory()->create([
            'gallery_id' => $gallery->id,
            'organization_unit_id' => $unit->id,
            'disk' => 'local',
            'path' => 'private/gallery-media/foto.jpg',
            'alt_text' => null,
            'is_public' => false,
        ]);
        Storage::disk('local')->put('private/gallery-media/foto.jpg', 'image-bytes');

        $this->actingAs($operator);

        Livewire::test(GalleryMediaRelationManager::class, [
            'ownerRecord' => $gallery,
            'pageClass' => EditGallery::class,
        ])
            ->callTableAction('publishToPortal', $media, data: ['alt_text' => null])
            ->assertHasTableActionErrors(['alt_text']);

        $this->assertFalse($media->refresh()->is_public);
    }

    public function test_publishing_media_moves_the_file_to_a_unique_public_path_and_flips_state(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $unit = OrganizationUnit::factory()->create();
        $operator = $this->operator($unit);
        $gallery = $this->publishedGallery($unit);
        $media = Media::factory()->create([
            'gallery_id' => $gallery->id,
            'organization_unit_id' => $unit->id,
            'disk' => 'local',
            'path' => 'private/gallery-media/foto.jpg',
            'alt_text' => 'Peserta latihan',
            'is_public' => false,
        ]);
        Storage::disk('local')->put('private/gallery-media/foto.jpg', 'image-bytes');

        $this->actingAs($operator);

        Livewire::test(GalleryMediaRelationManager::class, [
            'ownerRecord' => $gallery,
            'pageClass' => EditGallery::class,
        ])
            ->callTableAction('publishToPortal', $media, data: ['alt_text' => 'Peserta latihan'])
            ->assertHasNoTableActionErrors();

        $media->refresh();
        $this->assertSame('public', $media->disk);
        $this->assertTrue($media->is_public);
        $this->assertStringStartsWith('public/gallery-media/', $media->path);
        Storage::disk('public')->assertExists($media->path);
        Storage::disk('local')->assertMissing('private/gallery-media/foto.jpg');
    }

    public function test_publishing_media_uses_a_unique_path_even_for_identical_source_names(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $unit = OrganizationUnit::factory()->create();
        $operator = $this->operator($unit);
        $gallery = $this->publishedGallery($unit);

        $paths = [];

        foreach ([1, 2] as $index) {
            $media = Media::factory()->create([
                'gallery_id' => $gallery->id,
                'organization_unit_id' => $unit->id,
                'disk' => 'local',
                'path' => 'private/gallery-media/foto.jpg',
                'alt_text' => 'Peserta latihan',
                'is_public' => false,
            ]);
            Storage::disk('local')->put('private/gallery-media/foto.jpg', 'image-'.$index);

            app(PublishMediaToPortal::class)->publish($operator, $media);
            $paths[] = $media->refresh()->path;
        }

        $this->assertCount(2, array_unique($paths));
        Storage::disk('public')->assertExists($paths[0]);
        Storage::disk('public')->assertExists($paths[1]);
    }

    public function test_publishing_media_compensates_the_copied_public_file_when_the_database_update_fails(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $unit = OrganizationUnit::factory()->create();
        $operator = $this->operator($unit);
        $gallery = $this->publishedGallery($unit);
        $media = Media::factory()->create([
            'gallery_id' => $gallery->id,
            'organization_unit_id' => $unit->id,
            'disk' => 'local',
            'path' => 'private/gallery-media/foto.jpg',
            'alt_text' => 'Peserta latihan',
            'is_public' => false,
        ]);
        Storage::disk('local')->put('private/gallery-media/foto.jpg', 'image-bytes');

        Media::updating(function (): void {
            throw new \RuntimeException('database down');
        });

        try {
            app(PublishMediaToPortal::class)->publish($operator, $media);
            $this->fail('Expected the failing database update to bubble up.');
        } catch (\RuntimeException) {
            // expected
        } finally {
            Media::flushEventListeners();
        }

        $media->refresh();
        $this->assertFalse($media->is_public);
        $this->assertSame('local', $media->disk);
        $this->assertSame('private/gallery-media/foto.jpg', $media->path);
        Storage::disk('local')->assertExists('private/gallery-media/foto.jpg');
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_hapus_dari_portal_hides_the_media_but_keeps_the_public_file(): void
    {
        Storage::fake('public');
        $unit = OrganizationUnit::factory()->create();
        $operator = $this->operator($unit);
        $gallery = $this->publishedGallery($unit);
        $media = Media::factory()->create([
            'gallery_id' => $gallery->id,
            'organization_unit_id' => $unit->id,
            'disk' => 'public',
            'path' => 'public/gallery-media/foto.jpg',
            'alt_text' => 'Peserta latihan',
            'is_public' => true,
        ]);
        Storage::disk('public')->put('public/gallery-media/foto.jpg', 'image-bytes');

        $this->actingAs($operator);

        Livewire::test(GalleryMediaRelationManager::class, [
            'ownerRecord' => $gallery,
            'pageClass' => EditGallery::class,
        ])->callTableAction('hapusDariPortal', $media);

        $this->assertFalse($media->refresh()->is_public);
        $this->assertSame('public', $media->disk);
        Storage::disk('public')->assertExists('public/gallery-media/foto.jpg');
    }

    public function test_publish_media_action_is_hidden_for_already_public_media(): void
    {
        Storage::fake('public');
        $unit = OrganizationUnit::factory()->create();
        $operator = $this->operator($unit);
        $gallery = $this->publishedGallery($unit);
        $media = Media::factory()->create([
            'gallery_id' => $gallery->id,
            'organization_unit_id' => $unit->id,
            'disk' => 'public',
            'path' => 'public/gallery-media/foto.jpg',
            'alt_text' => 'Peserta latihan',
            'is_public' => true,
        ]);

        $this->actingAs($operator);

        Livewire::test(GalleryMediaRelationManager::class, [
            'ownerRecord' => $gallery,
            'pageClass' => EditGallery::class,
        ])->assertTableActionHidden('publishToPortal', $media);
    }

    public function test_media_relation_manager_form_cannot_edit_disk_or_is_public(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $unit = OrganizationUnit::factory()->create();
        $operator = $this->operator($unit);
        $gallery = Gallery::factory()->create(['organization_unit_id' => $unit->id]);

        $this->actingAs($operator);

        Livewire::test(GalleryMediaRelationManager::class, [
            'ownerRecord' => $gallery,
            'pageClass' => EditGallery::class,
        ])
            ->mountTableAction('create')
            ->assertFormFieldDoesNotExist('disk')
            ->assertFormFieldDoesNotExist('is_public');
    }

    private function publishedGallery(OrganizationUnit $unit): Gallery
    {
        return Gallery::factory()->create([
            'organization_unit_id' => $unit->id,
            'status' => PublicationStatus::Published,
            'published_at' => now(),
        ]);
    }
}
