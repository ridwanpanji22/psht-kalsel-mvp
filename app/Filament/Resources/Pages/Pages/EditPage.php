<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Actions\PublishContent;
use App\Enums\PublicationStatus;
use App\Filament\Resources\Pages\PageResource;
use App\Models\Page;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('submitForReview')
                ->label('Ajukan review')
                ->visible(fn (Page $record): bool => $record->status === PublicationStatus::Draft)
                ->action(fn (Page $record): Page => app(PublishContent::class)->submit(auth()->user(), $record)),
            Action::make('publishReviewedContent')
                ->label('Terbitkan setelah review')
                ->visible(fn (Page $record): bool => $record->status === PublicationStatus::PendingReview && $record->submitted_by_id !== auth()->id())
                ->requiresConfirmation()
                ->action(fn (Page $record): Page => app(PublishContent::class)->publish(auth()->user(), $record)),
        ];
    }
}
