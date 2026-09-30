<?php

namespace App\Actions;

use App\Contracts\FirebaseAuthentication;
use App\Models\AuditEvent;
use App\Models\OrganizationUnit;
use App\Models\UnitAssignment;
use App\Models\User;
use App\OrganizationUnitType;
use App\Support\OrganizationScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateOperator
{
    public function __construct(
        protected FirebaseAuthentication $firebase
    ) {}

    /**
     * Provision a pending operator account and dispatch a Firebase invite.
     *
     * @param  array{email: string, organization_unit_id: int}  $data
     */
    public function handle(User $actor, array $data): User
    {
        $targetUnitId = (int) $data['organization_unit_id'];

        $this->validateAuthority($actor, $targetUnitId);
        $this->validateEmail($data['email']);

        return DB::transaction(function () use ($actor, $data, $targetUnitId): User {
            $user = User::query()->create([
                'name' => null,
                'email' => strtolower($data['email']),
                'password' => Str::random(32),
                'is_active' => false,
                'onboarded_at' => null,
            ]);

            UnitAssignment::query()->create([
                'user_id' => $user->id,
                'organization_unit_id' => $targetUnitId,
                'role' => 'operator',
                'is_active' => false,
            ]);

            $firebaseResult = $this->firebase->createUserWithInviteLink($user->email);

            $user->update(['firebase_uid' => $firebaseResult['uid']]);

            AuditEvent::query()->create([
                'actor_id' => $actor->id,
                'subject_type' => $user->getMorphClass(),
                'subject_id' => $user->id,
                'action' => 'operator.invited',
                'metadata' => [
                    'email' => $user->email,
                    'organization_unit_id' => $targetUnitId,
                    'firebase_uid' => $firebaseResult['uid'],
                ],
            ]);

            return $user;
        });
    }

    protected function validateAuthority(User $actor, int $targetUnitId): void
    {
        $actorUnits = $actor->unitAssignments()
            ->where('is_active', true)
            ->with('organizationUnit')
            ->get();

        // A provinsi operator may provision any unit in the organization.
        $isProvinsiOperator = $actorUnits->contains(
            fn (UnitAssignment $assignment): bool => $assignment->organizationUnit?->type === OrganizationUnitType::Provinsi
        );

        if ($isProvinsiOperator) {
            return;
        }

        // A cabang operator may provision only ranting/rayon inside its formal subtree.
        $isCabangOperator = $actorUnits->contains(
            fn (UnitAssignment $assignment): bool => $assignment->organizationUnit?->type === OrganizationUnitType::Cabang
        );

        if ($isCabangOperator) {
            $visibleUnitIds = app(OrganizationScope::class)->visibleUnitIds($actor);
            $targetUnit = OrganizationUnit::query()->find($targetUnitId);

            $targetIsInvitable = $targetUnit !== null
                && in_array($targetUnit->type, [OrganizationUnitType::Ranting, OrganizationUnitType::Rayon], true);

            if ($targetIsInvitable && in_array($targetUnitId, $visibleUnitIds, true)) {
                return;
            }
        }

        throw ValidationException::withMessages([
            'organization_unit_id' => 'Anda tidak memiliki kewenangan untuk membuat akun operator pada unit ini.',
        ]);
    }

    protected function validateEmail(string $email): void
    {
        if (! str_ends_with(strtolower($email), '@gmail.com')) {
            throw ValidationException::withMessages([
                'email' => 'Hanya alamat Google (@gmail.com) yang dapat didaftarkan sebagai operator.',
            ]);
        }

        if (User::query()->where('email', strtolower($email))->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Email ini sudah terdaftar di dalam sistem.',
            ]);
        }
    }
}
