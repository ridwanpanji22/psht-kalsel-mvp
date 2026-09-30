<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use Database\Factories\ApprovalRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'membership_id',
    'requested_by_id',
    'reviewed_by_id',
    'type',
    'status',
    'payload',
    'review_note',
    'reviewed_at',
])]
class ApprovalRequest extends Model
{
    /** @use HasFactory<ApprovalRequestFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => ApprovalStatus::Pending,
    ];

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    protected function casts(): array
    {
        return [
            'status' => ApprovalStatus::class,
            'payload' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }
}
