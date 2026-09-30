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

        $verified = $firebase->verifyIdToken($validated['id_token']);

        if (! $verified['email_verified']) {
            return response()->json(['error' => 'Email belum diverifikasi oleh Firebase.'], 403);
        }

        $user = User::query()->where('firebase_uid', $verified['uid'])->first()
            ?? User::query()
                ->where('email', $verified['email'])
                ->whereNull('firebase_uid')
                ->first();

        if (! $user) {
            return response()->json(['error' => 'Undangan aktivasi tidak ditemukan.'], 404);
        }

        if ($user->isOnboarded()) {
            return response()->json(['error' => 'Akun ini sudah pernah diaktivasi sebelumnya.'], 400);
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
