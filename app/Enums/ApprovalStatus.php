<?php

namespace App\Enums;

enum ApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Returned = 'returned';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Approved => 'Disetujui',
            self::Returned => 'Dikembalikan',
            self::Rejected => 'Ditolak',
        };
    }
}
