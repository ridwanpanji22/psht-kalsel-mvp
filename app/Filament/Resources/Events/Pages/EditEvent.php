<?php

namespace App\Filament\Resources\Events\Pages;

use App\Actions\AturSampulEvent;
use App\Actions\PublishContent;
use App\Enums\PublicationStatus;
use App\Filament\Resources\Events\EventResource;
use App\Models\Event;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Event $record */
        $coverPath = $data['cover_path'] ?? null;
        $altText = $data['cover_alt_text'] ?? null;
        unset($data['cover_path'], $data['cover_alt_text']);

        $record->fill($data)->save();

        return app(AturSampulEvent::class)->apply($record, $coverPath, $altText);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('submitForReview')
                ->label('Ajukan review')
                ->visible(fn (Event $record): bool => $record->status === PublicationStatus::Draft)
                ->action(fn (Event $record): Event => app(PublishContent::class)->submit(auth()->user(), $record)),
            Action::make('publishReviewedContent')
                ->label('Terbitkan setelah review')
                ->visible(fn (Event $record): bool => $record->status === PublicationStatus::PendingReview && $record->submitted_by_id !== auth()->id())
                ->requiresConfirmation()
                ->action(fn (Event $record): Event => app(PublishContent::class)->publish(auth()->user(), $record)),
        ];
    }
}
