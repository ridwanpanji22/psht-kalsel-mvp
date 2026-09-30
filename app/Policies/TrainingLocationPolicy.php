<?php

namespace App\Policies;

use App\Models\TrainingLocation;
use App\Models\User;

class TrainingLocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->visibleUnitIds() !== [];
    }

    public function view(User $user, TrainingLocation $location): bool
    {
        return $user->is_active && in_array($location->organization_unit_id, $user->visibleUnitIds(), true);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->visibleUnitIds() !== [];
    }

    public function update(User $user, TrainingLocation $location): bool
    {
        return $this->view($user, $location);
    }

    public function delete(User $user, TrainingLocation $location): bool
    {
        return false;
    }
}
