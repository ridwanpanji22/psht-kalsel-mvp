<?php

namespace App\Enums;

enum PublicationStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::PendingReview => 'Menunggu review',
            self::Published => 'Diterbitkan',
            self::Archived => 'Diarsipkan',
        };
    }
}
