<?php

namespace App\Filament\Resources\OrganizationPositions\Schemas;

use App\Models\OrganizationUnit;
use App\Support\OrganizationScope;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OrganizationPositionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('organization_unit_id')
                ->label('Unit organisasi')
                ->options(fn (): array => OrganizationUnit::query()
                    ->whereIn('id', app(OrganizationScope::class)->visibleUnitIds(auth()->user()))
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->required(),
            TextInput::make('title')->label('Jabatan')->required()->maxLength(255),
            TextInput::make('holder_name')->label('Nama pejabat')->required()->maxLength(255),
            DatePicker::make('starts_on')->label('Mulai')->required(),
            DatePicker::make('ends_on')->label('Selesai'),
            Select::make('is_public')->label('Tampilkan pada portal')->options([true => 'Ya', false => 'Tidak'])->required(),
        ]);
    }
}
