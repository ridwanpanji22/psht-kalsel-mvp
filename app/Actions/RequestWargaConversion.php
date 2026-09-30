<?php

namespace App\Actions;

use App\Enums\ApprovalStatus;
use App\Enums\MembershipType;
use App\Models\ApprovalRequest;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class RequestWargaConversion
{
    public function handle(User $actor, Membership $membership, string $memberNumber): ApprovalRequest
    {
        if ($membership->type !== MembershipType::Siswa) {
            throw ValidationException::withMessages([
                'membership' => 'Hanya siswa yang dapat diajukan menjadi warga.',
            ]);
        }

        $memberNumber = trim($memberNumber);

        if ($memberNumber === '' || mb_strlen($memberNumber) > 100) {
            throw ValidationException::withMessages([
                'member_number' => 'Nomor warga wajib diisi dan maksimal 100 karakter.',
            ]);
        }

        if (Membership::query()->where('member_number', $memberNumber)->exists()) {
            throw ValidationException::withMessages([
                'member_number' => 'Nomor warga sudah digunakan.',
            ]);
        }

        return ApprovalRequest::query()->create([
            'membership_id' => $membership->id,
            'requested_by_id' => $actor->id,
            'type' => 'warga_conversion',
            'status' => ApprovalStatus::Pending,
            'payload' => ['member_number' => $memberNumber],
        ]);
    }
}
