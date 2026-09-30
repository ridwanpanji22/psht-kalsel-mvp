<?php

namespace App\Models;

use Database\Factories\GalleryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_unit_id',
    'slug',
    'title',
    'description',
    'status',
    'submitted_by_id',
    'published_by_id',
    'published_at',
])]
class Gallery extends Model
{
    /** @use HasFactory<GalleryFactory> */
    use HasFactory;

    use HasPublicationWorkflow;

    protected function casts(): array
    {
        return $this->publicationCasts();
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }
}
