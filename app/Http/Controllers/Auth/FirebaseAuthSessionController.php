<?php

namespace App\Http\Controllers\Auth;

use App\Contracts\FirebaseAuthentication;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FirebaseAuthSessionController extends Controller
{
    /**
     * Exchange a verified Firebase ID token for an authenticated Laravel session.
     */
    public function __invoke(Request $request, FirebaseAuthentication $firebase): JsonResponse
    {
        $validated = $request->validate([
            'id_token' => ['required', 'string'],
        ]);

        $verified = $firebase->verifyIdToken($validated['id_token']);

        // Match only by the verified Firebase UID. Falling back to email would let a
        // caller authenticate as a pending invite that has not been activated yet.
        $user = User::query()
            ->where('firebase_uid', $verified['uid'])
            ->first();

        if (! $user) {
            return response()->json(['error' => 'Akun operator tidak ditemukan atau dinonaktifkan.'], 403);
        }

        // A pending operator is always inactive until activation completes, so the
        // onboarding check must come first to surface the actionable activation hint.
        if (! $user->isOnboarded()) {
            return response()->json(['error' => 'Akun belum menyelesaikan aktivasi onboarding.'], 403);
        }

        if (! $user->is_active) {
            return response()->json(['error' => 'Akun operator tidak ditemukan atau dinonaktifkan.'], 403);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return response()->json([
            'status' => 'authenticated',
            'redirect' => url('/admin'),
        ]);
    }
}
