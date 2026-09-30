<?php

namespace App\Filament\Resources\Memberships\Pages;

use App\Actions\CreateMembership as CreateMembershipAction;
use App\Enums\MembershipType;
use App\Filament\Resources\Memberships\MembershipResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMembership extends CreateRecord
{
    protected static string $resource = MembershipResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateMembershipAction::class)->handle(auth()->user(), [
            'name' => $data['person_name'],
            'birth_place' => $data['birth_place'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'organization_unit_id' => (int) $data['organization_unit_id'],
            'type' => MembershipType::from($data['type']),
            'member_number' => $data['member_number'] ?? null,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
