<?php

namespace App\Actions;

use App\Enums\ApprovalStatus;
use App\Enums\MembershipType;
use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveWargaConversion
{
    public function handle(User $reviewer, ApprovalRequest $request): Membership
    {
        if ($request->type !== 'warga_conversion' || $request->status !== ApprovalStatus::Pending) {
            throw ValidationException::withMessages([
                'approval' => 'Permohonan tidak dapat disetujui.',
            ]);
        }

        return DB::transaction(function () use ($reviewer, $request): Membership {
            $request = ApprovalRequest::query()->lockForUpdate()->findOrFail($request->id);
            $membership = Membership::query()->lockForUpdate()->findOrFail($request->membership_id);
            $memberNumber = (string) $request->payload['member_number'];

            if (Membership::query()->where('member_number', $memberNumber)->whereKeyNot($membership->id)->exists()) {
                throw ValidationException::withMessages([
                    'member_number' => 'Nomor warga sudah digunakan.',
                ]);
            }

            $membership->update([
                'type' => MembershipType::Warga,
                'member_number' => $memberNumber,
            ]);
            $membership->statusHistories()->create([
                'actor_id' => $reviewer->id,
                'from_status' => $membership->status,
                'to_status' => $membership->status,
                'reason' => 'warga_conversion_approved',
            ]);
            $request->update([
                'status' => ApprovalStatus::Approved,
                'reviewed_by_id' => $reviewer->id,
                'reviewed_at' => now(),
            ]);
            AuditEvent::query()->create([
                'actor_id' => $reviewer->id,
                'subject_type' => $membership->getMorphClass(),
                'subject_id' => $membership->id,
                'action' => 'membership.converted_to_warga',
                'metadata' => ['approval_request_id' => $request->id],
            ]);

            return $membership->refresh();
        });
    }
}
