<?php

namespace App\Filament\Resources\UnitAssignments;

use App\Filament\Resources\UnitAssignments\Pages\CreateUnitAssignment;
use App\Filament\Resources\UnitAssignments\Pages\ListUnitAssignments;
use App\Filament\Resources\UnitAssignments\Schemas\UnitAssignmentForm;
use App\Filament\Resources\UnitAssignments\Tables\UnitAssignmentsTable;
use App\Models\UnitAssignment;
use App\Support\OrganizationScope;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UnitAssignmentResource extends Resource
{
    protected static ?string $model = UnitAssignment::class;

    protected static ?string $modelLabel = 'penugasan operator';

    protected static ?string $pluralModelLabel = 'penugasan operator';

    protected static ?string $navigationLabel = 'Penugasan Operator';

    protected static string|\UnitEnum|null $navigationGroup = 'Akses & Operator';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return UnitAssignmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UnitAssignmentsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn(
            'organization_unit_id',
            app(OrganizationScope::class)->visibleUnitIds(auth()->user()),
        );
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUnitAssignments::route('/'),
            'create' => CreateUnitAssignment::route('/create'),
        ];
    }
}
