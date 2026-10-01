<?php

namespace App\Http\Controllers\Auth;

use App\Contracts\FirebaseAuthentication;
use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FirebaseAuthActivationController extends Controller
{
    /**
     * Onboard an invited operator and start an authenticated session.
     */
    public function __invoke(Request $request, FirebaseAuthentication $firebase): JsonResponse
    {
        $validated = $request->validate([
            'id_token' => ['required', 'string'],
            'name' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        try {
            $verified = $firebase->verifyIdToken($validated['id_token']);
        } catch (RuntimeException) {
            return response()->json(['error' => 'Token autentikasi tidak valid atau telah kedaluwarsa.'], 401);
        }

        if (! $verified['email_verified']) {
            return response()->json(['error' => 'Email belum diverifikasi oleh Firebase.'], 403);
        }

        $email = strtolower($verified['email']);

        // Resolve the invitation strictly by the verified Firebase UID, but only when the
        // verified token email equals the address recorded on the local account. Otherwise a
        // caller could bind their Firebase identity to an unrelated local user.
        $user = User::query()->where('firebase_uid', $verified['uid'])->first();

        if ($user !== null && ! hash_equals(strtolower($user->email), $email)) {
            return response()->json(['error' => 'Undangan aktivasi tidak ditemukan.'], 403);
        }

        // Initial activation: the invited row has no Firebase UID bound yet, so fall back to
        // the verified email while the UID is still null.
        $user ??= User::query()
            ->where('email', $email)
            ->whereNull('firebase_uid')
            ->first();

        if (! $user) {
            return response()->json(['error' => 'Undangan aktivasi tidak ditemukan.'], 403);
        }

        if ($user->isOnboarded()) {
            return response()->json(['error' => 'Akun ini sudah pernah diaktivasi sebelumnya.'], 403);
        }

        DB::transaction(function () use ($user, $verified, $validated): void {
            $user->update([
                'name' => $validated['name'],
                'firebase_uid' => $verified['uid'],
                'onboarded_at' => now(),
                'is_active' => true,
            ]);

            $user->unitAssignments()->update(['is_active' => true]);

            AuditEvent::query()->create([
                'actor_id' => $user->id,
                'subject_type' => $user->getMorphClass(),
                'subject_id' => $user->id,
                'action' => 'operator.activated',
                'metadata' => [
                    'email' => $user->email,
                    'name' => $validated['name'],
                ],
            ]);
        });

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return response()->json([
            'status' => 'activated',
            'redirect' => url('/admin'),
        ]);
    }
}
