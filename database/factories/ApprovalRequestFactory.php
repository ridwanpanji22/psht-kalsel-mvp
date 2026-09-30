<?php

namespace Database\Factories;

use App\Enums\ApprovalStatus;
use App\Models\ApprovalRequest;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalRequest>
 */
class ApprovalRequestFactory extends Factory
{
    protected $model = ApprovalRequest::class;

    public function definition(): array
    {
        return [
            'membership_id' => Membership::factory(),
            'requested_by_id' => User::factory(),
            'reviewed_by_id' => null,
            'type' => 'warga_conversion',
            'status' => ApprovalStatus::Pending,
            'payload' => ['member_number' => 'PSHT-TEST'],
            'review_note' => null,
            'reviewed_at' => null,
        ];
    }
}
