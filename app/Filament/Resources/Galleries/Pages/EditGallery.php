<?php

namespace App\Filament\Resources\Galleries\Pages;

use App\Actions\PublishContent;
use App\Enums\PublicationStatus;
use App\Filament\Resources\Galleries\GalleryResource;
use App\Models\Gallery;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditGallery extends EditRecord
{
    protected static string $resource = GalleryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('submitForReview')
                ->label('Ajukan review')
                ->visible(fn (Gallery $record): bool => $record->status === PublicationStatus::Draft)
                ->action(fn (Gallery $record): Gallery => app(PublishContent::class)->submit(auth()->user(), $record)),
            Action::make('publishReviewedContent')
                ->label('Terbitkan setelah review')
                ->visible(fn (Gallery $record): bool => $record->status === PublicationStatus::PendingReview && $record->submitted_by_id !== auth()->id())
                ->requiresConfirmation()
                ->action(fn (Gallery $record): Gallery => app(PublishContent::class)->publish(auth()->user(), $record)),
        ];
    }
}
