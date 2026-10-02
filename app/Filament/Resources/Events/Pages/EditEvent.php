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
use Illuminate\Support\Facades\Storage;
use Throwable;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Event $record */
        $coverPath = $this->normalizeCoverPath($data['cover_path'] ?? null);
        $altText = $data['cover_alt_text'] ?? null;
        unset($data['cover_path'], $data['cover_alt_text']);

        $previousCoverPath = $record->cover_path;

        // Any staged cover file that is not the record's current cover must be
        // removed if the initial (non-cover) save fails, so no orphan is left.
        $stagedCover = $coverPath !== null && $coverPath !== $previousCoverPath ? $coverPath : null;

        try {
            $record->fill($data)->save();
        } catch (Throwable $exception) {
            if ($stagedCover !== null) {
                Storage::disk('local')->delete($stagedCover);
            }

            throw $exception;
        }

        return app(AturSampulEvent::class)->apply($record, $coverPath, $altText);
    }

    private function normalizeCoverPath(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        return is_string($value) && filled($value) ? $value : null;
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
