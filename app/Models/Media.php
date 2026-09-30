<?php

namespace App\Models;

use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'gallery_id',
    'organization_unit_id',
    'disk',
    'path',
    'mime_type',
    'size',
    'alt_text',
    'is_public',
])]
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class);
    }

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'is_public' => 'boolean',
        ];
    }
}
