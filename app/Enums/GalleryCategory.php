<?php

namespace App\Enums;

enum GalleryCategory: string
{
    case Latihan = 'latihan';
    case Kegiatan = 'kegiatan';
    case Pembinaan = 'pembinaan';

    public function label(): string
    {
        return match ($this) {
            self::Latihan => 'Latihan',
            self::Kegiatan => 'Kegiatan',
            self::Pembinaan => 'Pembinaan',
        };
    }

    /**
     * Nullable value/label map for legacy albums that have no category yet.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $category) {
            $options[$category->value] = $category->label();
        }

        return $options;
    }
}
