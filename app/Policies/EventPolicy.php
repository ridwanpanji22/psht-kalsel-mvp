<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->visibleUnitIds() !== [];
    }

    public function view(User $user, Event $event): bool
    {
        return $user->is_active && in_array($event->organization_unit_id, $user->visibleUnitIds(), true);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->visibleUnitIds() !== [];
    }

    public function update(User $user, Event $event): bool
    {
        return $this->view($user, $event);
    }

    public function delete(User $user, Event $event): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
