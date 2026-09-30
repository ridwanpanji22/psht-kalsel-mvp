<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class UserFirebaseAuthModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_user_can_be_created_with_firebase_identity_and_inactive_defaults(): void
    {
        $user = User::create([
            'name' => null,
            'email' => 'operator@example.test',
            'password' => 'password',
            'firebase_uid' => 'firebase-uid-123',
            'is_active' => false,
        ]);

        $this->assertNull($user->name);
        $this->assertSame('firebase-uid-123', $user->firebase_uid);
        $this->assertNull($user->onboarded_at);
        $this->assertFalse($user->is_active);
    }

    public function test_is_onboarded_returns_false_when_onboarded_at_is_null(): void
    {
        $user = User::factory()->create([
            'name' => 'Operator Name',
            'onboarded_at' => null,
        ]);

        $this->assertFalse($user->isOnboarded());
    }

    public function test_is_onboarded_returns_false_when_name_is_empty(): void
    {
        $user = User::factory()->create([
            'name' => '',
            'onboarded_at' => now(),
        ]);

        $this->assertFalse($user->isOnboarded());
    }

    public function test_is_onboarded_returns_true_when_onboarded_at_set_and_name_present(): void
    {
        $user = User::factory()->create([
            'name' => 'Operator Name',
            'onboarded_at' => now(),
        ]);

        $this->assertTrue($user->isOnboarded());
    }
}
