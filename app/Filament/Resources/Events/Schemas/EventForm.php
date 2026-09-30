<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Models\OrganizationUnit;
use App\Support\OrganizationScope;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('organization_unit_id')
                ->label('Unit penyelenggara')
                ->options(fn (): array => OrganizationUnit::query()
                    ->whereIn('id', app(OrganizationScope::class)->visibleUnitIds(auth()->user()))
                    ->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()->required(),
            TextInput::make('title')->label('Nama kegiatan')->required()->maxLength(255),
            TextInput::make('slug')->label('Slug')->required()->maxLength(255)->unique(ignoreRecord: true),
            Textarea::make('description')->label('Keterangan')->rows(5)->columnSpanFull(),
            DateTimePicker::make('starts_at')->label('Mulai')->required(),
            DateTimePicker::make('ends_at')->label('Selesai')->after('starts_at'),
            TextInput::make('venue')->label('Tempat')->maxLength(255),
        ]);
    }
}
