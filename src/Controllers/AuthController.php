<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Exceptions\UserFriendlyException;
use App\Security\Csrf;
use App\Validation\Validator;
use Throwable;

final class AuthController
{
    public function __construct(private readonly App $app) {}

    public function showRegister(Request $request): void
    {
        View::render('auth/register', [
            'errors' => flashGet('errors', []),
            'message' => flashGet('message'),
        ]);
    }

    public function register(Request $request): never
    {
        $csrf = $this->app->make(Csrf::class);
        if (!$csrf->validate((string)$request->input('_token'))) {
            flash('message', 'security.invalid_csrf');
            Response::redirect('/register');
        }

        $data = [
            'email' => trim((string)$request->input('email')),
            'password' => (string)$request->input('password'),
            'locale' => (string)$request->input('locale', 'fa'),
        ];

        $_SESSION['_old'] = $data;
        $validator = new Validator();
        $ok = $validator->validate($data, [
            'email' => 'required|email|max:190',
            'password' => 'required|min:8|max:120',
            'locale' => 'required|max:10',
        ]);

        if (!$ok) {
            flash('errors', $validator->errors());
            Response::redirect('/register');
        }

        $auth = $this->app->make(AuthService::class);

        try {
            $userId = $auth->register($data['email'], $data['password'], $data['locale']);
            $auth->login(['id' => $userId, 'preferred_locale' => $data['locale'], 'ui_direction' => $data['locale'] === 'fa' ? 'rtl' : 'ltr']);
        } catch (UserFriendlyException $e) {
            flash('errors', ['email' => [$e->translationKey]]);
            Response::redirect('/register');
        } catch (Throwable $e) {
            $this->logException($e, 'auth.register');
            flash('message', 'common.unexpected_error');
            Response::redirect('/register');
        }

        unset($_SESSION['_old']);
        Response::redirect('/onboarding/profile');
    }

    public function showLogin(Request $request): void
    {
        View::render('auth/login', [
            'errors' => flashGet('errors', []),
            'message' => flashGet('message'),
        ]);
    }

    public function login(Request $request): never
    {
        $csrf = $this->app->make(Csrf::class);
        if (!$csrf->validate((string)$request->input('_token'))) {
            flash('message', 'security.invalid_csrf');
            Response::redirect('/login');
        }

        $data = [
            'email' => trim((string)$request->input('email')),
            'password' => (string)$request->input('password'),
        ];

        $_SESSION['_old'] = $data;
        $validator = new Validator();
        if (!$validator->validate($data, ['email' => 'required|email', 'password' => 'required'])) {
            flash('errors', $validator->errors());
            Response::redirect('/login');
        }

        try {
            $auth = $this->app->make(AuthService::class);
            $user = $auth->attempt($data['email'], $data['password']);
            if (!$user) {
                flash('errors', ['email' => ['auth.invalid_credentials']]);
                Response::redirect('/login');
            }

            $auth->login($user);
        } catch (Throwable $e) {
            $this->logException($e, 'auth.login');
            flash('message', 'common.unexpected_error');
            Response::redirect('/login');
        }

        unset($_SESSION['_old']);
        Response::redirect('/dashboard');
    }

    public function logout(Request $request): never
    {
        $csrf = $this->app->make(Csrf::class);
        if (!$csrf->validate((string)$request->input('_token'))) {
            flash('message', 'security.invalid_csrf');
            Response::redirect('/dashboard');
        }

        try {
            $this->app->make(AuthService::class)->logout();
        } catch (Throwable $e) {
            $this->logException($e, 'auth.logout');
            flash('message', 'common.unexpected_error');
            Response::redirect('/dashboard');
        }

        Response::redirect('/login');
    }

    private function logException(Throwable $e, string $context): void
    {
        error_log(sprintf('[%s] %s: %s in %s:%d', $context, $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));
    }
}
