<?php

namespace App\Support;

use App\Models\OrganizationUnit;
use App\Models\User;

class OrganizationScope
{
    /**
     * @return list<int>
     */
    public function visibleUnitIds(User $user): array
    {
        $rootIds = $user->unitAssignments()
            ->where('is_active', true)
            ->pluck('organization_unit_id')
            ->all();

        if ($rootIds === []) {
            return [];
        }

        $visibleIds = $rootIds;

        do {
            $childIds = OrganizationUnit::query()
                ->whereIn('parent_id', $visibleIds)
                ->whereNotIn('id', $visibleIds)
                ->pluck('id')
                ->all();

            $visibleIds = [...$visibleIds, ...$childIds];
        } while ($childIds !== []);

        return array_values(array_unique($visibleIds));
    }
}
