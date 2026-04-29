<?php ob_start(); ?>
<h2>Goals</h2>
<table border="1" cellpadding="6"><tr><th>ID</th><th>Slug</th><th>Active</th><th>Sort</th><th>Questions</th></tr>
<?php foreach (($goals ?? []) as $g): ?>
<tr>
<td><?= (int)$g['id'] ?></td><td><?= htmlspecialchars((string)$g['slug'], ENT_QUOTES, 'UTF-8') ?></td><td><?= (int)$g['is_active'] ?></td><td><?= (int)$g['sort_order'] ?></td>
<td><a href="/admin/goal-questions?goal_id=<?= (int)$g['id'] ?>">open</a></td>
</tr>
<?php endforeach; ?>
</table>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
