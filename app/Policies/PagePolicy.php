<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->visibleUnitIds() !== [];
    }

    public function view(User $user, Page $page): bool
    {
        return $user->is_active && ($page->organization_unit_id === null
            || in_array($page->organization_unit_id, $user->visibleUnitIds(), true));
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->visibleUnitIds() !== [];
    }

    public function update(User $user, Page $page): bool
    {
        return $this->view($user, $page);
    }

    public function delete(User $user, Page $page): bool
    {
        return false;
    }
}
