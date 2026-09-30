<?php

namespace App\Filament\Resources\UnitAssignments\Pages;

use App\Filament\Resources\UnitAssignments\UnitAssignmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUnitAssignments extends ListRecords
{
    protected static string $resource = UnitAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
