<?php

namespace App\Filament\Resources\TrainingLocations\Tables;

use App\Enums\PublicationStatus;
use App\Models\OrganizationUnit;
use App\Support\OrganizationScope;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TrainingLocationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->whereIn(
                'organization_unit_id',
                app(OrganizationScope::class)->visibleUnitIds(auth()->user()),
            ))
            ->columns([
                TextColumn::make('name')->label('Tempat latihan')->searchable()->sortable(),
                TextColumn::make('organizationUnit.name')->label('Unit'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => match (true) {
                        $state instanceof PublicationStatus => $state->label(),
                        is_string($state) => PublicationStatus::tryFrom($state)?->label() ?? ucfirst($state),
                        default => (string) $state,
                    }),
                TextColumn::make('verified_at')->label('Verifikasi')->dateTime()->placeholder('Belum'),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(PublicationStatus::cases())->mapWithKeys(
                    fn (PublicationStatus $status): array => [$status->value => $status->label()],
                )->all()),
                SelectFilter::make('organization_unit_id')->label('Unit')->options(fn (): array => OrganizationUnit::query()
                    ->whereIn('id', app(OrganizationScope::class)->visibleUnitIds(auth()->user()))
                    ->orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->emptyStateHeading('Belum ada tempat latihan')
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
