<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use App\Enums\MembershipType;
use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'person_id',
    'organization_unit_id',
    'type',
    'status',
    'member_number',
    'started_on',
    'archived_at',
])]
class Membership extends Model
{
    /** @use HasFactory<MembershipFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => MembershipStatus::Active,
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(MembershipStatusHistory::class);
    }

    public function approvalRequests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class);
    }

    protected function casts(): array
    {
        return [
            'type' => MembershipType::class,
            'status' => MembershipStatus::class,
            'started_on' => 'date',
            'archived_at' => 'datetime',
        ];
    }
}
