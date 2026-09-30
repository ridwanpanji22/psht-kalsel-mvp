<?php

namespace App\Actions;

use App\Models\OrganizationPosition;

class CreateOrganizationPosition
{
    /**
     * @param  array{organization_unit_id: int, title: string, holder_name: string, starts_on: string, ends_on?: string|null, is_public?: bool}  $attributes
     */
    public function handle(array $attributes): OrganizationPosition
    {
        return OrganizationPosition::query()->create([
            'organization_unit_id' => $attributes['organization_unit_id'],
            'title' => $attributes['title'],
            'holder_name' => $attributes['holder_name'],
            'starts_on' => $attributes['starts_on'],
            'ends_on' => $attributes['ends_on'] ?? null,
            'is_public' => $attributes['is_public'] ?? false,
        ]);
    }

    public function end(OrganizationPosition $position, string $endsOn): OrganizationPosition
    {
        $position->update(['ends_on' => $endsOn]);

        return $position->refresh();
    }
}
