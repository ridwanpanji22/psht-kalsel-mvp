<?php

namespace App\Filament\Resources\UnitAssignments\Schemas;

use App\Models\OrganizationUnit;
use App\Models\User;
use App\Support\OrganizationScope;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class UnitAssignmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')
                ->label('Operator')
                ->options(fn (): array => User::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->required(),
            Select::make('organization_unit_id')
                ->label('Unit')
                ->options(fn (): array => OrganizationUnit::query()
                    ->whereIn('id', app(OrganizationScope::class)->visibleUnitIds(auth()->user()))
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->required(),
            Select::make('role')
                ->label('Role')
                ->options(['operator' => 'Operator'])
                ->default('operator')
                ->required()
                ->disabled(),
        ]);
    }
}
