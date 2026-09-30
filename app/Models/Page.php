<?php

namespace App\Models;

use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'organization_unit_id',
    'kind',
    'slug',
    'title',
    'body',
    'status',
    'submitted_by_id',
    'published_by_id',
    'published_at',
])]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    use HasPublicationWorkflow;

    protected function casts(): array
    {
        return $this->publicationCasts();
    }
}
