<?php

namespace App\Models;

use Database\Factories\OrganizationPositionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_unit_id',
    'title',
    'holder_name',
    'starts_on',
    'ends_on',
    'is_public',
])]
class OrganizationPosition extends Model
{
    /** @use HasFactory<OrganizationPositionFactory> */
    use HasFactory;

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class);
    }

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_public' => 'boolean',
        ];
    }
}
