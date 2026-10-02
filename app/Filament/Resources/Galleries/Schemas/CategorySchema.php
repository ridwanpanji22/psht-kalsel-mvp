<?php

namespace App\Filament\Resources\Galleries\Schemas;

use App\Enums\GalleryCategory;
use Filament\Forms\Components\Select;

class CategorySchema
{
    /**
     * Shared category field so every Filament create/update boundary
     * validates against the fixed GalleryCategory enum. Legacy null
     * albums remain supported and surface as "Belum dikategorikan".
     *
     * Passing the backed enum class-string (rather than a pre-built label
     * map) keeps Filament on its enum-aware option path, so the allowed
     * values are enforced by the form's own validation at the real write
     * boundary. This replaces a raw, driver-specific database check
     * constraint so the migration stays valid on MySQL 8 and SQLite.
     */
    public static function field(): Select
    {
        return Select::make('category')
            ->label('Kategori')
            ->options(GalleryCategory::class)
            ->placeholder('Belum dikategorikan')
            ->required();
    }
}
