<?php

namespace App\Filament\Resources\MembershipStatusHistories\Tables;

use App\Support\OrganizationScope;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MembershipStatusHistoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->whereHas('membership', fn ($membershipQuery) => $membershipQuery->whereIn(
                'organization_unit_id',
                app(OrganizationScope::class)->visibleUnitIds(auth()->user()),
            )))
            ->columns([
                TextColumn::make('membership.person.name')->label('Anggota'),
                TextColumn::make('from_status')->label('Dari')->badge(),
                TextColumn::make('to_status')->label('Ke')->badge(),
                TextColumn::make('actor.name')->label('Pelaku'),
                TextColumn::make('created_at')->label('Waktu')->dateTime(),
            ]);
    }
}
