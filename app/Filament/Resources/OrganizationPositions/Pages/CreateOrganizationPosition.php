<?php

namespace App\Filament\Resources\OrganizationPositions\Pages;

use App\Actions\CreateOrganizationPosition as CreatePositionAction;
use App\Filament\Resources\OrganizationPositions\OrganizationPositionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateOrganizationPosition extends CreateRecord
{
    protected static string $resource = OrganizationPositionResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(CreatePositionAction::class)->handle([
            'organization_unit_id' => (int) $data['organization_unit_id'],
            'title' => $data['title'],
            'holder_name' => $data['holder_name'],
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'] ?? null,
            'is_public' => (bool) ($data['is_public'] ?? false),
        ]);
    }
}
