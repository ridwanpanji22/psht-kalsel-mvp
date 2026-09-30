<?php

namespace App\Filament\Resources\TrainingLocations\Pages;

use App\Filament\Resources\TrainingLocations\TrainingLocationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTrainingLocations extends ListRecords
{
    protected static string $resource = TrainingLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
