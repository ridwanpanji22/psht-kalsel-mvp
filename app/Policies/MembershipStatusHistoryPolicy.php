<?php

namespace App\Policies;

use App\Models\MembershipStatusHistory;
use App\Models\User;

class MembershipStatusHistoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->visibleUnitIds() !== [];
    }

    public function view(User $user, MembershipStatusHistory $history): bool
    {
        return $user->is_active && in_array(
            $history->membership->organization_unit_id,
            $user->visibleUnitIds(),
            true,
        );
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, MembershipStatusHistory $history): bool
    {
        return false;
    }

    public function delete(User $user, MembershipStatusHistory $history): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
