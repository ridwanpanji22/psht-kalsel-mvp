<?php

namespace App\Filament\Resources\Memberships\Tables;

use App\Enums\MembershipStatus;
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
                TextColumn::make('type')->label('Kategori')->badge(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('member_number')->label('Nomor warga')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(MembershipStatus::cases())->mapWithKeys(
                    fn (MembershipStatus $status): array => [$status->value => ucfirst($status->value)],
                )->all()),
                SelectFilter::make('organization_unit_id')
                    ->label('Unit')
                    ->options(fn (): array => OrganizationUnit::query()
                        ->whereIn('id', app(OrganizationScope::class)->visibleUnitIds(auth()->user()))
                        ->orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->recordActions([]);
    }
}
