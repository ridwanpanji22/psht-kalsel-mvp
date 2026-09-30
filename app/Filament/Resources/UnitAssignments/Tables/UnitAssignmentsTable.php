<?php

namespace App\Filament\Resources\UnitAssignments\Tables;

use App\Support\OrganizationScope;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UnitAssignmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->whereIn(
                'organization_unit_id',
                app(OrganizationScope::class)->visibleUnitIds(auth()->user()),
            ))
            ->columns([
                TextColumn::make('user.name')->label('Operator')->searchable(),
                TextColumn::make('user.email')->label('Email')->searchable(),
                TextColumn::make('organizationUnit.name')->label('Unit'),
                TextColumn::make('role')->label('Role')->badge(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ]);
    }
}
