<?php ob_start(); $csrf = app()->make(App\Security\Csrf::class); ?>
<h2><?= htmlspecialchars(t('onboarding.boundaries_title'), ENT_QUOTES, 'UTF-8') ?></h2>
<form method="post" action="/onboarding/boundaries">
<input type="hidden" name="_token" value="<?= htmlspecialchars($csrf->token(), ENT_QUOTES, 'UTF-8') ?>">
<?php if ($e = fieldError($errors ?? [], 'boundary_key')): ?><div class="err"><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php for ($i = 0; $i < 3; $i++): ?>
<div class="row">
<label><?= htmlspecialchars(t('onboarding.boundary_key'), ENT_QUOTES, 'UTF-8') ?><input name="boundary_key[]"></label>
<label><?= htmlspecialchars(t('onboarding.boundary_value'), ENT_QUOTES, 'UTF-8') ?><input name="boundary_value[]"></label>
<label><?= htmlspecialchars(t('onboarding.boundary_importance'), ENT_QUOTES, 'UTF-8') ?>
<select name="importance[]"><option value="required"><?= htmlspecialchars(t('onboarding.importance.required'), ENT_QUOTES, 'UTF-8') ?></option><option value="preferred"><?= htmlspecialchars(t('onboarding.importance.preferred'), ENT_QUOTES, 'UTF-8') ?></option><option value="avoid"><?= htmlspecialchars(t('onboarding.importance.avoid'), ENT_QUOTES, 'UTF-8') ?></option></select>
</label>
</div>
<?php endfor; ?>
<button class="btn" type="submit"><?= htmlspecialchars(t('common.save_continue'), ENT_QUOTES, 'UTF-8') ?></button>
</form>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
