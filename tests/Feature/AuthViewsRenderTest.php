<?php

namespace Tests\Feature;

use Tests\TestCase;

class AuthViewsRenderTest extends TestCase
{
    public function test_login_page_renders_successfully(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    public function test_activate_page_renders_onboarding_form(): void
    {
        $this->get('/admin/activate')
            ->assertOk()
            ->assertSee('Aktivasi Akun Operator');
    }
}
