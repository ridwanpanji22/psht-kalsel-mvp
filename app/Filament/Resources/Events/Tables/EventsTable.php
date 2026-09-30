<?php

namespace App\Filament\Resources\Events\Tables;

use App\Enums\PublicationStatus;
use App\Models\OrganizationUnit;
use App\Support\OrganizationScope;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->whereIn(
                'organization_unit_id',
                app(OrganizationScope::class)->visibleUnitIds(auth()->user()),
            ))
            ->columns([
                TextColumn::make('title')->label('Kegiatan')->searchable()->sortable(),
                TextColumn::make('organizationUnit.name')->label('Unit'),
                TextColumn::make('starts_at')->label('Mulai')->dateTime()->sortable(),
                TextColumn::make('status')->label('Status')->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(PublicationStatus::cases())->mapWithKeys(
                    fn (PublicationStatus $status): array => [$status->value => ucfirst(str_replace('_', ' ', $status->value))],
                )->all()),
                SelectFilter::make('organization_unit_id')->label('Unit')->options(fn (): array => OrganizationUnit::query()
                    ->whereIn('id', app(OrganizationScope::class)->visibleUnitIds(auth()->user()))
                    ->orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
