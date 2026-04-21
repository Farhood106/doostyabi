<?php ob_start(); $csrf = app()->make(App\Security\Csrf::class); ?>
<h2><?= htmlspecialchars(t('onboarding.availability_title'), ENT_QUOTES, 'UTF-8') ?></h2>
<form method="post" action="/onboarding/availability">
<input type="hidden" name="_token" value="<?= htmlspecialchars($csrf->token(), ENT_QUOTES, 'UTF-8') ?>">
<?php if ($e = fieldError($errors ?? [], 'weekday')): ?><div class="err"><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php for ($i=0; $i<3; $i++): ?>
<div class="row">
<label><?= htmlspecialchars(t('onboarding.weekday'), ENT_QUOTES, 'UTF-8') ?><input name="weekday[]" value="<?= $i ?>"></label>
<label><?= htmlspecialchars(t('onboarding.start_minute'), ENT_QUOTES, 'UTF-8') ?><input name="start_minute[]" value="540"></label>
<label><?= htmlspecialchars(t('onboarding.end_minute'), ENT_QUOTES, 'UTF-8') ?><input name="end_minute[]" value="1020"></label>
</div>
<?php endfor; ?>
<button class="btn" type="submit"><?= htmlspecialchars(t('common.save_continue'), ENT_QUOTES, 'UTF-8') ?></button>
</form>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
