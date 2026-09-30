<?php

namespace App\Models;

use App\OrganizationUnitType;
use Database\Factories\OrganizationUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'type',
    'parent_id',
    'supervising_branch_id',
    'is_active',
])]
class OrganizationUnit extends Model
{
    /** @use HasFactory<OrganizationUnitFactory> */
    use HasFactory;

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function supervisingBranch(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supervising_branch_id');
    }

    protected function casts(): array
    {
        return [
            'type' => OrganizationUnitType::class,
            'is_active' => 'boolean',
        ];
    }
}
