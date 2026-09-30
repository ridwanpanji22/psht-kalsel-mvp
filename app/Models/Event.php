<?php

namespace App\Models;

use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'organization_unit_id',
    'slug',
    'title',
    'description',
    'starts_at',
    'ends_at',
    'venue',
    'status',
    'submitted_by_id',
    'published_by_id',
    'published_at',
])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    use HasPublicationWorkflow;

    protected function casts(): array
    {
        return [
            ...$this->publicationCasts(),
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
