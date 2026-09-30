<?php

namespace App\Filament\Resources\OrganizationUnits\Tables;

use App\OrganizationUnitType;
use App\Support\OrganizationScope;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrganizationUnitsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->whereIn(
                'id',
                app(OrganizationScope::class)->visibleUnitIds(auth()->user()),
            ))
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('type')->label('Jenis')->badge(),
                TextColumn::make('parent.name')->label('Induk formal')->placeholder('Provinsi'),
                TextColumn::make('supervisingBranch.name')->label('Cabang pembina')->placeholder('Mandiri'),
            ])
            ->filters([
                SelectFilter::make('type')->options(collect(OrganizationUnitType::cases())->mapWithKeys(
                    fn (OrganizationUnitType $type): array => [$type->value => ucfirst($type->value)],
                )->all()),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
