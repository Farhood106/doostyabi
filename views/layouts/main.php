<?php
/** @var string $content */
$csrf = app()->make(App\Security\Csrf::class);
?>
<!doctype html>
<html lang="<?= htmlspecialchars(currentLocale(), ENT_QUOTES, 'UTF-8') ?>" dir="<?= htmlspecialchars(currentDirection(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(t('app.name'), ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        body{font-family:tahoma,sans-serif;background:#f8f9fb;margin:0;padding:0}
        .container{max-width:860px;margin:30px auto;background:#fff;padding:24px;border-radius:10px}
        label{display:block;margin-top:10px}
        input,select,textarea{width:100%;padding:8px;margin-top:4px}
        .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .btn{padding:10px 14px;background:#4636d9;color:#fff;border:none;border-radius:6px;cursor:pointer;margin-top:12px}
        .err{color:#c00;font-size:13px;margin-top:2px}
        .ok{color:#0a6e2c;background:#ebf9ef;padding:8px;border-radius:6px;margin:10px 0}
        .top{display:flex;justify-content:space-between;align-items:center}
    </style>
</head>
<body>
<div class="container">
    <div class="top">
        <h1><?= htmlspecialchars(t('app.name'), ENT_QUOTES, 'UTF-8') ?></h1>
        <?php if (app()->make(App\Auth\AuthService::class)->userId()): ?>
            <form method="post" action="/logout">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf->token(), ENT_QUOTES, 'UTF-8') ?>">
                <button class="btn" type="submit"><?= htmlspecialchars(t('auth.logout'), ENT_QUOTES, 'UTF-8') ?></button>
            </form>
        <?php endif; ?>
    </div>

    <?php $flashMessage = flashGet('message'); if ($flashMessage): ?>
        <div class="ok"><?= htmlspecialchars(t((string)$flashMessage), ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?= $content ?>
</div>
</body>
</html>
