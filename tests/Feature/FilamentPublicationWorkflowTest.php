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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_atur_sampul_action_never_deletes_an_unsafe_persisted_old_cover_path(): void
    {
        Storage::fake('local');
        Log::spy();
        $unit = OrganizationUnit::factory()->create();
        $event = Event::factory()->create([
            'organization_unit_id' => $unit->id,
            'cover_path' => 'private/event-covers/../gallery-media/sentinel.jpg',
            'cover_alt_text' => 'Lama',
        ]);

        // A sentinel file the unvalidated old path would resolve to.
        Storage::disk('local')->put('private/gallery-media/sentinel.jpg', $this->validJpegBytes());

        // Replacing the malformed cover with a safe one.
        Storage::disk('local')->put('private/event-covers/new.jpg', $this->validJpegBytes());
        app(AturSampulEvent::class)->apply($event, 'private/event-covers/new.jpg', 'Baru');

        $event->refresh();
        $this->assertSame('private/event-covers/new.jpg', $event->cover_path);
        $this->assertSame('Baru', $event->cover_alt_text);
        Storage::disk('local')->assertExists('private/gallery-media/sentinel.jpg');

        Log::shouldHaveReceived('error')->withArgs(
            fn (string $message): bool => str_contains($message, 'Lokasi sampul lama tidak aman'),
        )->atLeast()->once();
    }

    public function test_atur_sampul_action_never_deletes_an_unsafe_old_cover_when_clearing(): void
    {
        Storage::fake('local');
        Log::spy();
        $unit = OrganizationUnit::factory()->create();
        $event = Event::factory()->create([
            'organization_unit_id' => $unit->id,
            'cover_path' => 'private/event-covers/../gallery-media/sentinel.jpg',
            'cover_alt_text' => 'Lama',
        ]);
        Storage::disk('local')->put('private/gallery-media/sentinel.jpg', $this->validJpegBytes());

        app(AturSampulEvent::class)->apply($event, null, null);

        $event->refresh();
        $this->assertNull($event->cover_path);
        $this->assertNull($event->cover_alt_text);
        Storage::disk('local')->assertExists('private/gallery-media/sentinel.jpg');
        Log::shouldHaveReceived('error')->atLeast()->once();
    }

    #[DataProvider('unsafeOldCoverPaths')]
    public function test_atur_sampul_action_ignores_any_unsafe_old_cover_path_shape(string $oldPath): void
    {
        Storage::fake('local');
        Log::spy();
        $unit = OrganizationUnit::factory()->create();
        $event = Event::factory()->create([
            'organization_unit_id' => $unit->id,
            'cover_path' => $oldPath,
            'cover_alt_text' => 'Lama',
        ]);

        Storage::disk('local')->put('private/gallery-media/sentinel.jpg', $this->validJpegBytes());
        Storage::disk('local')->put('private/event-covers/new.jpg', $this->validJpegBytes());

        app(AturSampulEvent::class)->apply($event, 'private/event-covers/new.jpg', 'Baru');

        $this->assertSame('private/event-covers/new.jpg', $event->refresh()->cover_path);
        Storage::disk('local')->assertExists('private/gallery-media/sentinel.jpg');
        Log::shouldHaveReceived('error')->atLeast()->once();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unsafeOldCoverPaths(): array
    {
        return [
            'traversal' => ['private/event-covers/../gallery-media/sentinel.jpg'],
            'backslash' => ['private\\event-covers\\x.jpg'],
            'null byte' => ["private/event-covers/x\0.jpg"],
            'absolute' => ['/private/event-covers/x.jpg'],
            'dot segment' => ['private/event-covers/./x.jpg'],
        ];
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

    public function test_legacy_gallery_must_be_classified_before_other_edits_can_be_saved(): void
    {
        $unit = OrganizationUnit::factory()->create();
        $operator = $this->operator($unit);
        $legacy = Gallery::factory()->create([
            'organization_unit_id' => $unit->id,
            'category' => null,
        ]);

        $this->actingAs($operator);

        Livewire::test(EditGallery::class, ['record' => $legacy->getRouteKey()])
            ->assertFormSet(['category' => null])
            ->fillForm(['title' => 'Perubahan tanpa klasifikasi'])
            ->call('save')
            ->assertHasFormErrors(['category']);

        $this->assertNotSame('Perubahan tanpa klasifikasi', $legacy->refresh()->title);
        $this->assertNull($legacy->category);
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
        Storage::disk('local')->put('private/gallery-media/foto.jpg', $this->validJpegBytes());

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
        Storage::disk('local')->put('private/gallery-media/foto.jpg', $this->validJpegBytes());

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
        Storage::disk('local')->put('private/gallery-media/foto.jpg', $this->validJpegBytes());

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
        Storage::disk('local')->put('private/gallery-media/foto.jpg', $this->validJpegBytes());

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
        Storage::disk('public')->put('public/gallery-media/foto.jpg', $this->validJpegBytes());

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

    // ---------------------------------------------------------------------
    // Fix round 1 — path tampering, republish, cleanup, metadata
    // ---------------------------------------------------------------------

    public function test_event_cover_upload_refuses_a_tampered_client_supplied_local_path(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $unit = OrganizationUnit::factory()->create();
        $operator = $this->operator($unit);
        $event = Event::factory()->create(['organization_unit_id' => $unit->id]);
        Storage::disk('local')->put('private/gallery-media/secret.jpg', 'not-an-event-cover');

        $this->actingAs($operator);

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->fillForm(['cover_path' => ['private/gallery-media/secret.jpg'], 'cover_alt_text' => 'X'])
            ->call('save')
            ->assertHasFormErrors(['cover_path']);

        $this->assertNull($event->refresh()->cover_path);
    }

    public function test_media_upload_refuses_a_tampered_client_supplied_local_path(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $unit = OrganizationUnit::factory()->create();
        $operator = $this->operator($unit);
        $gallery = Gallery::factory()->create(['organization_unit_id' => $unit->id]);
        Storage::disk('local')->put('private/event-covers/secret.jpg', 'not-a-gallery-upload');

        $this->actingAs($operator);

        Livewire::test(GalleryMediaRelationManager::class, [
            'ownerRecord' => $gallery,
            'pageClass' => EditGallery::class,
        ])
            ->callTableAction('create', data: [
                'file' => ['private/event-covers/secret.jpg'],
                'alt_text' => 'X',
                'sort_order' => 0,
            ])
            ->assertHasTableActionErrors(['file']);

        $this->assertSame(0, Media::query()->count());
    }

    public function test_publish_media_refuses_a_media_record_backed_by_a_non_local_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $unit = OrganizationUnit::factory()->create();
        $operator = $this->operator($unit);
        $gallery = $this->publishedGallery($unit);
        $media = Media::factory()->create([
            'gallery_id' => $gallery->id,
            'organization_unit_id' => $unit->id,
            'disk' => 'public',
            'path' => 'public/gallery-media/sneaky.jpg',
            'alt_text' => 'Peserta latihan',
            'is_public' => false,
        ]);
        Storage::disk('public')->put('public/gallery-media/sneaky.jpg', $this->validJpegBytes());
        // Mirror the same path on the local disk so the record cannot be
        // rejected merely by a missing source file; only the disk guard can
        // refuse this publish.
        Storage::disk('local')->put('public/gallery-media/sneaky.jpg', $this->validJpegBytes());

        $this->actingAs($operator);

        try {
            app(PublishMediaToPortal::class)->publish($operator, $media);
            $this->fail('Expected a non-local media record to be refused.');
        } catch (ValidationException) {
            // expected
        }

        $media->refresh();
        $this->assertFalse($media->is_public);
        $this->assertSame('public', $media->disk);
        $this->assertSame('public/gallery-media/sneaky.jpg', $media->path);
        Storage::disk('public')->assertExists('public/gallery-media/sneaky.jpg');
    }

    public function test_publish_media_refuses_a_local_path_outside_the_gallery_media_prefix(): void
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
            'path' => 'private/event-covers/looks-like-a-cover.jpg',
            'alt_text' => 'Peserta latihan',
            'is_public' => false,
        ]);
        Storage::disk('local')->put('private/event-covers/looks-like-a-cover.jpg', 'cover');

        $this->actingAs($operator);

        $this->expectException(ValidationException::class);
        app(PublishMediaToPortal::class)->publish($operator, $media);
    }

    public function test_publish_media_refuses_a_local_file_whose_content_mime_is_not_allowed(): void
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
            'path' => 'private/gallery-media/fake.jpg',
            'alt_text' => 'Peserta latihan',
            'is_public' => false,
        ]);
        Storage::disk('local')->put('private/gallery-media/fake.jpg', '<?php echo "not an image";');

        $this->actingAs($operator);

        try {
            app(PublishMediaToPortal::class)->publish($operator, $media);
            $this->fail('Expected disallowed content MIME to be refused.');
        } catch (ValidationException) {
            // expected
        }

        $media->refresh();
        $this->assertFalse($media->is_public);
        $this->assertSame('local', $media->disk);
        Storage::disk('public')->assertDirectoryEmpty('/');
        Storage::disk('local')->assertExists('private/gallery-media/fake.jpg');
    }

    public function test_media_hidden_from_portal_cannot_be_republished_or_reach_the_public_source_file(): void
    {
        Storage::fake('public');
        Storage::fake('local');
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
        Storage::disk('public')->put('public/gallery-media/foto.jpg', $this->validJpegBytes());

        $this->actingAs($operator);

        Livewire::test(GalleryMediaRelationManager::class, [
            'ownerRecord' => $gallery,
            'pageClass' => EditGallery::class,
        ])->callTableAction('hapusDariPortal', $media);

        $this->assertFalse($media->refresh()->is_public);

        Livewire::test(GalleryMediaRelationManager::class, [
            'ownerRecord' => $gallery,
            'pageClass' => EditGallery::class,
        ])->assertTableActionHidden('publishToPortal', $media);

        try {
            app(PublishMediaToPortal::class)->publish($operator, $media);
            $this->fail('A hidden public-backed media record must not be republishable.');
        } catch (ValidationException) {
            // expected
        }

        $media->refresh();
        $this->assertFalse($media->is_public);
        $this->assertSame('public', $media->disk);
        $this->assertSame('public/gallery-media/foto.jpg', $media->path);
        Storage::disk('public')->assertExists('public/gallery-media/foto.jpg');
    }

    public function test_media_upload_deletes_the_staged_file_when_the_row_creation_fails(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $unit = OrganizationUnit::factory()->create();
        $operator = $this->operator($unit);
        $gallery = Gallery::factory()->create(['organization_unit_id' => $unit->id]);

        Media::creating(function (): void {
            throw new \RuntimeException('row create failed');
        });

        $this->actingAs($operator);

        try {
            Livewire::test(GalleryMediaRelationManager::class, [
                'ownerRecord' => $gallery,
                'pageClass' => EditGallery::class,
            ])->callTableAction('create', data: [
                'file' => UploadedFile::fake()->image('foto.jpg', 800, 600),
                'alt_text' => 'Peserta latihan',
                'sort_order' => 0,
            ]);
        } catch (\RuntimeException) {
            // Livewire surfaces the thrown exception out of the test harness.
        } finally {
            Media::flushEventListeners();
        }

        $this->assertSame(0, Media::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_event_page_deletes_a_newly_staged_cover_when_the_record_update_fails(): void
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
            throw new \RuntimeException('record update failed');
        });

        try {
            (new class extends EditEvent
            {
                public function saveRecord(Model $record, array $data): Model
                {
                    return $this->handleRecordUpdate($record, $data);
                }
            })->saveRecord($event, [
                'title' => 'Judul baru',
                'cover_path' => 'private/event-covers/new.jpg',
                'cover_alt_text' => 'Baru',
            ]);
            $this->fail('Expected the initial record save to fail.');
        } catch (\RuntimeException) {
            // expected
        } finally {
            Event::flushEventListeners();
        }

        $event->refresh();
        $this->assertSame('private/event-covers/old.jpg', $event->cover_path);
        Storage::disk('local')->assertExists('private/event-covers/old.jpg');
        Storage::disk('local')->assertMissing('private/event-covers/new.jpg');
    }

    public function test_publish_media_reports_a_failed_local_cleanup_after_commit_without_reverting_public_state(): void
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
        Storage::disk('local')->put('private/gallery-media/foto.jpg', $this->validJpegBytes());

        // Simulate a post-commit cleanup failure: the local delete reports
        // failure and the file remains, but the public state must stand.
        $this->failLocalDiskDeletes(fn (): bool => false);

        Log::shouldReceive('error')->atLeast()->once()->withArgs(
            fn (string $message): bool => str_contains($message, 'Gagal menghapus berkas privat media setelah penerbitan'),
        );

        $this->actingAs($operator);

        $result = app(PublishMediaToPortal::class)->publish($operator, $media);

        $this->assertTrue($result->is_public);
        $this->assertSame('public', $result->disk);
        Storage::disk('public')->assertExists($result->path);
    }

    public function test_publish_media_reports_a_failed_compensation_delete_without_swallowing_the_db_exception(): void
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
        Storage::disk('local')->put('private/gallery-media/foto.jpg', $this->validJpegBytes());

        // Compensation delete also fails; the original DB exception must still win.
        $this->failPublicDiskDeletes(fn (): bool => false);

        // Compensation delete reported failure: the orphaned public file
        // remains, and this must be logged loudly. The DB exception is
        // preserved and the private file is untouched.
        Log::shouldReceive('error')->atLeast()->once()->withArgs(
            fn (string $message): bool => str_contains($message, 'Gagal membersihkan berkas publik setelah kegagalan basis data'),
        );

        Media::updating(function (): void {
            throw new \RuntimeException('database down');
        });

        try {
            app(PublishMediaToPortal::class)->publish($operator, $media);
            $this->fail('Expected the DB exception to bubble up.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('database down', $exception->getMessage());
        } finally {
            Media::flushEventListeners();
        }

        $media->refresh();
        $this->assertFalse($media->is_public);
        $this->assertSame('local', $media->disk);
        Storage::disk('local')->assertExists('private/gallery-media/foto.jpg');
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_publish_media_reports_a_failed_copy_compensation_and_keeps_the_local_file(): void
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
        Storage::disk('local')->put('private/gallery-media/foto.jpg', $this->validJpegBytes());

        // Copy fails and its compensation delete also fails.
        $this->failPublicDiskWritesAndDeletes();

        Log::shouldReceive('error')->atLeast()->once()->withArgs(
            fn (string $message): bool => str_contains($message, 'Gagal membersihkan berkas publik setelah kegagalan salin'),
        );

        $this->actingAs($operator);

        try {
            app(PublishMediaToPortal::class)->publish($operator, $media);
            $this->fail('Expected the failed copy to surface.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('copy failed', $exception->getMessage());
        }

        $media->refresh();
        $this->assertFalse($media->is_public);
        $this->assertSame('local', $media->disk);
        Storage::disk('local')->assertExists('private/gallery-media/foto.jpg');
    }

    // ---------------------------------------------------------------------
    // Fix round 2 — traversal-safe paths, probe/cleanup guards
    // ---------------------------------------------------------------------

    public function test_publish_media_refuses_a_traversal_path_and_leaves_the_sentinel_file_untouched(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $unit = OrganizationUnit::factory()->create();
        $operator = $this->operator($unit);
        $gallery = $this->publishedGallery($unit);

        // A sentinel private file that the traversal would otherwise reach.
        Storage::disk('local')->put('private/event-covers/sentinel.jpg', $this->validJpegBytes());

        $media = Media::factory()->create([
            'gallery_id' => $gallery->id,
            'organization_unit_id' => $unit->id,
            'disk' => 'local',
            'path' => 'private/gallery-media/../event-covers/sentinel.jpg',
            'alt_text' => 'Peserta latihan',
            'is_public' => false,
        ]);

        $this->actingAs($operator);

        $threw = false;

        try {
            app(PublishMediaToPortal::class)->publish($operator, $media);
        } catch (ValidationException) {
            $threw = true;
        }

        // Side-effect assertions run regardless of whether the guard threw.
        Storage::disk('local')->assertExists('private/event-covers/sentinel.jpg');
        Storage::disk('public')->assertDirectoryEmpty('/');
        $this->assertFalse($media->refresh()->is_public);
        $this->assertSame('local', $media->disk);
        $this->assertTrue($threw, 'Expected a traversal path to be refused.');
    }

    public function test_event_cover_refuses_a_traversal_path_pointing_at_another_private_file(): void
    {
        Storage::fake('local');
        $unit = OrganizationUnit::factory()->create();
        $operator = $this->operator($unit);
        $event = Event::factory()->create(['organization_unit_id' => $unit->id]);
        Storage::disk('local')->put('private/gallery-media/sentinel.jpg', $this->validJpegBytes());

        $this->actingAs($operator);

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->fillForm([
                'cover_path' => ['private/event-covers/../gallery-media/sentinel.jpg'],
                'cover_alt_text' => 'X',
            ])
            ->call('save')
            ->assertHasFormErrors(['cover_path']);

        $this->assertNull($event->refresh()->cover_path);
        Storage::disk('local')->assertExists('private/gallery-media/sentinel.jpg');
    }

    public function test_media_upload_refuses_a_traversal_path(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $unit = OrganizationUnit::factory()->create();
        $operator = $this->operator($unit);
        $gallery = Gallery::factory()->create(['organization_unit_id' => $unit->id]);
        Storage::disk('local')->put('private/event-covers/sentinel.jpg', $this->validJpegBytes());

        $this->actingAs($operator);

        Livewire::test(GalleryMediaRelationManager::class, [
            'ownerRecord' => $gallery,
            'pageClass' => EditGallery::class,
        ])
            ->callTableAction('create', data: [
                'file' => ['private/gallery-media/../event-covers/sentinel.jpg'],
                'alt_text' => 'X',
                'sort_order' => 0,
            ])
            ->assertHasTableActionErrors(['file']);

        $this->assertSame(0, Media::query()->count());
        Storage::disk('local')->assertExists('private/event-covers/sentinel.jpg');
    }

    public function test_media_upload_rejects_unsafe_path_shapes(): void
    {
        Storage::fake('local');
        $unit = OrganizationUnit::factory()->create();
        $operator = $this->operator($unit);
        $gallery = Gallery::factory()->create(['organization_unit_id' => $unit->id]);

        $this->actingAs($operator);

        foreach ([
            '/private/gallery-media/x.jpg',              // absolute
            'private\\gallery-media\\x.jpg',             // backslash
            'private//gallery-media/x.jpg',              // repeated separator
            'private/gallery-media/./x.jpg',             // dot segment
            'private/gallery-media/x.jpg/',              // trailing separator
            "private/gallery-media/x\0.jpg",             // null byte
        ] as $path) {
            Livewire::test(GalleryMediaRelationManager::class, [
                'ownerRecord' => $gallery,
                'pageClass' => EditGallery::class,
            ])
                ->callTableAction('create', data: [
                    'file' => [$path],
                    'alt_text' => 'X',
                    'sort_order' => 0,
                ])
                ->assertHasTableActionErrors(['file']);
        }

        $this->assertSame(0, Media::query()->count());
    }

    public function test_media_upload_deletes_the_staged_file_when_the_metadata_probe_throws(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $unit = OrganizationUnit::factory()->create();
        $operator = $this->operator($unit);
        $gallery = Gallery::factory()->create(['organization_unit_id' => $unit->id]);

        // mimeType() throws; the staged file must still be cleaned up.
        $this->wrapDisk('local', mimeTypeResult: fn (): never => throw new \RuntimeException('probe failed'));

        $this->actingAs($operator);

        try {
            Livewire::test(GalleryMediaRelationManager::class, [
                'ownerRecord' => $gallery,
                'pageClass' => EditGallery::class,
            ])->callTableAction('create', data: [
                'file' => UploadedFile::fake()->image('foto.jpg', 800, 600),
                'alt_text' => 'Peserta latihan',
                'sort_order' => 0,
            ]);
        } catch (\RuntimeException) {
            // surfaced through the harness
        }

        $this->assertSame(0, Media::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_publish_media_surfaces_a_thrown_compensation_delete_without_masking_the_db_exception(): void
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
        Storage::disk('local')->put('private/gallery-media/foto.jpg', $this->validJpegBytes());

        $this->wrapDisk('public', deleteResult: fn (): never => throw new \RuntimeException('delete failed'));

        Log::shouldReceive('error')->atLeast()->once()->withArgs(
            fn (string $message): bool => str_contains($message, 'Gagal membersihkan berkas publik setelah kegagalan basis data'),
        );

        Media::updating(function (): void {
            throw new \RuntimeException('database down');
        });

        try {
            app(PublishMediaToPortal::class)->publish($operator, $media);
            $this->fail('Expected the DB exception to bubble up.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('database down', $exception->getMessage());
        } finally {
            Media::flushEventListeners();
        }

        $media->refresh();
        $this->assertFalse($media->is_public);
        $this->assertSame('local', $media->disk);
        Storage::disk('local')->assertExists('private/gallery-media/foto.jpg');
    }

    public function test_publish_media_surfaces_a_thrown_copy_compensation_delete_without_masking_the_copy_exception(): void
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
        Storage::disk('local')->put('private/gallery-media/foto.jpg', $this->validJpegBytes());

        $this->wrapDisk(
            'public',
            deleteResult: fn (): never => throw new \RuntimeException('delete failed'),
            putResult: fn (): never => throw new \RuntimeException('copy failed'),
        );

        Log::shouldReceive('error')->atLeast()->once()->withArgs(
            fn (string $message): bool => str_contains($message, 'Gagal membersihkan berkas publik setelah kegagalan salin'),
        );

        $this->actingAs($operator);

        try {
            app(PublishMediaToPortal::class)->publish($operator, $media);
            $this->fail('Expected the copy exception to bubble up.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('copy failed', $exception->getMessage());
        }

        $media->refresh();
        $this->assertFalse($media->is_public);
        $this->assertSame('local', $media->disk);
        Storage::disk('local')->assertExists('private/gallery-media/foto.jpg');
    }

    private function failLocalDiskDeletes(\Closure $deleteResult): void
    {
        $this->wrapDisk('local', deleteResult: $deleteResult);
    }

    private function failPublicDiskDeletes(\Closure $deleteResult): void
    {
        $this->wrapDisk('public', deleteResult: $deleteResult);
    }

    private function failPublicDiskWritesAndDeletes(): void
    {
        $this->wrapDisk(
            'public',
            deleteResult: fn (): bool => false,
            putResult: fn (): never => throw new \RuntimeException('copy failed'),
        );
    }

    /**
     * Rebind a disk to a thin adapter whose `delete()`/`put()` results are
     * controlled, so cleanup-failure handling can be exercised deterministically.
     */
    private function wrapDisk(string $disk, ?\Closure $deleteResult = null, ?\Closure $putResult = null, ?\Closure $mimeTypeResult = null): void
    {
        /** @var FilesystemAdapter $original */
        $original = Storage::disk($disk);

        $wrapper = new class($original, $deleteResult, $putResult, $mimeTypeResult) extends FilesystemAdapter
        {
            public function __construct(
                FilesystemAdapter $inner,
                private ?\Closure $deleteResult,
                private ?\Closure $putResult,
                private ?\Closure $mimeTypeResult,
            ) {
                parent::__construct($inner->getDriver(), $inner->getAdapter(), $inner->getConfig());
            }

            public function delete($paths)
            {
                return $this->deleteResult ? ($this->deleteResult)() : parent::delete($paths);
            }

            public function put($path, $contents, $options = [])
            {
                return $this->putResult ? ($this->putResult)() : parent::put($path, $contents, $options);
            }

            public function mimeType($path)
            {
                return $this->mimeTypeResult ? ($this->mimeTypeResult)() : parent::mimeType($path);
            }
        };

        Storage::set($disk, $wrapper);
    }

    private function validJpegBytes(): string
    {
        // A minimal, valid 1x1 JPEG so content-based MIME detection passes
        // independently of the disk/prefix guard under test.
        return base64_decode(
            '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////'.
            '////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBAB'.
            'AAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=',
            true,
        );
    }

    private function publishedGallery(OrganizationUnit $unit): Gallery
    {
        return Gallery::factory()->create([
            'organization_unit_id' => $unit->id,
            'status' => PublicationStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function test_event_cover_cannot_reuse_another_events_private_cover_path(): void
    {
        Storage::fake('local');
        $unit = OrganizationUnit::factory()->create();
        $operator = $this->operator($unit);
        $foreignPath = $this->jpeg('foreign.jpg')->store('private/event-covers', 'local');
        $foreign = Event::factory()->create([
            'organization_unit_id' => $unit->id,
            'cover_path' => $foreignPath,
        ]);
        $target = Event::factory()->create(['organization_unit_id' => $unit->id]);

        $this->actingAs($operator);

        Livewire::test(EditEvent::class, ['record' => $target->getRouteKey()])
            ->fillForm(['cover_path' => [$foreignPath], 'cover_alt_text' => 'Cover reuse'])
            ->call('save')
            ->assertHasFormErrors(['cover_path']);

        $this->assertNull($target->refresh()->cover_path);
        $this->assertSame($foreignPath, $foreign->refresh()->cover_path);
        Storage::disk('local')->assertExists($foreignPath);
    }

    public function test_gallery_media_cannot_reuse_another_gallery_private_upload(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $unit = OrganizationUnit::factory()->create();
        $operator = $this->operator($unit);
        $source = Gallery::factory()->create(['organization_unit_id' => $unit->id]);
        $target = Gallery::factory()->create(['organization_unit_id' => $unit->id]);
        $foreignPath = $this->jpeg('foreign-media.jpg')->store('private/gallery-media', 'local');
        $foreignMedia = Media::factory()->create([
            'gallery_id' => $source->id,
            'organization_unit_id' => $unit->id,
            'disk' => 'local',
            'path' => $foreignPath,
            'mime_type' => 'image/jpeg',
            'size' => Storage::disk('local')->size($foreignPath),
            'is_public' => false,
        ]);

        $this->actingAs($operator);

        Livewire::test(GalleryMediaRelationManager::class, [
            'ownerRecord' => $target,
            'pageClass' => EditGallery::class,
        ])
            ->callTableAction('create', data: [
                'file' => [$foreignPath],
                'alt_text' => 'Bukan upload baru',
                'sort_order' => 0,
            ])
            ->assertHasTableActionErrors(['file']);

        $this->assertSame(1, Media::query()->count());
        $this->assertSame($source->id, $foreignMedia->refresh()->gallery_id);
        Storage::disk('local')->assertExists($foreignPath);
    }

    public function test_gallery_owner_unit_cannot_change_after_media_exists(): void
    {
        $source = OrganizationUnit::factory()->create();
        $destination = OrganizationUnit::factory()->create();
        $operator = $this->operator($source);
        UnitAssignment::factory()->create([
            'user_id' => $operator->id,
            'organization_unit_id' => $destination->id,
        ]);
        $gallery = Gallery::factory()->create([
            'organization_unit_id' => $source->id,
            'category' => GalleryCategory::Latihan,
        ]);
        Media::factory()->create([
            'gallery_id' => $gallery->id,
            'organization_unit_id' => $source->id,
        ]);

        $this->actingAs($operator);

        Livewire::test(EditGallery::class, ['record' => $gallery->getRouteKey()])
            ->fillForm(['organization_unit_id' => $destination->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($source->id, $gallery->refresh()->organization_unit_id);
        $this->assertSame($source->id, $gallery->media()->firstOrFail()->organization_unit_id);
    }
}
