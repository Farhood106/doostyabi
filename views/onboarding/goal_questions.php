<?php ob_start(); $csrf = app()->make(App\Security\Csrf::class); ?>
<h2><?= htmlspecialchars(t('onboarding.goal_specific_questions_title'), ENT_QUOTES, 'UTF-8') ?></h2>
<p style="font-size:13px;color:#555;"><?= htmlspecialchars(t('onboarding.goal_specific_questions_helper'), ENT_QUOTES, 'UTF-8') ?></p>
<?php if (!empty($primaryGoalId) && !empty($goalTitleById[$primaryGoalId])): ?>
    <p style="font-size:13px;color:#333;"><strong><?= htmlspecialchars(t('onboarding.goal_primary_active_label'), ENT_QUOTES, 'UTF-8') ?>:</strong> <?= htmlspecialchars(t((string)$goalTitleById[$primaryGoalId]), ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<form method="post" action="/onboarding/goal-questions">
<input type="hidden" name="_token" value="<?= htmlspecialchars($csrf->token(), ENT_QUOTES, 'UTF-8') ?>">
<?php $goalPrefDefs = (array)($goalPreferenceDefinitions ?? []); $goalPrefValues = (array)($goalPreferenceValues ?? []); ?>
<?php if ($goalPrefDefs === []): ?>
    <p><?= htmlspecialchars(t('onboarding.goal_questions_empty'), ENT_QUOTES, 'UTF-8') ?></p>
<?php else: ?>
    <?php foreach ($goalPrefDefs as $def): ?>
        <?php
            $goalId = (int)$def['goal_id'];
            $prefKey = (string)$def['pref_key'];
            $allowed = (array)($def['allowed_values'] ?? []);
            $inputType = (string)($def['input_type'] ?? 'select');
            $savedValue = $goalPrefValues[$goalId][$prefKey] ?? '';
            $savedArray = is_array($savedValue) ? array_map('strval', $savedValue) : [];
            $savedScalar = is_array($savedValue) ? '' : (string)$savedValue;
        ?>
        <fieldset style="margin-top:12px;border:1px solid #ddd;padding:12px;border-radius:8px;">
            <legend>
                <?= htmlspecialchars(t(($goalTitleById[$goalId] ?? 'onboarding.goals_title')), ENT_QUOTES, 'UTF-8') ?>
                -
                <?= htmlspecialchars(t((string)$def['label_key']), ENT_QUOTES, 'UTF-8') ?>
            </legend>
            <?php if (!empty($def['helper_text_key'])): ?>
                <div style="font-size:13px;color:#555;margin-bottom:6px;"><?= htmlspecialchars(t((string)$def['helper_text_key']), ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if ($e = fieldError($errors ?? [], "goal_pref.{$goalId}.{$prefKey}")): ?>
                <div class="err" role="alert"><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if ($inputType === 'multiselect'): ?>
                <?php foreach ($allowed as $value): $value = (string)$value; ?>
                    <label style="display:block;padding:6px 0;">
                        <input
                            type="checkbox"
                            name="goal_pref[<?= $goalId ?>][<?= htmlspecialchars($prefKey, ENT_QUOTES, 'UTF-8') ?>][]"
                            value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"
                            <?= in_array($value, $savedArray, true) ? 'checked' : '' ?>
                        >
                        <?= htmlspecialchars(t('onboarding.goal_pref.option.' . $prefKey . '.' . $value), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                <?php endforeach; ?>
            <?php elseif ($inputType === 'text'): ?>
                <textarea name="goal_pref[<?= $goalId ?>][<?= htmlspecialchars($prefKey, ENT_QUOTES, 'UTF-8') ?>]" rows="3" style="width:100%;max-width:540px;"><?= htmlspecialchars($savedScalar, ENT_QUOTES, 'UTF-8') ?></textarea>
            <?php else: ?>
                <?php foreach ($allowed as $value): $value = (string)$value; ?>
                    <label style="display:block;padding:6px 0;">
                        <input
                            type="radio"
                            name="goal_pref[<?= $goalId ?>][<?= htmlspecialchars($prefKey, ENT_QUOTES, 'UTF-8') ?>]"
                            value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"
                            <?= ($savedScalar === $value) ? 'checked' : '' ?>
                        >
                        <?= htmlspecialchars(t('onboarding.goal_pref.option.' . $prefKey . '.' . $value), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                <?php endforeach; ?>
            <?php endif; ?>
        </fieldset>
    <?php endforeach; ?>
<?php endif; ?>
<button class="btn" type="submit"><?= htmlspecialchars(t('common.finish'), ENT_QUOTES, 'UTF-8') ?></button>
</form>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
