<h2>
  Submission #<?= (int)$submission['id'] ?>
  <?php if ($is_admin): ?>
    <form method="POST" action="index.php?action=rejudge" style="display:inline;">
      <input type="hidden" name="id" value="<?= (int)$submission['id'] ?>">
    </form>
  <?php endif; ?>
</h2>
<table>
  <thead>
    <tr>
      <th>ID</th>
      <th>User</th>
      <th>Problem</th>
      <th>Status</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>
        <a href="index.php?action=view_submission&id=<?= (int)$submission['id'] ?>">
          #<?= (int)$submission['id'] ?>
        </a>
      </td>
      <td><?= htmlspecialchars($submission['username']) ?></td>
      <td>
        <a href="index.php?action=view_problem&id=<?= (int)$submission['problem'] ?>">
          <?= htmlspecialchars($submission['title']) ?>
        </a>
      </td>
      <td class="status-cell">
        <span class="status-<?= strtolower($submission['status']) ?>">
          <?= htmlspecialchars($submission['status']) ?>
        </span>
      </td>
    </tr>
  </tbody>
</table>
<?php if ($show_source): ?>
  <div class="code-container">
    <button class="copy-button" type="button" onclick="copyCode(this)">Copy</button>
    <pre><?= htmlspecialchars($submission['source']) ?></pre>
  </div>
<?php else: ?>
  <p>You have to solve the problem first to view the source code.</p>
<?php endif; ?>
