<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Trusted ownership record for a demo storage artifact.
 *
 * The demo seeder registers one row per file/media it creates, keyed by the
 * deterministic `namespace` + `disk` + `path`. Reconciliation may remove an
 * artifact (row or file) only when a matching manifest row proves this seeder
 * owns it; the `demo/` namespace prefix alone is never evidence of ownership.
 */
#[Fillable([
    'namespace',
    'disk',
    'path',
    'kind',
    'seed_key',
    'owner_type',
    'owner_id',
])]
class DemoArtifact extends Model
{
    /** Gallery media artifact kind. */
    public const KIND_GALLERY_MEDIA = 'gallery_media';

    /** Event cover artifact kind. */
    public const KIND_EVENT_COVER = 'event_cover';

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }
}
