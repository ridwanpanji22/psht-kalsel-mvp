<?php

namespace App\Policies;

use App\Models\Gallery;
use App\Models\User;

class GalleryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->visibleUnitIds() !== [];
    }

    public function view(User $user, Gallery $gallery): bool
    {
        return $user->is_active && in_array($gallery->organization_unit_id, $user->visibleUnitIds(), true);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->visibleUnitIds() !== [];
    }

    public function update(User $user, Gallery $gallery): bool
    {
        return $this->view($user, $gallery);
    }

    public function delete(User $user, Gallery $gallery): bool
    {
        return false;
    }
}
