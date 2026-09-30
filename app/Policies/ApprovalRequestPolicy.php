<?php

namespace App\Policies;

use App\Models\ApprovalRequest;
use App\Models\User;

class ApprovalRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->visibleUnitIds() !== [];
    }

    public function view(User $user, ApprovalRequest $request): bool
    {
        return $user->is_active && in_array(
            $request->membership->organization_unit_id,
            $user->visibleUnitIds(),
            true,
        );
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->visibleUnitIds() !== [];
    }

    public function review(User $user, ApprovalRequest $request): bool
    {
        return $user->id !== $request->requested_by_id && $this->view($user, $request);
    }

    public function delete(User $user, ApprovalRequest $request): bool
    {
        return false;
    }
}
