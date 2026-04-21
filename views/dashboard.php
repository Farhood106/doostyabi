<?php ob_start(); ?>
<h2><?= htmlspecialchars(t('dashboard.title'), ENT_QUOTES, 'UTF-8') ?></h2>
<p><?= htmlspecialchars(t('dashboard.description'), ENT_QUOTES, 'UTF-8') ?></p>
<ul>
    <li><a href="/onboarding/profile"><?= htmlspecialchars(t('onboarding.profile_title'), ENT_QUOTES, 'UTF-8') ?></a></li>
    <li><a href="/onboarding/boundaries"><?= htmlspecialchars(t('onboarding.boundaries_title'), ENT_QUOTES, 'UTF-8') ?></a></li>
    <li><a href="/onboarding/availability"><?= htmlspecialchars(t('onboarding.availability_title'), ENT_QUOTES, 'UTF-8') ?></a></li>
    <li><a href="/onboarding/goals"><?= htmlspecialchars(t('onboarding.goals_title'), ENT_QUOTES, 'UTF-8') ?></a></li>
</ul>

<h3><?= htmlspecialchars(t('dashboard.match_cards_title'), ENT_QUOTES, 'UTF-8') ?></h3>
<?php if (empty($cards ?? [])): ?>
    <p><?= htmlspecialchars(t('dashboard.match_cards_empty'), ENT_QUOTES, 'UTF-8') ?></p>
<?php else: ?>
    <ul>
        <?php foreach ($cards as $card): ?>
            <li>
                <strong><?= htmlspecialchars((string)$card['compatibility_score'], ENT_QUOTES, 'UTF-8') ?></strong>
                |
                <?= htmlspecialchars((string)$card['age_range_label_key'], ENT_QUOTES, 'UTF-8') ?>
                |
                <?= htmlspecialchars((string)$card['approx_distance_bucket'], ENT_QUOTES, 'UTF-8') ?>
                |
                <?= htmlspecialchars((string)$card['emotional_summary_key'], ENT_QUOTES, 'UTF-8') ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php $content = ob_get_clean(); require __DIR__ . '/layouts/main.php'; ?>
