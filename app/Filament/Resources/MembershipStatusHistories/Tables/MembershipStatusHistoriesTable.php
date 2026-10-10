<?php

namespace App\Filament\Resources\MembershipStatusHistories\Tables;

use App\Enums\MembershipStatus;
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
                TextColumn::make('from_status')
                    ->label('Dari')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => self::statusLabel($state)),
                TextColumn::make('to_status')
                    ->label('Ke')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => self::statusLabel($state)),
                TextColumn::make('actor.name')->label('Pelaku'),
                TextColumn::make('created_at')->label('Waktu')->dateTime(),
            ])
            ->emptyStateHeading('Belum ada riwayat status');
    }

    /**
     * The `from_status`/`to_status` columns are cast to a `MembershipStatus`
     * enum by the model, so the column state arrives as an enum instance (or
     * null), never a raw scalar. Render its Indonesian label; fall back to the
     * raw value for any unexpected scalar so the table never throws.
     */
    private static function statusLabel(mixed $state): string
    {
        if ($state instanceof MembershipStatus) {
            return $state->label();
        }

        if ($state === null || $state === '') {
            return '—';
        }

        return MembershipStatus::tryFrom((string) $state)?->label() ?? (string) $state;
    }
}
