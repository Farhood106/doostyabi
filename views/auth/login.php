<?php ob_start(); $csrf = app()->make(App\Security\Csrf::class); ?>
<h2><?= htmlspecialchars(t('auth.login_title'), ENT_QUOTES, 'UTF-8') ?></h2>
<form method="post" action="/login">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf->token(), ENT_QUOTES, 'UTF-8') ?>">

    <label><?= htmlspecialchars(t('auth.email'), ENT_QUOTES, 'UTF-8') ?></label>
    <input type="email" name="email" value="<?= htmlspecialchars((string)old('email'), ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($msg = fieldError($errors ?? [], 'email')): ?><div class="err"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <label><?= htmlspecialchars(t('auth.password'), ENT_QUOTES, 'UTF-8') ?></label>
    <input type="password" name="password">
    <?php if ($msg = fieldError($errors ?? [], 'password')): ?><div class="err"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <button class="btn" type="submit"><?= htmlspecialchars(t('auth.login_submit'), ENT_QUOTES, 'UTF-8') ?></button>
</form>
<a href="/register"><?= htmlspecialchars(t('auth.register_title'), ENT_QUOTES, 'UTF-8') ?></a>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
