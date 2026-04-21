<?php ob_start(); $csrf = app()->make(App\Security\Csrf::class); ?>
<h2><?= htmlspecialchars(t('onboarding.goals_title'), ENT_QUOTES, 'UTF-8') ?></h2>
<form method="post" action="/onboarding/goals">
<input type="hidden" name="_token" value="<?= htmlspecialchars($csrf->token(), ENT_QUOTES, 'UTF-8') ?>">
<?php if ($e = fieldError($errors ?? [], 'goal_ids')): ?><div class="err"><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php foreach ($goals as $goal): ?>
    <label>
        <input type="checkbox" name="goal_ids[]" value="<?= (int)$goal['id'] ?>">
        <?= htmlspecialchars(t($goal['title_key']), ENT_QUOTES, 'UTF-8') ?>
    </label>
<?php endforeach; ?>
<button class="btn" type="submit"><?= htmlspecialchars(t('common.finish'), ENT_QUOTES, 'UTF-8') ?></button>
</form>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
