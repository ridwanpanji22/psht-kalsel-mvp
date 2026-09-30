<?php

namespace App\Filament\Resources\MembershipStatusHistories\Pages;

use App\Filament\Resources\MembershipStatusHistories\MembershipStatusHistoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMembershipStatusHistories extends ListRecords
{
    protected static string $resource = MembershipStatusHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
