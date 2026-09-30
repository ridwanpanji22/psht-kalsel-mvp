<?php

namespace App\Actions;

use App\Models\AuditEvent;
use App\Models\UnitAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateUnitAssignment
{
    /**
     * @param  array{user_id: int, organization_unit_id: int, role: string}  $attributes
     */
    public function handle(User $actor, array $attributes): UnitAssignment
    {
        if ($attributes['role'] !== 'operator') {
            throw ValidationException::withMessages([
                'role' => 'Role assignment ini tidak diizinkan.',
            ]);
        }

        if (! in_array($attributes['organization_unit_id'], $actor->visibleUnitIds(), true)) {
            throw ValidationException::withMessages([
                'organization_unit_id' => 'Unit berada di luar cakupan operator.',
            ]);
        }

        return DB::transaction(function () use ($actor, $attributes): UnitAssignment {
            $assignment = UnitAssignment::query()->create([
                'user_id' => $attributes['user_id'],
                'organization_unit_id' => $attributes['organization_unit_id'],
                'role' => 'operator',
                'is_active' => true,
            ]);

            AuditEvent::query()->create([
                'actor_id' => $actor->id,
                'subject_type' => $assignment->getMorphClass(),
                'subject_id' => $assignment->id,
                'action' => 'operator.assignment_created',
                'metadata' => [
                    'user_id' => $assignment->user_id,
                    'organization_unit_id' => $assignment->organization_unit_id,
                ],
            ]);

            return $assignment;
        });
    }
}
