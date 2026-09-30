<?php

namespace App\Actions;

use App\Enums\MembershipStatus;
use App\Enums\MembershipType;
use App\Models\AuditEvent;
use App\Models\Membership;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateMembership
{
    /**
     * @param  array{name: string, organization_unit_id: int, type: MembershipType, member_number?: string|null}  $attributes
     */
    public function handle(User $actor, array $attributes): Membership
    {
        $memberNumber = isset($attributes['member_number']) ? trim((string) $attributes['member_number']) : null;
        $memberNumber = $memberNumber === '' ? null : $memberNumber;

        if (! in_array($attributes['organization_unit_id'], $actor->visibleUnitIds(), true)) {
            throw ValidationException::withMessages([
                'organization_unit_id' => 'Unit berada di luar cakupan operator.',
            ]);
        }

        if ($attributes['type'] === MembershipType::Warga && $memberNumber === null) {
            throw ValidationException::withMessages([
                'member_number' => 'Nomor warga wajib diisi.',
            ]);
        }

        if ($attributes['type'] === MembershipType::Siswa && $memberNumber !== null) {
            throw ValidationException::withMessages([
                'member_number' => 'Siswa tidak memiliki nomor warga.',
            ]);
        }

        if ($memberNumber !== null && mb_strlen($memberNumber) > 100) {
            throw ValidationException::withMessages([
                'member_number' => 'Nomor warga maksimal 100 karakter.',
            ]);
        }

        return DB::transaction(function () use ($actor, $attributes, $memberNumber): Membership {
            if ($memberNumber !== null && Membership::query()->where('member_number', $memberNumber)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages([
                    'member_number' => 'Nomor warga sudah digunakan.',
                ]);
            }

            $person = Person::query()->create(['name' => $attributes['name']]);
            $membership = Membership::query()->create([
                'person_id' => $person->id,
                'organization_unit_id' => $attributes['organization_unit_id'],
                'type' => $attributes['type'],
                'status' => MembershipStatus::Active,
                'member_number' => $memberNumber,
                'started_on' => now()->toDateString(),
            ]);

            $membership->statusHistories()->create([
                'actor_id' => $actor->id,
                'from_status' => null,
                'to_status' => MembershipStatus::Active,
                'reason' => 'created',
            ]);

            AuditEvent::query()->create([
                'actor_id' => $actor->id,
                'subject_type' => $membership->getMorphClass(),
                'subject_id' => $membership->id,
                'action' => 'membership.created',
                'metadata' => ['type' => $attributes['type']->value],
            ]);

            return $membership->load('person');
        });
    }
}
