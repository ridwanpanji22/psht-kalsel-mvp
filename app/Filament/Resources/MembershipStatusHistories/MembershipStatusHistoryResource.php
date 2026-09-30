<?php

namespace App\Filament\Resources\MembershipStatusHistories;

use App\Filament\Resources\MembershipStatusHistories\Pages\CreateMembershipStatusHistory;
use App\Filament\Resources\MembershipStatusHistories\Pages\ListMembershipStatusHistories;
use App\Filament\Resources\MembershipStatusHistories\Schemas\MembershipStatusHistoryForm;
use App\Filament\Resources\MembershipStatusHistories\Tables\MembershipStatusHistoriesTable;
use App\Models\MembershipStatusHistory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MembershipStatusHistoryResource extends Resource
{
    protected static ?string $model = MembershipStatusHistory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return MembershipStatusHistoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MembershipStatusHistoriesTable::configure($table);
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
            'index' => ListMembershipStatusHistories::route('/'),
            'create' => CreateMembershipStatusHistory::route('/create'),
        ];
    }
}
