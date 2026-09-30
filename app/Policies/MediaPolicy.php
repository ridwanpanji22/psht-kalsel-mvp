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
}
