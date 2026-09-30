<?php

namespace Tests\Feature;

use App\Actions\PublishContent;
use App\Enums\PublicationStatus;
use App\Models\Event;
use App\Models\Gallery;
use App\Models\OrganizationUnit;
use App\Models\Page;
use App\Models\TrainingLocation;
use App\Models\UnitAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PublicationWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_draft_page_is_hidden_from_public_route(): void
    {
        $page = Page::factory()->create([
            'slug' => 'sejarah-psht',
            'title' => 'Sejarah PSHT Rahasia',
            'body' => 'Draft tidak boleh terbaca.',
            'status' => PublicationStatus::Draft,
        ]);

        $this->get(route('public.pages.show', $page->slug))->assertNotFound();
    }

    public function test_published_page_is_visible_with_escaped_body(): void
    {
        $page = Page::factory()->create([
            'slug' => 'sejarah-psht',
            'title' => 'Sejarah PSHT',
            'body' => '<script>alert(1)</script>Riwayat organisasi.',
            'status' => PublicationStatus::Published,
            'published_at' => now(),
        ]);

        $this->get(route('public.pages.show', $page->slug))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_operator_can_submit_page_for_review_but_cannot_publish_it(): void
    {
        $operator = User::factory()->create();
        $unit = OrganizationUnit::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $operator->id,
            'organization_unit_id' => $unit->id,
        ]);
        $page = Page::factory()->create([
            'organization_unit_id' => $unit->id,
            'status' => PublicationStatus::Draft,
        ]);

        $this->actingAs($operator)
            ->post(route('pages.submit-for-review', $page))
            ->assertRedirect();

        $this->assertSame(PublicationStatus::PendingReview, $page->refresh()->status);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'content.submitted',
            'subject_id' => $page->id,
        ]);
    }

    public function test_rejects_publishing_page_that_has_not_been_reviewed(): void
    {
        $page = Page::factory()->create(['status' => PublicationStatus::Draft]);

        $this->expectException(ValidationException::class);

        app(PublishContent::class)->publish(User::factory()->create(), $page);
    }

    public function test_reviewed_page_publishes_and_is_audited(): void
    {
        $submitter = User::factory()->create();
        $reviewer = User::factory()->create();
        $page = Page::factory()->create([
            'status' => PublicationStatus::PendingReview,
            'submitted_by_id' => $submitter->id,
        ]);

        $published = app(PublishContent::class)->publish($reviewer, $page);

        $this->assertSame(PublicationStatus::Published, $published->status);
        $this->assertSame($reviewer->id, $published->published_by_id);
        $this->assertNotNull($published->published_at);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'content.published',
            'subject_id' => $page->id,
        ]);
    }

    public function test_submitting_operator_cannot_publish_own_page(): void
    {
        $submitter = User::factory()->create();
        $page = Page::factory()->create([
            'status' => PublicationStatus::PendingReview,
            'submitted_by_id' => $submitter->id,
        ]);

        $this->expectException(ValidationException::class);

        app(PublishContent::class)->publish($submitter, $page);
    }

    public function test_public_event_and_training_location_require_published_state(): void
    {
        $unit = OrganizationUnit::factory()->create();
        Event::factory()->create([
            'organization_unit_id' => $unit->id,
            'title' => 'Latihan internal draft',
            'status' => PublicationStatus::Draft,
        ]);
        TrainingLocation::factory()->create([
            'organization_unit_id' => $unit->id,
            'name' => 'Lokasi draft',
            'status' => PublicationStatus::Draft,
        ]);

        $this->get(route('public.events.index'))->assertDontSee('Latihan internal draft');
        $this->get(route('public.training-locations.index'))->assertDontSee('Lokasi draft');
        $this->assertSame(1, Event::query()->count());
        $this->assertSame(1, TrainingLocation::query()->count());
        $this->assertSame(0, Gallery::query()->where('status', PublicationStatus::Published)->count());
    }
}
