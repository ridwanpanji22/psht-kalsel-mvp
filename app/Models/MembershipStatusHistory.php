<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['membership_id', 'actor_id', 'from_status', 'to_status', 'reason'])]
class MembershipStatusHistory extends Model
{
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected function casts(): array
    {
        return [
            'from_status' => MembershipStatus::class,
            'to_status' => MembershipStatus::class,
        ];
    }
}
