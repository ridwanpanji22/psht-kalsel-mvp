<?php

namespace App\Policies;

use App\Models\UnitAssignment;
use App\Models\User;

class UnitAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->visibleUnitIds() !== [];
    }

    public function view(User $user, UnitAssignment $assignment): bool
    {
        return $user->is_active && in_array($assignment->organization_unit_id, $user->visibleUnitIds(), true);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->visibleUnitIds() !== [];
    }

    public function update(User $user, UnitAssignment $assignment): bool
    {
        return false;
    }

    public function delete(User $user, UnitAssignment $assignment): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
