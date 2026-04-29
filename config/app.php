<?php

declare(strict_types=1);

return [
    'name' => 'Doostyabi',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => (bool)($_ENV['APP_DEBUG'] ?? false),
    'url' => $_ENV['APP_URL'] ?? 'http://localhost',
    'default_locale' => 'fa',
    'fallback_locale' => 'en',
    'default_direction' => 'rtl',
    'session_name' => 'doostyabi_session',
    'admin_user_ids' => $_ENV['APP_ADMIN_USER_IDS'] ?? '1',
];
