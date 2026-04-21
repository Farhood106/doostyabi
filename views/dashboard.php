<?php ob_start(); ?>
<h2><?= htmlspecialchars(t('dashboard.title'), ENT_QUOTES, 'UTF-8') ?></h2>
<p><?= htmlspecialchars(t('dashboard.description'), ENT_QUOTES, 'UTF-8') ?></p>
<ul>
    <li><a href="/onboarding/profile"><?= htmlspecialchars(t('onboarding.profile_title'), ENT_QUOTES, 'UTF-8') ?></a></li>
    <li><a href="/onboarding/boundaries"><?= htmlspecialchars(t('onboarding.boundaries_title'), ENT_QUOTES, 'UTF-8') ?></a></li>
    <li><a href="/onboarding/availability"><?= htmlspecialchars(t('onboarding.availability_title'), ENT_QUOTES, 'UTF-8') ?></a></li>
    <li><a href="/onboarding/goals"><?= htmlspecialchars(t('onboarding.goals_title'), ENT_QUOTES, 'UTF-8') ?></a></li>
</ul>
<?php $content = ob_get_clean(); require __DIR__ . '/layouts/main.php'; ?>
