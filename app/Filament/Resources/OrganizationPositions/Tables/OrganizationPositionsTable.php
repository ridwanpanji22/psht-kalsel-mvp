<?php

namespace App\Filament\Resources\OrganizationPositions\Tables;

use App\Support\OrganizationScope;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrganizationPositionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->whereIn(
                'organization_unit_id',
                app(OrganizationScope::class)->visibleUnitIds(auth()->user()),
            ))
            ->columns([
                TextColumn::make('title')->label('Jabatan')->searchable()->sortable(),
                TextColumn::make('holder_name')->label('Pejabat')->searchable(),
                TextColumn::make('organizationUnit.name')->label('Unit'),
                TextColumn::make('starts_on')->label('Mulai')->date()->sortable(),
                TextColumn::make('ends_on')->label('Selesai')->date()->placeholder('Aktif'),
            ])
            ->filters([
                SelectFilter::make('organization_unit_id')
                    ->label('Unit')
                    ->options(fn (): array => OrganizationUnit::query()
                        ->whereIn('id', app(OrganizationScope::class)->visibleUnitIds(auth()->user()))
                        ->orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
