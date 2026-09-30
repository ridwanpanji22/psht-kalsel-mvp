<?php

namespace App\Filament\Resources\Users\Tables;

use App\Support\OrganizationScope;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->whereHas(
                'unitAssignments',
                fn (Builder $assignmentQuery) => $assignmentQuery->whereIn(
                    'organization_unit_id',
                    app(OrganizationScope::class)->visibleUnitIds(auth()->user()),
                ),
            ))
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Lengkap')
                    ->searchable()
                    ->placeholder('Menunggu Aktivasi'),
                TextColumn::make('email')
                    ->label('Email Gmail')
                    ->searchable(),
                TextColumn::make('unitAssignments.organizationUnit.name')
                    ->label('Unit')
                    ->badge(),
                IconColumn::make('is_active')
                    ->label('Status Aktif')
                    ->boolean(),
                TextColumn::make('onboarded_at')
                    ->label('Tanggal Aktivasi')
                    ->dateTime()
                    ->placeholder('Belum Aktivasi'),
            ])
            ->emptyStateHeading('Belum ada operator');
    }
}
