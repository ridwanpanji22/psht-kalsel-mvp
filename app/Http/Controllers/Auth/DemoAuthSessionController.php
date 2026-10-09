<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Local-only password session endpoint for seeded demo operators.
 *
 * The seeded demo accounts use dummy Firebase UIDs and therefore cannot
 * authenticate against real Firebase. This guarded endpoint lets those accounts
 * sign in during local/test presentations with the shared demo password.
 *
 * Safety:
 * - Only active when `app.demo_auth_enabled` is true AND the environment is
 *   `local` or `testing`. Otherwise it behaves as if the route does not exist.
 * - Firebase token verification is never modified; production login stays
 *   Firebase-only.
 * - Failure responses are uniform so callers cannot probe which accounts exist.
 */
class DemoAuthSessionController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless($this->isEnabled(), 404);

        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            $this->fail();
        }

        /** @var User $user */
        $user = Auth::user();

        // Mirror the Firebase session contract: a valid login requires an active,
        // onboarded operator that holds at least one active unit assignment.
        if (! $user->is_active
            || ! $user->isOnboarded()
            || ! $user->unitAssignments()->where('is_active', true)->exists()) {
            Auth::logout();
            $this->fail();
        }

        $request->session()->regenerate();

        return redirect()->intended('/admin');
    }

    /**
     * Determine whether the demo login path may run in this environment.
     */
    private function isEnabled(): bool
    {
        return (bool) config('app.demo_auth_enabled')
            && app()->environment(['local', 'testing']);
    }

    /**
     * Fail with a uniform, non-enumerating validation error.
     *
     * @return never
     */
    private function fail(): void
    {
        throw ValidationException::withMessages([
            'email' => 'Email atau kata sandi demo tidak cocok, atau akun belum aktif.',
        ]);
    }
}
