<?php

namespace App\Filament\Resources\Memberships\Tables;

use App\Enums\MembershipStatus;
use App\Enums\MembershipType;
use App\Models\OrganizationUnit;
use App\Support\OrganizationScope;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MembershipsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->whereIn(
                'organization_unit_id',
                app(OrganizationScope::class)->visibleUnitIds(auth()->user()),
            ))
            ->columns([
                TextColumn::make('person.name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('organizationUnit.name')->label('Unit'),
                TextColumn::make('type')
                    ->label('Kategori')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => match (true) {
                        $state instanceof MembershipType => ucfirst($state->value),
                        is_string($state) => ucfirst($state),
                        default => (string) $state,
                    }),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => match (true) {
                        $state instanceof MembershipStatus => $state->label(),
                        is_string($state) => MembershipStatus::tryFrom($state)?->label() ?? ucfirst($state),
                        default => (string) $state,
                    }),
                TextColumn::make('member_number')->label('Nomor warga')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(MembershipStatus::cases())->mapWithKeys(
                    fn (MembershipStatus $status): array => [$status->value => $status->label()],
                )->all()),
                SelectFilter::make('organization_unit_id')
                    ->label('Unit')
                    ->options(fn (): array => OrganizationUnit::query()
                        ->whereIn('id', app(OrganizationScope::class)->visibleUnitIds(auth()->user()))
                        ->orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->emptyStateHeading('Belum ada anggota')
            ->emptyStateDescription('Tambahkan anggota pada unit dalam cakupan Anda.')
            ->recordActions([]);
    }
}
