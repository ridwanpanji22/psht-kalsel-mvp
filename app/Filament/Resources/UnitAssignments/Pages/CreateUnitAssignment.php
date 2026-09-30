<?php

namespace App\Filament\Resources\UnitAssignments\Pages;

use App\Actions\CreateUnitAssignment as CreateUnitAssignmentAction;
use App\Filament\Resources\UnitAssignments\UnitAssignmentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUnitAssignment extends CreateRecord
{
    protected static string $resource = UnitAssignmentResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateUnitAssignmentAction::class)->handle(auth()->user(), [
            'user_id' => (int) $data['user_id'],
            'organization_unit_id' => (int) $data['organization_unit_id'],
            'role' => 'operator',
        ]);
    }
}
