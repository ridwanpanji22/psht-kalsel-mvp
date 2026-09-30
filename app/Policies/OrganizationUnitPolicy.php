<?php

namespace App\Policies;

use App\Models\OrganizationUnit;
use App\Models\User;

class OrganizationUnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->visibleUnitIds() !== [];
    }

    public function view(User $user, OrganizationUnit $unit): bool
    {
        return $user->is_active && in_array($unit->id, $user->visibleUnitIds(), true);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->visibleUnitIds() !== [];
    }

    public function update(User $user, OrganizationUnit $unit): bool
    {
        return $this->view($user, $unit);
    }

    public function delete(User $user, OrganizationUnit $unit): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
