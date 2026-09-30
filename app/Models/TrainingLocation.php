<?php

namespace App\Models;

use Database\Factories\TrainingLocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'organization_unit_id',
    'slug',
    'name',
    'address',
    'contact',
    'schedule',
    'latitude',
    'longitude',
    'status',
    'verified_at',
    'submitted_by_id',
    'published_by_id',
    'published_at',
])]
class TrainingLocation extends Model
{
    /** @use HasFactory<TrainingLocationFactory> */
    use HasFactory;

    use HasPublicationWorkflow;

    protected function casts(): array
    {
        return [
            ...$this->publicationCasts(),
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'verified_at' => 'datetime',
        ];
    }
}
