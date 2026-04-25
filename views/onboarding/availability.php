<?php ob_start(); $csrf = app()->make(App\Security\Csrf::class); ?>
<h2><?= htmlspecialchars(t('onboarding.availability_title'), ENT_QUOTES, 'UTF-8') ?></h2>
<p><?= htmlspecialchars(t('onboarding.availability_helper'), ENT_QUOTES, 'UTF-8') ?></p>
<form method="post" action="/onboarding/availability">
<input type="hidden" name="_token" value="<?= htmlspecialchars($csrf->token(), ENT_QUOTES, 'UTF-8') ?>">
<?php if ($e = fieldError($errors ?? [], 'availability')): ?><div class="err" role="alert"><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($e = fieldError($errors ?? [], 'day_keys')): ?><div class="err" role="alert"><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($e = fieldError($errors ?? [], 'time_block_keys')): ?><div class="err" role="alert"><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php $selectedDays = (array)($selectedDayKeys ?? []); $selectedBlocks = (array)($selectedTimeBlockKeys ?? []); ?>

<fieldset style="margin-top:12px;border:1px solid #ddd;padding:12px;border-radius:8px;">
    <legend><?= htmlspecialchars(t('onboarding.availability_days_label'), ENT_QUOTES, 'UTF-8') ?></legend>
    <div class="row">
        <?php foreach (($availabilityDays ?? []) as $day): ?>
            <label style="display:flex;gap:8px;align-items:center;">
                <input type="checkbox" name="day_keys[]" value="<?= htmlspecialchars((string)$day['id'], ENT_QUOTES, 'UTF-8') ?>" <?= in_array((string)$day['id'], $selectedDays, true) ? 'checked' : '' ?>>
                <span><?= htmlspecialchars(t((string)$day['label_key']), ENT_QUOTES, 'UTF-8') ?></span>
            </label>
        <?php endforeach; ?>
    </div>
</fieldset>

<fieldset style="margin-top:12px;border:1px solid #ddd;padding:12px;border-radius:8px;">
    <legend><?= htmlspecialchars(t('onboarding.availability_blocks_label'), ENT_QUOTES, 'UTF-8') ?></legend>
    <?php foreach (($availabilityBlocks ?? []) as $block): ?>
        <label style="display:flex;gap:8px;align-items:center;">
            <input type="checkbox" name="time_block_keys[]" value="<?= htmlspecialchars((string)$block['id'], ENT_QUOTES, 'UTF-8') ?>" <?= in_array((string)$block['id'], $selectedBlocks, true) ? 'checked' : '' ?>>
            <span><?= htmlspecialchars(t((string)$block['label_key']), ENT_QUOTES, 'UTF-8') ?></span>
        </label>
    <?php endforeach; ?>
</fieldset>

<button class="btn" type="submit"><?= htmlspecialchars(t('common.save_continue'), ENT_QUOTES, 'UTF-8') ?></button>
</form>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
