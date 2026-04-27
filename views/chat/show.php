<?php ob_start(); ?>
<?php $csrf = app()->make(App\Security\Csrf::class); ?>
<?php
$starterPrompts = [
    t(($starterPromptKeys[0] ?? 'chat.starter_prompt.1')),
    t(($starterPromptKeys[1] ?? 'chat.starter_prompt.2')),
    t(($starterPromptKeys[2] ?? 'chat.starter_prompt.3')),
];
$hasMessages = !empty($chat['messages'] ?? []);
$availableRevealTypes = $revealPanel['available_request_types'] ?? [];
$currentUserId = (int)($currentUserId ?? 0);
?>

<style>
  .chat-list { display:flex; flex-direction:column; gap:10px; }
  .chat-row { display:flex; }
  .chat-row.me { justify-content:flex-end; }
  .chat-row.other { justify-content:flex-start; }
  .chat-row.neutral { justify-content:center; }
  .chat-bubble { max-width:78%; border-radius:12px; padding:8px 10px; border:1px solid #e5e7eb; background:#fff; }
  .chat-row.me .chat-bubble { background:#ecfeff; border-color:#a5f3fc; text-align:right; }
  .chat-row.other .chat-bubble { background:#ffffff; border-color:#e5e7eb; text-align:right; }
  .chat-row.neutral .chat-bubble { background:#f9fafb; border-color:#e5e7eb; text-align:center; }
  .chat-meta { color:#6b7280; font-size:12px; margin-bottom:4px; display:block; }
</style>

<h2><?= htmlspecialchars(t('chat.secure_title'), ENT_QUOTES, 'UTF-8') ?></h2>
<p><?= htmlspecialchars(t('chat.privacy_note_long'), ENT_QUOTES, 'UTF-8') ?></p>

<?php if (!empty($chat['match_id'] ?? null)): ?>
    <div style="display:inline-block;padding:6px 10px;border-radius:999px;background:#f3f4f6;border:1px solid #e5e7eb;margin-bottom:12px;">
        <strong><?= htmlspecialchars(t('chat.match_context_badge'), ENT_QUOTES, 'UTF-8') ?>:</strong>
        #<?= (int)$chat['match_id'] ?>
    </div>
<?php endif; ?>

<?php if (!empty($message ?? null)): ?>
    <div class="ok"><?= htmlspecialchars(t((string)$message), ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<section style="border:1px solid #e5e7eb;border-radius:10px;padding:12px;margin-bottom:14px;background:#fafafa;">
    <h3 style="margin-top:0;"><?= htmlspecialchars(t('chat.starter_title'), ENT_QUOTES, 'UTF-8') ?></h3>
    <p style="margin-top:0;"><?= htmlspecialchars(t('chat.starter_helper'), ENT_QUOTES, 'UTF-8') ?></p>
    <ul style="padding-right:20px; margin-bottom:0;">
        <?php foreach ($starterPrompts as $index => $prompt): ?>
            <li style="margin-bottom:8px;">
                <span><?= htmlspecialchars($prompt, ENT_QUOTES, 'UTF-8') ?></span>
                <button
                    class="btn"
                    type="button"
                    data-starter-insert="<?= htmlspecialchars($prompt, ENT_QUOTES, 'UTF-8') ?>"
                    style="margin-right:8px;"
                >
                    <?= htmlspecialchars(t('chat.starter_use_button'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<div id="messages" class="chat-list" style="border:1px solid #ddd;padding:12px;max-height:420px;overflow:auto;border-radius:8px;margin-bottom:12px;">
    <?php if (!$hasMessages): ?>
        <div data-empty-state="1" style="color:#6b7280;"><?= htmlspecialchars(t('chat.empty_messages'), ENT_QUOTES, 'UTF-8') ?></div>
    <?php else: ?>
        <?php foreach (($chat['messages'] ?? []) as $m): ?>
            <?php
                $senderId = (int)($m['sender_user_id'] ?? 0);
                $type = (string)($m['message_type'] ?? 'text');
                $rowClass = in_array($type, ['prompt', 'system'], true)
                    ? 'neutral'
                    : (($senderId === $currentUserId) ? 'me' : 'other');
            ?>
            <div data-id="<?= (int)$m['id'] ?>" class="chat-row <?= htmlspecialchars($rowClass, ENT_QUOTES, 'UTF-8') ?>">
                <div class="chat-bubble">
                    <small class="chat-meta"><?= htmlspecialchars((string)$m['created_at'], ENT_QUOTES, 'UTF-8') ?> | <?= htmlspecialchars(t('chat.message_type.' . (string)$m['message_type']), ENT_QUOTES, 'UTF-8') ?></small>
                    <div><?= nl2br(htmlspecialchars((string)$m['message_body'], ENT_QUOTES, 'UTF-8')) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<form method="post" action="/chat/send" style="margin-bottom:18px;">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf->token(), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="chat_id" value="<?= (int)$chat['chat_id'] ?>">
    <textarea id="message_body" name="message_body" rows="3" required maxlength="2000" placeholder="<?= htmlspecialchars(t('chat.composer_placeholder'), ENT_QUOTES, 'UTF-8') ?>" style="width:100%;box-sizing:border-box;"></textarea>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:8px;">
        <small style="color:#6b7280;"><?= htmlspecialchars(t('chat.composer_note'), ENT_QUOTES, 'UTF-8') ?></small>
        <button class="btn" type="submit"><?= htmlspecialchars(t('chat.composer_send_button'), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
</form>

<details style="border:1px solid #e5e7eb;border-radius:10px;padding:12px;background:#fcfcfd;">
    <summary style="cursor:pointer;font-weight:600;"><?= htmlspecialchars(t('reveal.secondary_title'), ENT_QUOTES, 'UTF-8') ?></summary>
    <p><?= htmlspecialchars(t('reveal.secondary_helper_1'), ENT_QUOTES, 'UTF-8') ?></p>
    <p><?= htmlspecialchars(t('reveal.secondary_helper_2'), ENT_QUOTES, 'UTF-8') ?></p>

    <?php if (!empty($availableRevealTypes)): ?>
        <form method="post" action="/reveal/request" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf->token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="chat_id" value="<?= (int)$chat['chat_id'] ?>">
            <input type="hidden" name="match_id" value="<?= (int)$chat['match_id'] ?>">
            <select name="reveal_type">
                <?php foreach ($availableRevealTypes as $type): ?>
                    <option value="<?= htmlspecialchars((string)$type, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t('reveal.type.' . (string)$type), ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn" type="submit"><?= htmlspecialchars(t('reveal.request_submit'), ENT_QUOTES, 'UTF-8') ?></button>
        </form>
    <?php else: ?>
        <small><?= htmlspecialchars(t('reveal.secondary_empty_state'), ENT_QUOTES, 'UTF-8') ?></small>
    <?php endif; ?>

    <?php if (!empty($revealPanel['pending_incoming'] ?? [])): ?>
        <h4><?= htmlspecialchars(t('reveal.pending_incoming'), ENT_QUOTES, 'UTF-8') ?></h4>
        <ul>
            <?php foreach ($revealPanel['pending_incoming'] as $req): ?>
                <li>
                    <?= htmlspecialchars(t('reveal.type.' . (string)$req['reveal_type']), ENT_QUOTES, 'UTF-8') ?>
                    <form method="post" action="/reveal/respond" style="display:inline-block;">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf->token(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="chat_id" value="<?= (int)$chat['chat_id'] ?>">
                        <input type="hidden" name="request_id" value="<?= (int)$req['id'] ?>">
                        <button class="btn" type="submit" name="decision" value="accept"><?= htmlspecialchars(t('reveal.accept'), ENT_QUOTES, 'UTF-8') ?></button>
                        <button class="btn" type="submit" name="decision" value="decline"><?= htmlspecialchars(t('reveal.decline'), ENT_QUOTES, 'UTF-8') ?></button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if (!empty($revealPanel['pending_outgoing'] ?? [])): ?>
        <h4><?= htmlspecialchars(t('reveal.pending_outgoing'), ENT_QUOTES, 'UTF-8') ?></h4>
        <ul>
            <?php foreach ($revealPanel['pending_outgoing'] as $req): ?>
                <li>
                    <?= htmlspecialchars(t('reveal.type.' . (string)$req['reveal_type']), ENT_QUOTES, 'UTF-8') ?>
                    <form method="post" action="/reveal/cancel" style="display:inline-block;">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf->token(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="chat_id" value="<?= (int)$chat['chat_id'] ?>">
                        <input type="hidden" name="request_id" value="<?= (int)$req['id'] ?>">
                        <button class="btn" type="submit"><?= htmlspecialchars(t('reveal.cancel'), ENT_QUOTES, 'UTF-8') ?></button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if (!empty($revealPanel['unlocked'] ?? [])): ?>
        <h4><?= htmlspecialchars(t('reveal.unlocked'), ENT_QUOTES, 'UTF-8') ?></h4>
        <ul>
            <?php foreach ($revealPanel['unlocked'] as $item): ?>
                <li>
                    <strong><?= htmlspecialchars(t('reveal.type.' . (string)$item['reveal_type']), ENT_QUOTES, 'UTF-8') ?>:</strong>
                    <?php $type = (string)$item['reveal_type']; ?>
                    <?php if ($type === 'first_name'): ?>
                        <?= htmlspecialchars((string)$item['value'], ENT_QUOTES, 'UTF-8') ?>
                    <?php elseif ($type === 'photo'): ?>
                        <?php $photo = trim((string)$item['value']); ?>
                        <?php if ($photo !== ''): ?>
                            <img src="<?= htmlspecialchars($photo, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars(t('reveal.type.photo'), ENT_QUOTES, 'UTF-8') ?>" style="max-width:220px;max-height:220px;object-fit:cover;">
                        <?php endif; ?>
                    <?php elseif ($type === 'contact_info' || $type === 'deep_profile'): ?>
                        <?php $decoded = json_decode((string)$item['value'], true); ?>
                        <?php if (is_array($decoded)): ?>
                            <ul>
                                <?php foreach ($decoded as $k => $v): ?>
                                    <li><strong><?= htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8') ?>:</strong> <?= htmlspecialchars(is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <?= htmlspecialchars((string)$item['value'], ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                    <?php else: ?>
                        <?= htmlspecialchars((string)$item['value'], ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</details>

<script>
(function () {
  const box = document.getElementById('messages');
  const composer = document.getElementById('message_body');
  const insertButtons = document.querySelectorAll('[data-starter-insert]');

  for (const btn of insertButtons) {
    btn.addEventListener('click', function () {
      if (!composer) return;
      composer.value = String(btn.getAttribute('data-starter-insert') || '');
      composer.focus();
    });
  }

  if (!box) return;

  function latestId() {
    const items = box.querySelectorAll('[data-id]');
    if (!items.length) return 0;
    return parseInt(items[items.length - 1].getAttribute('data-id') || '0', 10) || 0;
  }

  async function poll() {
    const sinceId = latestId();
    const url = '/chat/poll?chat_id=<?= (int)$chat['chat_id'] ?>&since_id=' + sinceId;

    try {
      const res = await fetch(url, { credentials: 'same-origin' });
      if (!res.ok) return;
      const data = await res.json();
      if (!data.messages || !Array.isArray(data.messages)) return;

      for (const m of data.messages) {
        const empty = box.querySelector('[data-empty-state="1"]');
        if (empty) empty.remove();

        const div = document.createElement('div');
        div.setAttribute('data-id', String(m.id));
        const msgType = String(m.message_type || 'text');
        const senderId = parseInt(String(m.sender_user_id || '0'), 10) || 0;
        const rowClass = (msgType === 'prompt' || msgType === 'system')
          ? 'neutral'
          : (senderId === <?= $currentUserId ?> ? 'me' : 'other');
        div.className = 'chat-row ' + rowClass;
        const small = document.createElement('small');
        small.className = 'chat-meta';
        small.textContent = `${m.created_at} | ${m.message_type_label || m.message_type}`;
        const body = document.createElement('div');
        body.textContent = m.message_body;
        const bubble = document.createElement('div');
        bubble.className = 'chat-bubble';
        bubble.appendChild(small);
        bubble.appendChild(body);
        div.appendChild(bubble);
        box.appendChild(div);
      }

      if (data.messages.length > 0) {
        box.scrollTop = box.scrollHeight;
      }
    } catch (_) {
      // polling errors are intentionally silent
    }
  }

  setInterval(poll, 5000);
})();
</script>

<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
