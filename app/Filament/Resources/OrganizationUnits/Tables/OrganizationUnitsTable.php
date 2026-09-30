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
                TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => match (true) {
                        $state === OrganizationUnitType::Provinsi || $state === 'provinsi' => 'Provinsi',
                        $state === OrganizationUnitType::Cabang || $state === 'cabang' => 'Cabang',
                        $state === OrganizationUnitType::Ranting || $state === 'ranting' => 'Ranting',
                        $state === OrganizationUnitType::Rayon || $state === 'rayon' => 'Rayon',
                        $state === OrganizationUnitType::Ukm || $state === 'ukm' => 'UKM',
                        $state === OrganizationUnitType::Komisariat || $state === 'komisariat' => 'Komisariat',
                        default => (string) $state,
                    }),
                TextColumn::make('parent.name')->label('Induk formal')->placeholder('Provinsi'),
                TextColumn::make('supervisingBranch.name')->label('Cabang pembina')->placeholder('Mandiri'),
            ])
            ->filters([
                SelectFilter::make('type')->options([
                    'provinsi' => 'Provinsi',
                    'cabang' => 'Cabang',
                    'ranting' => 'Ranting',
                    'rayon' => 'Rayon',
                    'ukm' => 'UKM',
                    'komisariat' => 'Komisariat',
                ]),
            ])
            ->emptyStateHeading('Belum ada unit organisasi')
            ->emptyStateDescription('Tambahkan unit organisasi dalam cakupan Anda.')
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
