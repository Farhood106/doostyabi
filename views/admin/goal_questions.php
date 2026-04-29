<?php ob_start(); $csrf = app()->make(App\Security\Csrf::class); ?>
<h2>Goal Questions</h2>
<form method="get" action="/admin/goal-questions">
<select name="goal_id">
<?php foreach (($goals ?? []) as $g): ?><option value="<?= (int)$g['id'] ?>" <?= ((int)$goalId === (int)$g['id']) ? 'selected' : '' ?>><?= htmlspecialchars((string)$g['slug'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
</select>
<button>Load</button>
</form>
<table border="1" cellpadding="6" style="margin-top:10px;"><tr><th>ID</th><th>pref_key</th><th>input</th><th>required</th><th>active</th><th>toggle</th></tr>
<?php foreach (($definitions ?? []) as $d): ?>
<tr><td><?= (int)$d['id'] ?></td><td><?= htmlspecialchars((string)$d['pref_key'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string)$d['input_type'], ENT_QUOTES, 'UTF-8') ?></td><td><?= (int)$d['is_required'] ?></td><td><?= (int)$d['is_active'] ?></td>
<td><form method="post" action="/admin/goal-questions/toggle"><input type="hidden" name="_token" value="<?= htmlspecialchars($csrf->token(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><input type="hidden" name="goal_id" value="<?= (int)$goalId ?>"><input type="hidden" name="is_active" value="<?= (int)$d['is_active'] ? 0 : 1 ?>"><button><?= (int)$d['is_active'] ? 'Disable' : 'Enable' ?></button></form></td></tr>
<?php endforeach; ?>
</table>
<h3>Add / Edit</h3>
<form method="post" action="/admin/goal-questions/save">
<input type="hidden" name="_token" value="<?= htmlspecialchars($csrf->token(), ENT_QUOTES, 'UTF-8') ?>">
<input type="hidden" name="goal_id" value="<?= (int)$goalId ?>">
<input name="id" placeholder="id (blank for new)">
<input name="pref_key" placeholder="pref_key" required>
<input name="label_key" placeholder="label_key" required>
<input name="helper_text_key" placeholder="helper_text_key">
<input name="input_type" placeholder="select|multiselect|text" value="select">
<input name="value_type" placeholder="string|json" value="string">
<input name="allowed_values_json" placeholder='["a","b"]' value='[]' size="40">
<input name="is_required" placeholder="0|1" value="0" size="4">
<input name="weight" placeholder="1" value="1" size="4">
<input name="is_active" placeholder="0|1" value="1" size="4">
<button>Save</button>
</form>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
