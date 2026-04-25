<?php ob_start(); $csrf = app()->make(App\Security\Csrf::class); ?>
<h2><?= htmlspecialchars(t('onboarding.boundaries_title'), ENT_QUOTES, 'UTF-8') ?></h2>
<p><?= htmlspecialchars(t('onboarding.boundaries_helper'), ENT_QUOTES, 'UTF-8') ?></p>
<form method="post" action="/onboarding/boundaries">
<input type="hidden" name="_token" value="<?= htmlspecialchars($csrf->token(), ENT_QUOTES, 'UTF-8') ?>">
<?php if ($e = fieldError($errors ?? [], 'boundary_ids')): ?><div class="err"><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($e = fieldError($errors ?? [], 'importance')): ?><div class="err"><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php $selected = (array)old('boundary_ids', []); $importance = (array)old('importance', []); ?>

<?php foreach (($boundaryGroups ?? []) as $group): ?>
<fieldset style="margin-top:12px;border:1px solid #ddd;padding:12px;border-radius:8px;">
    <legend><?= htmlspecialchars(t((string)$group['title_key']), ENT_QUOTES, 'UTF-8') ?></legend>
    <?php foreach (($group['items'] ?? []) as $item): ?>
    <div class="row" style="align-items:center;">
        <label style="display:flex;gap:8px;align-items:center;">
            <input type="checkbox" name="boundary_ids[]" value="<?= htmlspecialchars((string)$item['id'], ENT_QUOTES, 'UTF-8') ?>" <?= in_array((string)$item['id'], $selected, true) ? 'checked' : '' ?>>
            <span><?= htmlspecialchars(t((string)$item['label_key']), ENT_QUOTES, 'UTF-8') ?></span>
        </label>
        <label>
            <?= htmlspecialchars(t('onboarding.boundary_importance'), ENT_QUOTES, 'UTF-8') ?>
            <select name="importance[<?= htmlspecialchars((string)$item['id'], ENT_QUOTES, 'UTF-8') ?>]">
                <option value="preferred" <?= (($importance[(string)$item['id']] ?? 'preferred') === 'preferred') ? 'selected' : '' ?>><?= htmlspecialchars(t('onboarding.importance.preferred_friendly'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="required" <?= (($importance[(string)$item['id']] ?? '') === 'required') ? 'selected' : '' ?>><?= htmlspecialchars(t('onboarding.importance.required_friendly'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="avoid" <?= (($importance[(string)$item['id']] ?? '') === 'avoid') ? 'selected' : '' ?>><?= htmlspecialchars(t('onboarding.importance.avoid_friendly'), ENT_QUOTES, 'UTF-8') ?></option>
            </select>
        </label>
    </div>
    <?php endforeach; ?>
</fieldset>
<?php endforeach; ?>

<button class="btn" type="submit"><?= htmlspecialchars(t('common.save_continue'), ENT_QUOTES, 'UTF-8') ?></button>
</form>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
