<?php

namespace App\Actions;

use App\Models\Media;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Removes a media record from application discovery/route access by setting
 * `is_public` to false. The file already copied to the public disk is left in
 * place on purpose: a direct `/storage/...` URL that has already been disclosed
 * cannot be revoked, so this action must never present itself as reclaiming
 * confidentiality or deleting the public asset.
 */
class HapusMediaDariPortal
{
    public function hide(User $actor, Media $media): Media
    {
        if (! $media->is_public) {
            throw ValidationException::withMessages([
                'media' => 'Media belum tayang di portal.',
            ]);
        }

        $media->update(['is_public' => false]);

        return $media->refresh();
    }
}
