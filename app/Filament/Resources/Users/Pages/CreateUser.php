<?php

namespace App\Filament\Resources\Users\Pages;

use App\Actions\CreateOperator;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateOperator::class)->handle(auth()->user(), [
            'email' => $data['email'],
            'organization_unit_id' => (int) $data['organization_unit_id'],
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
