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
$purposeFromGoalSlug = static function (?string $goalSlug): string {
    return match ((string)$goalSlug) {
        'long_term_relationship', 'emotional_connection' => 'match_card.purpose.emotional',
        'travel_companion', 'event_companion' => 'match_card.purpose.travel',
        'sports_companion', 'social_activity_partner' => 'match_card.purpose.activity',
        'project_collaboration', 'co_living' => 'match_card.purpose.collaboration',
        'casual_connection' => 'match_card.purpose.casual',
        'personal_growth_connection' => 'match_card.purpose.growth',
        default => 'match_card.purpose.general',
    };
};
$starterFromGoalSlug = static function (?string $goalSlug): string {
    return match ((string)$goalSlug) {
        'long_term_relationship', 'emotional_connection' => 'match_card.starter.emotional',
        'travel_companion', 'event_companion', 'social_activity_partner' => 'match_card.starter.travel_activity',
        'sports_companion' => 'match_card.starter.sports',
        'project_collaboration', 'co_living' => 'match_card.starter.collaboration',
        'casual_connection' => 'match_card.starter.casual_respectful',
        default => 'match_card.starter.general',
    };
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
                    <?php if (!empty($notification['action_url']) && !empty($notification['action_label_key'])): ?>
                        <a class="btn" href="<?= htmlspecialchars((string)$notification['action_url'], ENT_QUOTES, 'UTF-8') ?>" style="display:inline-block;text-decoration:none;margin-inline-start:8px;">
                            <?= htmlspecialchars(t((string)$notification['action_label_key']), ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    <?php endif; ?>
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
    <li><a href="/onboarding/goal-questions"><?= htmlspecialchars(t('onboarding.goal_specific_questions_title'), ENT_QUOTES, 'UTF-8') ?></a></li>
</ul>

<h3 id="matches"><?= htmlspecialchars(t('dashboard.match_cards_title'), ENT_QUOTES, 'UTF-8') ?></h3>
<?php if (empty($cards ?? [])): ?>
    <p><?= htmlspecialchars(t('dashboard.match_cards_empty'), ENT_QUOTES, 'UTF-8') ?></p>
    <p style="font-size:13px;color:#555;"><?= htmlspecialchars(t('dashboard.match_cards_processing_hint'), ENT_QUOTES, 'UTF-8') ?></p>
<?php else: ?>
    <ul>
        <?php foreach ($cards as $card): ?>
            <li id="match-card-<?= (int)($card['counterpart_user_id'] ?? 0) ?>" style="margin-bottom:12px;padding:10px;border:1px solid #e5e7eb;border-radius:10px;background:#fff;">
                <div>
                    <strong><?= htmlspecialchars(number_format((float)$card['compatibility_score'], 2), ENT_QUOTES, 'UTF-8') ?></strong>
                    |
                    <?= htmlspecialchars($translateCardKey('match_card.age_label.' . (string)$card['age_range_label_key']), ENT_QUOTES, 'UTF-8') ?>
                    |
                    <?= htmlspecialchars($translateCardKey('match_card.distance_bucket.' . (string)$card['approx_distance_bucket']), ENT_QUOTES, 'UTF-8') ?>
                    |
                    <?= htmlspecialchars($translateCardKey('match_card.emotional_summary.' . (string)$card['emotional_summary_key']), ENT_QUOTES, 'UTF-8') ?>
                </div>

                <div style="margin-top:6px;">
                    <strong><?= htmlspecialchars(t('match_card.shared_goals_label'), ENT_QUOTES, 'UTF-8') ?>:</strong>
                    <?php $goalKeys = (array)($card['shared_goal_title_keys'] ?? []); ?>
                    <?php if ($goalKeys === [] && !empty($card['primary_goal_title_key'])): $goalKeys = [(string)$card['primary_goal_title_key']]; endif; ?>
                    <?= htmlspecialchars(implode('، ', array_map(static fn(string $k): string => t($k), $goalKeys)), ENT_QUOTES, 'UTF-8') ?>
                </div>

                <div style="margin-top:6px;">
                    <strong><?= htmlspecialchars(t('match_card.why_suggested_label'), ENT_QUOTES, 'UTF-8') ?>:</strong>
                    <ul style="margin:6px 0 0 20px;">
                        <?php foreach ((array)($card['match_reasons_json'] ?? []) as $reasonKey): ?>
                            <li><?= htmlspecialchars(t((string)$reasonKey), ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                        <li><?= htmlspecialchars($translateCardKey('match_card.schedule_overlap.' . (string)$card['schedule_overlap_key']), ENT_QUOTES, 'UTF-8') ?></li>
                    </ul>
                </div>

                <div style="margin-top:6px;">
                    <strong><?= htmlspecialchars(t('match_card.conversation_purpose_label'), ENT_QUOTES, 'UTF-8') ?>:</strong>
                    <?= htmlspecialchars(t($purposeFromGoalSlug((string)($card['primary_goal_slug'] ?? ''))), ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div style="margin-top:6px;">
                    <strong><?= htmlspecialchars(t('match_card.starter_label'), ENT_QUOTES, 'UTF-8') ?>:</strong>
                    <?= htmlspecialchars(t($starterFromGoalSlug((string)($card['primary_goal_slug'] ?? ''))), ENT_QUOTES, 'UTF-8') ?>
                </div>

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

<?php if (!empty($passedCards ?? [])): ?>
    <h3><?= htmlspecialchars(t('dashboard.passed_matches_title'), ENT_QUOTES, 'UTF-8') ?></h3>
    <ul>
        <?php foreach ($passedCards as $card): ?>
            <li style="margin-bottom:8px;">
                <strong><?= htmlspecialchars(number_format((float)$card['compatibility_score'], 2), ENT_QUOTES, 'UTF-8') ?></strong>
                <form method="post" action="/match-interest" style="display:inline-block;margin-inline-start:10px;">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf->token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="match_id" value="<?= (int)$card['match_id'] ?>">
                    <button class="btn" type="submit" name="action" value="undo"><?= htmlspecialchars(t('match.interest.undo'), ENT_QUOTES, 'UTF-8') ?></button>
                    <button class="btn" type="submit" name="action" value="interested"><?= htmlspecialchars(t('match.interest.interested'), ENT_QUOTES, 'UTF-8') ?></button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php $content = ob_get_clean(); require __DIR__ . '/layouts/main.php'; ?>
