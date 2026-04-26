<?php ob_start(); ?>
<?php
$translateCardKey = static function (string $translationKey): string {
    $value = t($translationKey);
    if ($value === $translationKey) {
        $fallback = t('match_card.unknown');
        return $fallback === 'match_card.unknown' ? '—' : $fallback;
    }

    return $value;
};
$csrf = app()->make(App\Security\Csrf::class);
?>
<h2><?= htmlspecialchars(t('dashboard.title'), ENT_QUOTES, 'UTF-8') ?></h2>
<p><?= htmlspecialchars(t('dashboard.description'), ENT_QUOTES, 'UTF-8') ?></p>
<?php if (!empty($message ?? null)): ?>
    <div class="ok"><?= htmlspecialchars(t((string)$message), ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<section style="border:1px solid #e5e7eb;border-radius:10px;padding:12px;background:#fafafa;margin-bottom:14px;">
    <h3 style="margin:0 0 8px 0;"><?= htmlspecialchars(t('dashboard.notifications_title'), ENT_QUOTES, 'UTF-8') ?></h3>
    <p style="margin-top:0;">
        <?= htmlspecialchars(t('dashboard.notifications_unread_label'), ENT_QUOTES, 'UTF-8') ?>:
        <strong><?= (int)($unreadNotifications ?? 0) ?></strong>
    </p>
    <form method="post" action="/notifications/read-all" style="margin-bottom:8px;">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf->token(), ENT_QUOTES, 'UTF-8') ?>">
        <button class="btn" type="submit"><?= htmlspecialchars(t('dashboard.notifications_mark_all_read'), ENT_QUOTES, 'UTF-8') ?></button>
    </form>

    <?php if (empty($notifications ?? [])): ?>
        <p><?= htmlspecialchars(t('dashboard.notifications_empty'), ENT_QUOTES, 'UTF-8') ?></p>
    <?php else: ?>
        <ul>
            <?php foreach ($notifications as $notification): ?>
                <li style="margin-bottom:8px;">
                    <strong><?= htmlspecialchars(t((string)$notification['title_text_key']), ENT_QUOTES, 'UTF-8') ?></strong>
                    <div><?= htmlspecialchars(t((string)$notification['body_text_key']), ENT_QUOTES, 'UTF-8') ?></div>
                    <small><?= htmlspecialchars((string)$notification['created_at'], ENT_QUOTES, 'UTF-8') ?></small>
                    <?php if ((int)$notification['is_read'] === 0): ?>
                        <form method="post" action="/notifications/read" style="display:inline-block;margin-inline-start:8px;">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf->token(), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="notification_id" value="<?= (int)$notification['id'] ?>">
                            <button class="btn" type="submit"><?= htmlspecialchars(t('dashboard.notifications_mark_read'), ENT_QUOTES, 'UTF-8') ?></button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<ul>
    <li><a href="/onboarding/profile"><?= htmlspecialchars(t('onboarding.profile_title'), ENT_QUOTES, 'UTF-8') ?></a></li>
    <li><a href="/onboarding/boundaries"><?= htmlspecialchars(t('onboarding.boundaries_title'), ENT_QUOTES, 'UTF-8') ?></a></li>
    <li><a href="/onboarding/availability"><?= htmlspecialchars(t('onboarding.availability_title'), ENT_QUOTES, 'UTF-8') ?></a></li>
    <li><a href="/onboarding/goals"><?= htmlspecialchars(t('onboarding.goals_title'), ENT_QUOTES, 'UTF-8') ?></a></li>
</ul>

<h3><?= htmlspecialchars(t('dashboard.match_cards_title'), ENT_QUOTES, 'UTF-8') ?></h3>
<?php if (empty($cards ?? [])): ?>
    <p><?= htmlspecialchars(t('dashboard.match_cards_empty'), ENT_QUOTES, 'UTF-8') ?></p>
    <p style="font-size:13px;color:#555;"><?= htmlspecialchars(t('dashboard.match_cards_processing_hint'), ENT_QUOTES, 'UTF-8') ?></p>
<?php else: ?>
    <ul>
        <?php foreach ($cards as $card): ?>
            <li>
                <strong><?= htmlspecialchars((string)$card['compatibility_score'], ENT_QUOTES, 'UTF-8') ?></strong>
                |
                <?= htmlspecialchars($translateCardKey('match_card.age_label.' . (string)$card['age_range_label_key']), ENT_QUOTES, 'UTF-8') ?>
                |
                <?= htmlspecialchars($translateCardKey('match_card.distance_bucket.' . (string)$card['approx_distance_bucket']), ENT_QUOTES, 'UTF-8') ?>
                |
                <?= htmlspecialchars($translateCardKey('match_card.emotional_summary.' . (string)$card['emotional_summary_key']), ENT_QUOTES, 'UTF-8') ?>

                <?php if (in_array((string)($card['match_status'] ?? ''), ['mutual', 'chat_open'], true)): ?>
                    <?php if (!empty($card['chat_id'])): ?>
                        <a class="btn" href="/chat?chat_id=<?= (int)$card['chat_id'] ?>" style="display:inline-block;text-decoration:none;"><?= htmlspecialchars(t('match.chat.enter'), ENT_QUOTES, 'UTF-8') ?></a>
                    <?php else: ?>
                        <button class="btn" type="button"><?= htmlspecialchars(t('match.chat.open_or_coming_soon'), ENT_QUOTES, 'UTF-8') ?></button>
                    <?php endif; ?>
                <?php else: ?>
                    <form method="post" action="/match-interest" style="display:inline-block;margin-inline-start:10px;">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf->token(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="match_id" value="<?= (int)$card['match_id'] ?>">

                        <?php if (($card['viewer_interest'] ?? 'none') !== 'interested'): ?>
                            <button class="btn" type="submit" name="action" value="interested"><?= htmlspecialchars(t('match.interest.interested'), ENT_QUOTES, 'UTF-8') ?></button>
                        <?php endif; ?>

                        <?php if (($card['viewer_interest'] ?? 'none') !== 'passed'): ?>
                            <button class="btn" type="submit" name="action" value="pass"><?= htmlspecialchars(t('match.interest.pass'), ENT_QUOTES, 'UTF-8') ?></button>
                        <?php endif; ?>

                        <?php if (in_array(($card['viewer_interest'] ?? 'none'), ['interested', 'passed'], true)): ?>
                            <button class="btn" type="submit" name="action" value="undo"><?= htmlspecialchars(t('match.interest.undo'), ENT_QUOTES, 'UTF-8') ?></button>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php $content = ob_get_clean(); require __DIR__ . '/layouts/main.php'; ?>
