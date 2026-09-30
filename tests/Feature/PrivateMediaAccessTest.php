<?php

namespace Tests\Feature;

use App\Models\Gallery;
use App\Models\Media;
use App\Models\OrganizationUnit;
use App\Models\UnitAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivateMediaAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_anonymous_user_cannot_download_private_media(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('private/member-record.pdf', 'private content');
        $unit = OrganizationUnit::factory()->create();
        $media = Media::factory()->create([
            'organization_unit_id' => $unit->id,
            'disk' => 'local',
            'path' => 'private/member-record.pdf',
            'is_public' => false,
        ]);

        $this->get(route('media.show', $media))->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_operator_from_another_unit_cannot_download_private_media(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('private/member-record.pdf', 'private content');
        $unit = OrganizationUnit::factory()->create();
        $otherUnit = OrganizationUnit::factory()->create();
        $operator = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $operator->id,
            'organization_unit_id' => $otherUnit->id,
        ]);
        $media = Media::factory()->create([
            'organization_unit_id' => $unit->id,
            'disk' => 'local',
            'path' => 'private/member-record.pdf',
            'is_public' => false,
        ]);

        $this->actingAs($operator)->get(route('media.show', $media))->assertNotFound();
    }

    public function test_operator_in_unit_scope_can_download_private_media(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('private/member-record.pdf', 'private content');
        $unit = OrganizationUnit::factory()->create();
        $operator = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $operator->id,
            'organization_unit_id' => $unit->id,
        ]);
        $media = Media::factory()->create([
            'organization_unit_id' => $unit->id,
            'disk' => 'local',
            'path' => 'private/member-record.pdf',
            'is_public' => false,
        ]);

        $this->actingAs($operator)
            ->get(route('media.show', $media))
            ->assertDownload();
    }

    public function test_gallery_media_upload_validates_image_content_and_records_private_path(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $unit = OrganizationUnit::factory()->create();
        $gallery = Gallery::factory()->create(['organization_unit_id' => $unit->id]);
        $operator = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $operator->id,
            'organization_unit_id' => $unit->id,
        ]);

        $response = $this->actingAs($operator)->post(route('media.store'), [
            'gallery_id' => $gallery->id,
            'file' => UploadedFile::fake()->image('latihan.jpg', 1200, 800),
            'alt_text' => 'Latihan pencak silat',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('media', [
            'gallery_id' => $gallery->id,
            'organization_unit_id' => $unit->id,
            'is_public' => false,
        ]);
        Storage::disk('local')->assertExists(Media::query()->firstOrFail()->path);
    }
}
