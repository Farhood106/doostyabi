<?php ob_start(); ?>
<?php $csrf = app()->make(App\Security\Csrf::class); ?>
<h2><?= htmlspecialchars(t('chat.title'), ENT_QUOTES, 'UTF-8') ?></h2>
<p><?= htmlspecialchars(t('chat.privacy_note'), ENT_QUOTES, 'UTF-8') ?></p>
<?php if (!empty($message ?? null)): ?>
    <div class="ok"><?= htmlspecialchars(t((string)$message), ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<h3><?= htmlspecialchars(t('reveal.title'), ENT_QUOTES, 'UTF-8') ?></h3>
<div>
    <p><?= htmlspecialchars(t('reveal.request_prompt'), ENT_QUOTES, 'UTF-8') ?></p>
    <form method="post" action="/reveal/request" style="display:flex;gap:8px;align-items:center;">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf->token(), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="chat_id" value="<?= (int)$chat['chat_id'] ?>">
        <input type="hidden" name="match_id" value="<?= (int)$chat['match_id'] ?>">
        <select name="reveal_type">
            <?php foreach (($revealPanel['available_request_types'] ?? []) as $type): ?>
                <option value="<?= htmlspecialchars((string)$type, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t('reveal.type.' . (string)$type), ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn" type="submit" <?= empty($revealPanel['available_request_types'] ?? []) ? 'disabled' : '' ?>><?= htmlspecialchars(t('reveal.request_submit'), ENT_QUOTES, 'UTF-8') ?></button>
    </form>
    <?php if (empty($revealPanel['available_request_types'] ?? [])): ?>
        <small><?= htmlspecialchars(t('reveal.none_available'), ENT_QUOTES, 'UTF-8') ?></small>
    <?php endif; ?>
</div>

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

<div id="messages" style="border:1px solid #ddd;padding:12px;max-height:400px;overflow:auto;">
    <?php foreach (($chat['messages'] ?? []) as $m): ?>
        <div data-id="<?= (int)$m['id'] ?>" style="margin-bottom:10px;">
            <small><?= htmlspecialchars((string)$m['created_at'], ENT_QUOTES, 'UTF-8') ?> | <?= htmlspecialchars(t('chat.message_type.' . (string)$m['message_type']), ENT_QUOTES, 'UTF-8') ?></small>
            <div><?= nl2br(htmlspecialchars((string)$m['message_body'], ENT_QUOTES, 'UTF-8')) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<form method="post" action="/chat/send">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf->token(), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="chat_id" value="<?= (int)$chat['chat_id'] ?>">
    <label><?= htmlspecialchars(t('chat.message_label'), ENT_QUOTES, 'UTF-8') ?></label>
    <textarea name="message_body" rows="3" required maxlength="2000"></textarea>
    <button class="btn" type="submit"><?= htmlspecialchars(t('chat.send'), ENT_QUOTES, 'UTF-8') ?></button>
</form>

<script>
(function () {
  const box = document.getElementById('messages');
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
        const div = document.createElement('div');
        div.setAttribute('data-id', String(m.id));
        div.style.marginBottom = '10px';
        const small = document.createElement('small');
        small.textContent = `${m.created_at} | ${m.message_type_label || m.message_type}`;
        const body = document.createElement('div');
        body.textContent = m.message_body;
        div.appendChild(small);
        div.appendChild(body);
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
