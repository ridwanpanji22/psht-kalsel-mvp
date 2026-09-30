<?php

namespace App\Filament\Resources\TrainingLocations\Schemas;

use App\Models\OrganizationUnit;
use App\Support\OrganizationScope;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TrainingLocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('organization_unit_id')
                ->label('Unit pengelola')
                ->options(fn (): array => OrganizationUnit::query()
                    ->whereIn('id', app(OrganizationScope::class)->visibleUnitIds(auth()->user()))
                    ->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()->required(),
            TextInput::make('name')->label('Nama tempat')->required()->maxLength(255),
            TextInput::make('slug')->label('Slug')->required()->maxLength(255)->unique(ignoreRecord: true),
            Textarea::make('address')->label('Alamat publik')->required()->rows(3),
            TextInput::make('contact')->label('Kontak publik')->maxLength(255),
            Textarea::make('schedule')->label('Jadwal publik')->rows(3),
            TextInput::make('latitude')->label('Latitude')->numeric()->required()->minValue(-90)->maxValue(90),
            TextInput::make('longitude')->label('Longitude')->numeric()->required()->minValue(-180)->maxValue(180),
        ]);
    }
}
