<?php ob_start(); ?>
<h2>Admin MVP</h2>
<ul>
  <li><a href="/admin/goals">Manage Goals</a></li>
  <li><a href="/admin/goal-questions">Manage Goal Questions</a></li>
</ul>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
