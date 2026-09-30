<?php

namespace App\Filament\Resources\TrainingLocations;

use App\Filament\Resources\TrainingLocations\Pages\CreateTrainingLocation;
use App\Filament\Resources\TrainingLocations\Pages\ListTrainingLocations;
use App\Filament\Resources\TrainingLocations\Schemas\TrainingLocationForm;
use App\Filament\Resources\TrainingLocations\Tables\TrainingLocationsTable;
use App\Models\TrainingLocation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TrainingLocationResource extends Resource
{
    protected static ?string $model = TrainingLocation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return TrainingLocationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TrainingLocationsTable::configure($table);
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
            'index' => ListTrainingLocations::route('/'),
            'create' => CreateTrainingLocation::route('/create'),
        ];
    }
}
