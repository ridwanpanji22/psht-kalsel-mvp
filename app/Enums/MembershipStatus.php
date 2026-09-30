<?php

namespace App\Enums;

enum MembershipStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Stopped = 'stopped';
    case Archived = 'archived';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Active => in_array($target, [self::Inactive, self::Stopped, self::Archived], true),
            self::Inactive => in_array($target, [self::Active, self::Stopped, self::Archived], true),
            self::Stopped => $target === self::Archived,
            self::Archived => false,
        };
    }
}
