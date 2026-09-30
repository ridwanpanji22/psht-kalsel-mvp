<?php

namespace App\Actions;

use App\Models\OrganizationUnit;
use App\OrganizationUnitType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateOrganizationUnit
{
    /**
     * @param  array{name: string, type: OrganizationUnitType, parent_id: int|null, supervising_branch_id?: int|null}  $attributes
     */
    public function handle(array $attributes): OrganizationUnit
    {
        return DB::transaction(function () use ($attributes): OrganizationUnit {
            $parent = isset($attributes['parent_id'])
                ? OrganizationUnit::query()->lockForUpdate()->find($attributes['parent_id'])
                : null;
            $supervisor = isset($attributes['supervising_branch_id'])
                ? OrganizationUnit::query()->find($attributes['supervising_branch_id'])
                : null;

            $this->validateHierarchy($attributes['type'], $parent, $supervisor);

            return OrganizationUnit::query()->create([
                'name' => $attributes['name'],
                'type' => $attributes['type'],
                'parent_id' => $parent?->id,
                'supervising_branch_id' => $supervisor?->id,
                'is_active' => true,
            ]);
        });
    }

    private function validateHierarchy(
        OrganizationUnitType $type,
        ?OrganizationUnit $parent,
        ?OrganizationUnit $supervisor,
    ): void {
        if ($parent?->type !== $type->expectedParentType()) {
            throw ValidationException::withMessages([
                'parent_id' => 'Unit harus berada di bawah induk formal yang sah.',
            ]);
        }

        if ($type === OrganizationUnitType::Provinsi
            && OrganizationUnit::query()->where('type', OrganizationUnitType::Provinsi)->lockForUpdate()->exists()) {
            throw ValidationException::withMessages([
                'type' => 'Hanya satu unit provinsi yang diizinkan.',
            ]);
        }

        if ($supervisor !== null && ! $type->mayHaveSupervisingBranch()) {
            throw ValidationException::withMessages([
                'supervising_branch_id' => 'Hanya UKM atau Komisariat yang boleh memiliki Cabang pembina.',
            ]);
        }

        if ($supervisor !== null && $supervisor->type !== OrganizationUnitType::Cabang) {
            throw ValidationException::withMessages([
                'supervising_branch_id' => 'Cabang pembina harus berupa unit Cabang.',
            ]);
        }
    }
}
