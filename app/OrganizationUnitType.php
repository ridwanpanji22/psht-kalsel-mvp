<?php

namespace App;

enum OrganizationUnitType: string
{
    case Provinsi = 'provinsi';
    case Cabang = 'cabang';
    case Ranting = 'ranting';
    case Rayon = 'rayon';
    case Ukm = 'ukm';
    case Komisariat = 'komisariat';

    public function expectedParentType(): ?self
    {
        return match ($this) {
            self::Provinsi => null,
            self::Cabang, self::Ukm, self::Komisariat => self::Provinsi,
            self::Ranting => self::Cabang,
            self::Rayon => self::Ranting,
        };
    }

    public function mayHaveChildren(): bool
    {
        return match ($this) {
            self::Provinsi, self::Cabang, self::Ranting => true,
            self::Rayon, self::Ukm, self::Komisariat => false,
        };
    }

    public function mayHaveSupervisingBranch(): bool
    {
        return $this === self::Ukm || $this === self::Komisariat;
    }
}
