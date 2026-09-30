<?php

namespace App\Actions;

use App\Enums\PublicationStatus;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublishContent
{
    public function submit(User $actor, Model $content): Model
    {
        if ($content->status !== PublicationStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Hanya konten draf yang dapat diajukan.',
            ]);
        }

        return DB::transaction(function () use ($actor, $content): Model {
            $content->update([
                'status' => PublicationStatus::PendingReview,
                'submitted_by_id' => $actor->id,
            ]);
            $this->audit($actor, $content, 'content.submitted');

            return $content->refresh();
        });
    }

    public function publish(User $actor, Model $content): Model
    {
        if ($content->status !== PublicationStatus::PendingReview) {
            throw ValidationException::withMessages([
                'status' => 'Konten harus melalui review sebelum diterbitkan.',
            ]);
        }

        if ($content->submitted_by_id === $actor->id) {
            throw ValidationException::withMessages([
                'reviewer' => 'Pembuat usulan tidak boleh menyetujui publikasinya sendiri.',
            ]);
        }

        return DB::transaction(function () use ($actor, $content): Model {
            $content->update([
                'status' => PublicationStatus::Published,
                'published_by_id' => $actor->id,
                'published_at' => now(),
            ]);
            $this->audit($actor, $content, 'content.published');

            return $content->refresh();
        });
    }

    private function audit(User $actor, Model $content, string $action): void
    {
        AuditEvent::query()->create([
            'actor_id' => $actor->id,
            'subject_type' => $content->getMorphClass(),
            'subject_id' => $content->getKey(),
            'action' => $action,
        ]);
    }
}
