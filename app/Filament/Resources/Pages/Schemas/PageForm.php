<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Models\OrganizationUnit;
use App\Support\OrganizationScope;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('organization_unit_id')
                ->label('Unit pemilik')
                ->options(fn (): array => OrganizationUnit::query()
                    ->whereIn('id', app(OrganizationScope::class)->visibleUnitIds(auth()->user()))
                    ->orderBy('name')->pluck('name', 'id')->all())
                ->nullable()
                ->searchable(),
            Select::make('kind')->label('Jenis halaman')->options([
                'general_history' => 'Sejarah umum',
                'branch_history' => 'Sejarah Cabang',
                'organization_profile' => 'Profil organisasi',
            ])->required(),
            TextInput::make('slug')->label('Slug')->required()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('title')->label('Judul')->required()->maxLength(255),
            RichEditor::make('body')->label('Isi')->required()->columnSpanFull(),
        ]);
    }
}
