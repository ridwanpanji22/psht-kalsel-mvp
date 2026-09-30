<?php

namespace App\Policies;

use App\Models\OrganizationPosition;
use App\Models\User;

class OrganizationPositionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->visibleUnitIds() !== [];
    }

    public function view(User $user, OrganizationPosition $position): bool
    {
        return $user->is_active && in_array($position->organization_unit_id, $user->visibleUnitIds(), true);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->visibleUnitIds() !== [];
    }

    public function update(User $user, OrganizationPosition $position): bool
    {
        return $this->view($user, $position);
    }

    public function delete(User $user, OrganizationPosition $position): bool
    {
        return false;
    }
}
