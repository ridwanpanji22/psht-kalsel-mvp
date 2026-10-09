<?php

namespace App\Filament\Resources\OrganizationUnits\Schemas;

use App\Models\OrganizationUnit;
use App\OrganizationUnitType;
use App\Support\OrganizationScope;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class OrganizationUnitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nama unit')
                ->required()
                ->maxLength(255),
            Select::make('type')
                ->label('Jenis unit')
                ->options(collect(OrganizationUnitType::cases())->mapWithKeys(
                    fn (OrganizationUnitType $type): array => [$type->value => ucfirst($type->value)],
                )->all())
                ->required()
                ->live()
                ->disabledOn('edit'),
            Select::make('parent_id')
                ->label('Induk formal')
                ->options(function (Get $get): array {
                    $expectedParentType = OrganizationUnitType::tryFrom((string) $get('type'))?->expectedParentType();

                    // A province root has no formal parent at all; every other
                    // type may only select a unit of its contractual parent type.
                    if ($expectedParentType === null) {
                        return [];
                    }

                    return OrganizationUnit::query()
                        ->where('type', $expectedParentType)
                        ->whereIn('id', app(OrganizationScope::class)->visibleUnitIds(auth()->user()))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all();
                })
                ->searchable()
                ->required(fn (Get $get): bool => $get('type') !== OrganizationUnitType::Provinsi->value)
                ->visible(fn (Get $get): bool => $get('type') !== OrganizationUnitType::Provinsi->value),
            Select::make('supervising_branch_id')
                ->label('Cabang pembina')
                ->options(fn (): array => OrganizationUnit::query()
                    ->where('type', OrganizationUnitType::Cabang)
                    ->whereIn('id', app(OrganizationScope::class)->visibleUnitIds(auth()->user()))
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->nullable()
                ->visible(fn (Get $get): bool => in_array($get('type'), [
                    OrganizationUnitType::Ukm->value,
                    OrganizationUnitType::Komisariat->value,
                ], true)),
        ]);
    }
}
