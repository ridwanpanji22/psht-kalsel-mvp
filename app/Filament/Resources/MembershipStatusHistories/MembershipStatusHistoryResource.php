<?php

namespace App\Filament\Resources\MembershipStatusHistories;

use App\Filament\Resources\MembershipStatusHistories\Pages\ListMembershipStatusHistories;
use App\Filament\Resources\MembershipStatusHistories\Schemas\MembershipStatusHistoryForm;
use App\Filament\Resources\MembershipStatusHistories\Tables\MembershipStatusHistoriesTable;
use App\Models\MembershipStatusHistory;
use App\Support\OrganizationScope;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MembershipStatusHistoryResource extends Resource
{
    protected static ?string $model = MembershipStatusHistory::class;

    protected static ?string $modelLabel = 'riwayat status anggota';

    protected static ?string $pluralModelLabel = 'riwayat status anggota';

    protected static ?string $navigationLabel = 'Riwayat Status';

    protected static string|\UnitEnum|null $navigationGroup = 'Keanggotaan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return MembershipStatusHistoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MembershipStatusHistoriesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('membership', fn ($query) => $query->whereIn(
            'organization_unit_id',
            app(OrganizationScope::class)->visibleUnitIds(auth()->user()),
        ));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMembershipStatusHistories::route('/'),
        ];
    }
}
