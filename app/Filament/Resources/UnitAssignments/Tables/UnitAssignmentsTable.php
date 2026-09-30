<?php

namespace App\Filament\Resources\UnitAssignments\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Table;

class UnitAssignmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                //
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
