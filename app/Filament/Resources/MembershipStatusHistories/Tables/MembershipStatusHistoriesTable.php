<?php

namespace App\Filament\Resources\MembershipStatusHistories\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Table;

class MembershipStatusHistoriesTable
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
