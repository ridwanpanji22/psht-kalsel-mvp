<?php

namespace App\Console\Commands;

use App\Contracts\FirebaseAuthentication;
use App\Models\AuditEvent;
use App\Models\OrganizationUnit;
use App\Models\UnitAssignment;
use App\Models\User;
use App\OrganizationUnitType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class BootstrapProvinceOperator extends Command
{
    protected $signature = 'operators:bootstrap-province {email : Alamat Gmail calon Operator Provinsi}';

    protected $description = 'Membuat dan mengundang Operator Provinsi pertama ke dalam sistem';

    public function handle(FirebaseAuthentication $firebase): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        if (! str_ends_with($email, '@gmail.com')) {
            $this->error('Hanya alamat Gmail (@gmail.com) yang diizinkan.');

            return self::FAILURE;
        }

        $provinsiQuery = OrganizationUnit::query()
            ->where('type', OrganizationUnitType::Provinsi)
            ->where('is_active', true);

        if ((clone $provinsiQuery)->count() !== 1) {
            $this->error('Sistem membutuhkan tepat satu unit organisasi Provinsi yang aktif.');

            return self::FAILURE;
        }

        /** @var OrganizationUnit $provinsi */
        $provinsi = $provinsiQuery->first();

        $hasActiveProvinsiOperator = UnitAssignment::query()
            ->where('organization_unit_id', $provinsi->id)
            ->where('is_active', true)
            ->whereHas('user', fn ($query) => $query->where('is_active', true))
            ->exists();

        if ($hasActiveProvinsiOperator) {
            $this->error('Operator Provinsi aktif sudah tersedia di dalam sistem.');

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->error('Email ini sudah terdaftar di dalam sistem.');

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($email, $provinsi, $firebase): void {
                $user = User::query()->create([
                    'name' => null,
                    'email' => $email,
                    'password' => Str::random(32),
                    'is_active' => false,
                    'onboarded_at' => null,
                ]);

                UnitAssignment::query()->create([
                    'user_id' => $user->id,
                    'organization_unit_id' => $provinsi->id,
                    'role' => 'operator',
                    'is_active' => false,
                ]);

                $firebaseResult = $firebase->createUserWithInviteLink($email);

                $user->update(['firebase_uid' => $firebaseResult['uid']]);

                AuditEvent::query()->create([
                    'actor_id' => null,
                    'subject_type' => $user->getMorphClass(),
                    'subject_id' => $user->id,
                    'action' => 'operator.bootstrapped',
                    'metadata' => [
                        'email' => $email,
                        'organization_unit_id' => $provinsi->id,
                        'firebase_uid' => $firebaseResult['uid'],
                    ],
                ]);
            });
        } catch (Throwable) {
            $this->error('Gagal mengirim undangan Firebase.');

            return self::FAILURE;
        }

        $this->info("Undangan aktivasi berhasil dikirimkan ke {$email}.");

        return self::SUCCESS;
    }
}
