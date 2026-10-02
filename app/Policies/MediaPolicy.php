<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MediaPolicy
{
    public function view(User $user, Media $media): Response
    {
        return $user->is_active && in_array($media->organization_unit_id, $user->visibleUnitIds(), true)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->visibleUnitIds() !== [];
    }

    public function update(User $user, Media $media): bool
    {
        return $this->view($user, $media)->allowed();
    }

    public function delete(User $user, Media $media): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
