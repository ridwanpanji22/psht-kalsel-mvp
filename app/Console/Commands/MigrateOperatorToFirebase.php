<?php

namespace App\Console\Commands;

use App\Contracts\FirebaseAuthentication;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class MigrateOperatorToFirebase extends Command
{
    protected $signature = 'operators:migrate-firebase {email : Alamat email Operator lama yang akan dimigrasikan}';

    protected $description = 'Mengirim ulang undangan Firebase kepada Operator lama yang belum memiliki identitas Firebase';

    public function handle(FirebaseAuthentication $firebase): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error('Pengguna dengan email tersebut tidak ditemukan.');

            return self::FAILURE;
        }

        if ($user->firebase_uid !== null) {
            $this->error('Pengguna ini sudah memiliki identitas Firebase.');

            return self::FAILURE;
        }

        if (! $user->unitAssignments()->exists()) {
            $this->error('Pengguna ini tidak memiliki assignment unit yang dapat dimigrasikan.');

            return self::FAILURE;
        }

        try {
            $firebaseResult = $firebase->createUserWithInviteLink($user->email);

            DB::transaction(function () use ($user, $firebaseResult): void {
                $user->update([
                    'firebase_uid' => $firebaseResult['uid'],
                    'onboarded_at' => null,
                    'is_active' => false,
                ]);

                $user->unitAssignments()->update(['is_active' => false]);

                AuditEvent::query()->create([
                    'actor_id' => null,
                    'subject_type' => $user->getMorphClass(),
                    'subject_id' => $user->id,
                    'action' => 'operator.firebase_migration_invited',
                    'metadata' => [
                        'email' => $user->email,
                        'firebase_uid' => $firebaseResult['uid'],
                    ],
                ]);
            });
        } catch (Throwable) {
            $this->error('Gagal mengirim undangan migrasi Firebase.');

            return self::FAILURE;
        }

        $this->info("Undangan migrasi Firebase berhasil dikirimkan ke {$email}.");

        return self::SUCCESS;
    }
}
