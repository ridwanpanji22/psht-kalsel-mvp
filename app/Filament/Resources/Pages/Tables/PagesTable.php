<?php

namespace App\Filament\Resources\Pages\Tables;

use App\Enums\PublicationStatus;
use App\Models\OrganizationUnit;
use App\Support\OrganizationScope;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->where(function ($query): void {
                $query->whereNull('organization_unit_id')
                    ->orWhereIn('organization_unit_id', app(OrganizationScope::class)->visibleUnitIds(auth()->user()));
            }))
            ->columns([
                TextColumn::make('title')->label('Judul')->searchable()->sortable(),
                TextColumn::make('kind')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => match ((string) $state) {
                        'general_history' => 'Sejarah umum',
                        'branch_history' => 'Sejarah Cabang',
                        'organization_profile' => 'Profil organisasi',
                        default => (string) $state,
                    }),
                TextColumn::make('organizationUnit.name')->label('Unit')->placeholder('Provinsi'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => match (true) {
                        $state instanceof PublicationStatus => $state->label(),
                        is_string($state) => PublicationStatus::tryFrom($state)?->label() ?? ucfirst($state),
                        default => (string) $state,
                    }),
                TextColumn::make('published_at')->label('Diterbitkan')->dateTime()->placeholder('Belum'),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(PublicationStatus::cases())->mapWithKeys(
                    fn (PublicationStatus $status): array => [$status->value => $status->label()],
                )->all()),
                SelectFilter::make('organization_unit_id')->label('Unit')->options(fn (): array => OrganizationUnit::query()
                    ->whereIn('id', app(OrganizationScope::class)->visibleUnitIds(auth()->user()))
                    ->orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->emptyStateHeading('Belum ada halaman')
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
