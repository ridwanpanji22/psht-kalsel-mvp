<?php

namespace App\Actions;

use App\Enums\MembershipStatus;
use App\Models\AuditEvent;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeMembershipStatus
{
    public function handle(
        User $actor,
        Membership $membership,
        MembershipStatus $target,
        ?string $reason = null,
    ): Membership {
        if (! $membership->status->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => 'Perubahan status anggota tidak diizinkan.',
            ]);
        }

        return DB::transaction(function () use ($actor, $membership, $target, $reason): Membership {
            $from = $membership->status;
            $membership->update([
                'status' => $target,
                'archived_at' => $target === MembershipStatus::Archived ? now() : null,
            ]);
            $membership->statusHistories()->create([
                'actor_id' => $actor->id,
                'from_status' => $from,
                'to_status' => $target,
                'reason' => $reason,
            ]);
            AuditEvent::query()->create([
                'actor_id' => $actor->id,
                'subject_type' => $membership->getMorphClass(),
                'subject_id' => $membership->id,
                'action' => 'membership.status_changed',
                'metadata' => ['from' => $from->value, 'to' => $target->value],
            ]);

            return $membership->refresh();
        });
    }
}
