<?php

namespace App\Filament\Resources\Galleries\RelationManagers;

use App\Actions\HapusMediaDariPortal;
use App\Actions\PublishMediaToPortal;
use App\Enums\PublicationStatus;
use App\Models\Gallery;
use App\Models\Media;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Manages the files belonging to a single Gallery album.
 *
 * Uploads always land on the private `local` disk with `is_public=false`.
 * `Terbitkan media` is the only one-way transition to the public disk and is
 * hidden until the parent Gallery is published. `Hapus dari portal` only
 * clears application visibility; it never deletes the public file. Operators
 * cannot edit `disk` or `is_public` directly.
 */
class GalleryMediaRelationManager extends RelationManager
{
    protected static string $relationship = 'media';

    protected static ?string $title = 'Media album';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Gallery
            && (auth()->user()?->can('update', $ownerRecord) ?? false);
    }

    private function isOwnerPublished(): bool
    {
        /** @var Gallery $gallery */
        $gallery = $this->getOwnerRecord();

        return $gallery->status === PublicationStatus::Published && $gallery->published_at !== null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('file')
                ->label('Berkas gambar')
                ->disk('local')
                ->directory('private/gallery-media')
                ->visibility('private')
                ->image()
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(5120)
                ->preserveFilenames(false)
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (string $operation): bool => $operation === 'create')
                ->helperText('JPEG, PNG, atau WebP maksimal 5 MB. Tersimpan privat sampai Anda menerbitkannya.')
                ->columnSpanFull(),
            TextInput::make('alt_text')
                ->label('Teks alternatif')
                ->maxLength(255)
                ->required(),
            TextInput::make('sort_order')
                ->label('Urutan')
                ->numeric()
                ->integer()
                ->minValue(0)
                ->default(0)
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->where(
                'gallery_id',
                $this->getOwnerRecord()->getKey(),
            ))
            ->columns([
                TextColumn::make('alt_text')->label('Teks alternatif')->searchable(),
                TextColumn::make('sort_order')->label('Urutan')->sortable(),
                TextColumn::make('disk')->label('Penyimpanan')->badge(),
                IconColumn::make('is_public')->label('Tayang')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->headerActions([
                CreateAction::make()
                    ->label('Unggah media')
                    ->using(function (array $data): Media {
                        /** @var Gallery $gallery */
                        $gallery = $this->getOwnerRecord();

                        $disk = Storage::disk('local');

                        return $gallery->media()->create([
                            'organization_unit_id' => $gallery->organization_unit_id,
                            'disk' => 'local',
                            'path' => $data['file'],
                            'mime_type' => rescue(fn (): ?string => $disk->mimeType($data['file']), report: false),
                            'size' => rescue(fn (): ?int => $disk->size($data['file']), report: false),
                            'alt_text' => $data['alt_text'],
                            'is_public' => false,
                            'sort_order' => $data['sort_order'] ?? 0,
                        ]);
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (Media $record): bool => ! $record->is_public),
                Action::make('publishToPortal')
                    ->label('Terbitkan media')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->visible(fn (Media $record): bool => ! $record->is_public && $this->isOwnerPublished())
                    ->schema([
                        TextInput::make('alt_text')
                            ->label('Teks alternatif')
                            ->maxLength(255)
                            ->required()
                            ->default(fn (Media $record): ?string => $record->alt_text),
                    ])
                    ->action(function (Media $record, array $data): void {
                        app(PublishMediaToPortal::class)->publish(auth()->user(), $record, $data['alt_text']);
                    })
                    ->successNotificationTitle('Media diterbitkan ke portal'),
                Action::make('hapusDariPortal')
                    ->label('Hapus dari portal')
                    ->icon('heroicon-o-eye-slash')
                    ->visible(fn (Media $record): bool => $record->is_public)
                    ->requiresConfirmation()
                    ->modalDescription('Media disembunyikan dari portal. Berkas pada disk publik tetap tersimpan dan tidak dihapus.')
                    ->action(function (Media $record): void {
                        app(HapusMediaDariPortal::class)->hide(auth()->user(), $record);
                    })
                    ->successNotificationTitle('Media disembunyikan dari portal'),
            ]);
    }
}
