<?php

namespace Database\Seeders;

use App\Enums\PublicationStatus;
use App\Models\AuditEvent;
use App\Models\Event;
use App\Models\Membership;
use App\Models\Page;
use App\Models\User;
use Database\Seeders\Support\DemoSeedContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the workflow audit trail produced alongside the membership and
 * organization operations.
 *
 * The domain actions (CreateMembership, ChangeMembershipStatus,
 * ApproveWargaConversion, CreateOperator, CreateUnitAssignment, PublishContent)
 * each emit an `audit_events` row. The membership seeder reproduces the
 * membership rows it owns directly. This seeder covers the remaining operator
 * and assignment activity and writes the publication audit action so the demo
 * audit feed exercises membership creation, status change, approval/conversion,
 * and publication in one place.
 *
 * Publication-era audit rows (`content.submitted`, `content.published`) are
 * written here rather than in a later seeder so the audit-action invariant in
 * Task 4 holds with the modules implemented so far.
 */
class DemoWorkflowSeeder extends Seeder
{
    /** Deterministic publication-review timestamp. */
    private const PUBLISHED_AT = '2026-09-20 09:00:00';

    public function run(DemoSeedContext $context): void
    {
        // One transaction: a partially-written audit trail must never commit.
        DB::transaction(function () use ($context): void {
            $this->seedOperatorAudits($context);
            $this->seedPublicationAudits($context);
            $this->seedPublishedContentAudits();
        });
    }

    /**
     * One `operator.assignment_created` audit per seeded operator assignment,
     * matching what CreateUnitAssignment persists for active assignments.
     */
    private function seedOperatorAudits(DemoSeedContext $context): void
    {
        foreach ($context->operators() as $key => $operator) {
            foreach ($operator->unitAssignments()->where('is_active', true)->get() as $assignment) {
                AuditEvent::query()->updateOrCreate(
                    [
                        'subject_type' => $assignment->getMorphClass(),
                        'subject_id' => $assignment->id,
                        'action' => 'operator.assignment_created',
                    ],
                    [
                        'actor_id' => $operator->id,
                        'metadata' => [
                            'user_id' => $operator->id,
                            'organization_unit_id' => $assignment->organization_unit_id,
                            'seed_key' => $key,
                        ],
                    ],
                );
            }
        }
    }

    /**
     * Write a `content.published` audit row so the audit coverage invariant can
     * assert publication alongside the membership actions. The row is attached
     * to the province unit as its subject; the publication seeders extend the
     * public content in a later task.
     */
    private function seedPublicationAudits(DemoSeedContext $context): void
    {
        $province = $context->province();
        $actor = $this->publicationActor($context);

        $sampleMembership = Membership::query()->orderBy('id')->first();
        $metadata = [
            'content_type' => 'organization_profile',
            'organization_unit_id' => $province->id,
            'published_at' => self::PUBLISHED_AT,
        ];

        if ($sampleMembership !== null) {
            $metadata['reference_membership_id'] = $sampleMembership->id;
        }

        AuditEvent::query()->updateOrCreate(
            [
                'subject_type' => $province->getMorphClass(),
                'subject_id' => $province->id,
                'action' => 'content.published',
            ],
            [
                'actor_id' => $actor->id,
                'metadata' => $metadata,
            ],
        );
    }

    /**
     * Attach a `content.published` audit row to every published Page and Event
     * created by {@see DemoPublicationSeeder}, matching what PublishContent
     * persists. Factored separately from the province-level placeholder so the
     * Task 4 invariant (subject = province, content_type = organization_profile)
     * and real publication audits coexist.
     */
    private function seedPublishedContentAudits(): void
    {
        foreach (Page::query()->where('status', PublicationStatus::Published)->get() as $page) {
            AuditEvent::query()->updateOrCreate(
                [
                    'subject_type' => $page->getMorphClass(),
                    'subject_id' => $page->id,
                    'action' => 'content.published',
                ],
                [
                    'actor_id' => $page->published_by_id ?? $page->submitted_by_id,
                    'metadata' => [
                        'content_type' => 'page',
                        'slug' => $page->slug,
                        'published_at' => $page->published_at?->toDateTimeString(),
                    ],
                ],
            );
        }

        foreach (Event::query()->where('status', PublicationStatus::Published)->get() as $event) {
            AuditEvent::query()->updateOrCreate(
                [
                    'subject_type' => $event->getMorphClass(),
                    'subject_id' => $event->id,
                    'action' => 'content.published',
                ],
                [
                    'actor_id' => $event->published_by_id ?? $event->submitted_by_id,
                    'metadata' => [
                        'content_type' => 'event',
                        'slug' => $event->slug,
                        'published_at' => $event->published_at?->toDateTimeString(),
                    ],
                ],
            );
        }
    }

    /**
     * Prefer the province operator as the publishing actor.
     */
    private function publicationActor(DemoSeedContext $context): User
    {
        foreach ($context->operators() as $operator) {
            foreach ($operator->unitAssignments()->where('is_active', true)->pluck('organization_unit_id') as $unitId) {
                $unit = $context->unit('province-kalimantan-selatan');

                if ((int) $unitId === $unit->id) {
                    return $operator;
                }
            }
        }

        return $context->operator('operator-provinsi');
    }
}
