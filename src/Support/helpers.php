<?php

declare(strict_types=1);

use App\Core\App;

function app(): App
{
    return $GLOBALS['app'];
}

function t(string $key, array $replace = []): string
{
    return app()->make(App\I18n\Translator::class)->get(currentLocale(), $key, $replace);
}

function currentLocale(): string
{
    return $_SESSION['locale'] ?? app()->config('app.default_locale', 'fa');
}

function currentDirection(): string
{
    return $_SESSION['direction'] ?? app()->config('app.default_direction', 'rtl');
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['_old'][$key] ?? $default;
}

function flash(string $key, mixed $value): void
{
    $_SESSION['_flash'][$key] = $value;
}

function flashGet(string $key, mixed $default = null): mixed
{
    if (!isset($_SESSION['_flash'])) {
        return $default;
    }

    $value = $_SESSION['_flash'][$key] ?? $default;
    unset($_SESSION['_flash'][$key]);
    if ($_SESSION['_flash'] === []) {
        unset($_SESSION['_flash']);
    }

    return $value;
}

function fieldError(array $errors, string $field): ?string
{
    $list = $errors[$field] ?? [];
    if ($list === []) {
        return null;
    }

    return t($list[0]);
}
