<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\DemoAuthSessionController;
use App\Models\OrganizationUnit;
use App\Models\UnitAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DemoAuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_demo_login_is_disabled_by_default(): void
    {
        config()->set('app.demo_auth_enabled', false);

        $response = $this->post('/admin/demo/auth/session', [
            'email' => 'demo.provinsi@psht-kalsel.test',
            'password' => 'PshtKalsel@1922',
        ]);

        $this->assertContains($response->status(), [403, 404], 'Demo auth must be hidden when disabled.');
        $this->assertGuest();
    }

    public function test_login_page_hides_demo_form_when_disabled(): void
    {
        config()->set('app.demo_auth_enabled', false);

        $html = $this->get('/admin/login')->assertOk()->getContent();

        $this->assertStringNotContainsString('data-demo-login', $html, 'Demo form must not render when disabled.');
        $this->assertStringNotContainsString('Demo lokal', $html, 'Demo label must not render when disabled.');
    }

    public function test_demo_login_succeeds_when_enabled_in_local_environment(): void
    {
        config()->set('app.demo_auth_enabled', true);

        $operator = $this->createOperator();

        $response = $this->post('/admin/demo/auth/session', [
            'email' => $operator->email,
            'password' => 'PshtKalsel@1922',
        ]);

        $response->assertRedirect('/admin');
        $this->assertAuthenticatedAs($operator);
    }

    public function test_demo_login_page_shows_form_when_enabled(): void
    {
        config()->set('app.demo_auth_enabled', true);

        $html = $this->get('/admin/login')->assertOk()->getContent();

        $this->assertStringContainsString('data-demo-login', $html, 'Demo form must render when enabled.');
        $this->assertStringContainsString('Demo lokal', $html, 'Demo form must be visibly labelled.');
    }

    public function test_demo_login_rejects_wrong_password(): void
    {
        config()->set('app.demo_auth_enabled', true);

        $operator = $this->createOperator();

        $response = $this->from('/admin/login')->post('/admin/demo/auth/session', [
            'email' => $operator->email,
            'password' => 'not-the-password',
        ]);

        $response->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_demo_login_rejects_inactive_operator(): void
    {
        config()->set('app.demo_auth_enabled', true);

        $operator = $this->createOperator(['is_active' => false]);

        $response = $this->from('/admin/login')->post('/admin/demo/auth/session', [
            'email' => $operator->email,
            'password' => 'PshtKalsel@1922',
        ]);

        $response->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_demo_login_rejects_operator_without_active_assignment(): void
    {
        config()->set('app.demo_auth_enabled', true);

        $operator = $this->createOperator();
        UnitAssignment::query()->where('user_id', $operator->id)->update(['is_active' => false]);

        $response = $this->from('/admin/login')->post('/admin/demo/auth/session', [
            'email' => $operator->email,
            'password' => 'PshtKalsel@1922',
        ]);

        $response->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_demo_login_is_rejected_in_production_environment(): void
    {
        config()->set('app.demo_auth_enabled', true);

        $operator = $this->createOperator();

        app()->detectEnvironment(fn (): string => 'production');

        $request = Request::create('/admin/demo/auth/session', 'POST', [
            'email' => $operator->email,
            'password' => 'PshtKalsel@1922',
        ]);

        $controller = new DemoAuthSessionController;

        try {
            $controller($request);
            $this->fail('Demo auth must be rejected in production.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode(), 'Demo auth must behave as missing in production.');
        }

        $this->assertGuest();
    }

    public function test_demo_login_does_not_reveal_unknown_account(): void
    {
        config()->set('app.demo_auth_enabled', true);

        $response = $this->from('/admin/login')->post('/admin/demo/auth/session', [
            'email' => 'tidak.ada@psht-kalsel.test',
            'password' => 'PshtKalsel@1922',
        ]);

        $response->assertSessionHasErrors();
        $this->assertGuest();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createOperator(array $attributes = []): User
    {
        $unit = OrganizationUnit::factory()->create();

        $operator = User::factory()->onboarded()->create(array_merge([
            'email' => 'demo.operator@psht-kalsel.test',
            'password' => 'PshtKalsel@1922',
            'is_active' => true,
        ], $attributes));

        UnitAssignment::factory()->create([
            'user_id' => $operator->id,
            'organization_unit_id' => $unit->id,
            'is_active' => true,
        ]);

        return $operator;
    }
}
