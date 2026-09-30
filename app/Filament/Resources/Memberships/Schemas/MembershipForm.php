<?php

namespace App\Filament\Resources\Memberships\Schemas;

use App\Enums\MembershipType;
use App\Models\OrganizationUnit;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class MembershipForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('person_name')
                    ->label('Nama lengkap')
                    ->required()
                    ->maxLength(255),
                TextInput::make('birth_place')
                    ->label('Tempat lahir')
                    ->maxLength(255),
                DatePicker::make('birth_date')
                    ->label('Tanggal lahir'),
                Select::make('organization_unit_id')
                    ->label('Unit organisasi')
                    ->options(fn (): array => OrganizationUnit::query()
                        ->whereIn('id', auth()->user()->visibleUnitIds())
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->required()
                    ->searchable(),
                Select::make('type')
                    ->label('Kategori')
                    ->options([
                        MembershipType::Siswa->value => 'Siswa',
                        MembershipType::Warga->value => 'Warga',
                    ])
                    ->required()
                    ->live()
                    ->disabledOn('edit'),
                TextInput::make('member_number')
                    ->label('Nomor warga')
                    ->maxLength(100)
                    ->required(fn (Get $get): bool => $get('type') === MembershipType::Warga->value)
                    ->visible(fn (Get $get): bool => $get('type') === MembershipType::Warga->value)
                    ->disabledOn('edit'),
            ]);
    }
}
