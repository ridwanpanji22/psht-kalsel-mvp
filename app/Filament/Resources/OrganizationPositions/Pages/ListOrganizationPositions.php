<?php

namespace App\Filament\Resources\OrganizationPositions\Pages;

use App\Filament\Resources\OrganizationPositions\OrganizationPositionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOrganizationPositions extends ListRecords
{
    protected static string $resource = OrganizationPositionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
