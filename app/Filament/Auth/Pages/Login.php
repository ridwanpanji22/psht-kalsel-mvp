<?php

namespace App\Filament\Auth\Pages;

use Filament\Auth\Pages\Login as BaseLogin;

/**
 * Operator login page driven by Firebase Authentication.
 *
 * The stock Filament form is replaced with a standalone view that runs
 * Firebase email/password and Google sign-in in the browser, then exchanges
 * the resulting ID token for a Laravel session through the auth API.
 */
class Login extends BaseLogin
{
    protected string $view = 'auth.login';
}
