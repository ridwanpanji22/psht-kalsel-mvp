<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\OrganizationUnit;
use App\OrganizationUnitType;
use App\Support\OrganizationScope;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')
                    ->label('Alamat Gmail Operator')
                    ->email()
                    ->required()
                    ->helperText('Undangan aktivasi akan dikirimkan otomatis ke alamat Gmail ini.')
                    ->maxLength(255),
                Select::make('organization_unit_id')
                    ->label('Unit Organisasi Penugasan')
                    ->options(fn (): array => self::unitOptions())
                    ->searchable()
                    ->required(),
            ]);
    }

    /**
     * @return array<int, string>
     */
    protected static function unitOptions(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        $isProvinsiOperator = $user->unitAssignments()
            ->where('is_active', true)
            ->whereHas('organizationUnit', fn (Builder $query) => $query->where('type', OrganizationUnitType::Provinsi))
            ->exists();

        if ($isProvinsiOperator) {
            return OrganizationUnit::query()
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all();
        }

        return OrganizationUnit::query()
            ->whereIn('id', app(OrganizationScope::class)->visibleUnitIds($user))
            ->whereIn('type', [OrganizationUnitType::Ranting, OrganizationUnitType::Rayon])
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
