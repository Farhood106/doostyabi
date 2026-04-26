<?php ob_start(); $csrf = app()->make(App\Security\Csrf::class); ?>
<h2><?= htmlspecialchars(t('onboarding.goals_title'), ENT_QUOTES, 'UTF-8') ?></h2>
<p><?= htmlspecialchars(t('onboarding.goals_helper'), ENT_QUOTES, 'UTF-8') ?></p>
<p><?= htmlspecialchars(t('onboarding.goals_priority_hint'), ENT_QUOTES, 'UTF-8') ?></p>
<p><strong><?= htmlspecialchars(t('onboarding.goal_main_question'), ENT_QUOTES, 'UTF-8') ?></strong></p>
<form method="post" action="/onboarding/goals">
<input type="hidden" name="_token" value="<?= htmlspecialchars($csrf->token(), ENT_QUOTES, 'UTF-8') ?>">
<?php if ($e = fieldError($errors ?? [], 'goal_ids')): ?><div class="err" role="alert"><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php $selectedGoalIds = array_map('intval', (array)($selectedGoalIds ?? [])); ?>
<?php $goalPrefDefs = (array)($goalPreferenceDefinitions ?? []); $goalPrefValues = (array)($goalPreferenceValues ?? []); ?>
<?php foreach (($goalGroups ?? []) as $group): ?>
    <fieldset style="margin-top:12px;border:1px solid #ddd;padding:12px;border-radius:8px;">
        <legend><?= htmlspecialchars(t((string)$group['title_key']), ENT_QUOTES, 'UTF-8') ?></legend>
        <?php foreach (($group['goals'] ?? []) as $goal): ?>
            <label style="display:block;padding:8px;border:1px solid #efefef;border-radius:8px;margin:8px 0;">
                <input type="checkbox" name="goal_ids[]" value="<?= (int)$goal['id'] ?>" <?= in_array((int)$goal['id'], $selectedGoalIds, true) ? 'checked' : '' ?>>
                <strong><?= htmlspecialchars(t((string)$goal['title_key']), ENT_QUOTES, 'UTF-8') ?></strong>
                <div style="font-size:13px;color:#555;"><?= htmlspecialchars(t((string)$goal['description_key']), ENT_QUOTES, 'UTF-8') ?></div>
            </label>
        <?php endforeach; ?>
    </fieldset>
<?php endforeach; ?>

<?php if ($goalPrefDefs !== []): ?>
    <h3><?= htmlspecialchars(t('onboarding.goal_specific_questions_title'), ENT_QUOTES, 'UTF-8') ?></h3>
    <p style="font-size:13px;color:#555;"><?= htmlspecialchars(t('onboarding.goal_specific_questions_helper'), ENT_QUOTES, 'UTF-8') ?></p>
    <?php
    $goalTitleById = [];
    foreach (($goalGroups ?? []) as $group) {
        foreach (($group['goals'] ?? []) as $goal) {
            $goalTitleById[(int)$goal['id']] = (string)$goal['title_key'];
        }
    }
    ?>
    <?php foreach ($goalPrefDefs as $def): ?>
        <?php $goalId = (int)$def['goal_id']; $prefKey = (string)$def['pref_key']; $allowed = (array)($def['allowed_values'] ?? []); ?>
        <fieldset style="margin-top:12px;border:1px solid #ddd;padding:12px;border-radius:8px;">
            <legend>
                <?= htmlspecialchars(t($goalTitleById[$goalId] ?? 'onboarding.goals_title'), ENT_QUOTES, 'UTF-8') ?>
                -
                <?= htmlspecialchars(t((string)$def['label_key']), ENT_QUOTES, 'UTF-8') ?>
            </legend>
            <?php if (!empty($def['helper_text_key'])): ?>
                <div style="font-size:13px;color:#555;margin-bottom:6px;"><?= htmlspecialchars(t((string)$def['helper_text_key']), ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if ($e = fieldError($errors ?? [], "goal_pref.{$goalId}.{$prefKey}")): ?>
                <div class="err" role="alert"><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php foreach ($allowed as $value): ?>
                <?php $value = (string)$value; ?>
                <label style="display:block;padding:6px 0;">
                    <input
                        type="radio"
                        name="goal_pref[<?= $goalId ?>][<?= htmlspecialchars($prefKey, ENT_QUOTES, 'UTF-8') ?>]"
                        value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"
                        <?= (($goalPrefValues[$goalId][$prefKey] ?? '') === $value) ? 'checked' : '' ?>
                    >
                    <?= htmlspecialchars(t('onboarding.goal_pref.option.' . $prefKey . '.' . $value), ENT_QUOTES, 'UTF-8') ?>
                </label>
            <?php endforeach; ?>
        </fieldset>
    <?php endforeach; ?>
<?php endif; ?>
<button class="btn" type="submit"><?= htmlspecialchars(t('common.finish'), ENT_QUOTES, 'UTF-8') ?></button>
</form>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
