<h2>
  Submission #<?= (int)$submission['id'] ?>
  <?php if ($is_admin): ?>
    <form method="POST" action="index.php?action=rejudge" style="display:inline;">
      <input type="hidden" name="id" value="<?= (int)$submission['id'] ?>">
      <input type="submit" value="Rejudge" onclick="return confirm('Rejudge submission?');">
    </form>
  <?php endif; ?>
</h2>
<table>
  <thead>
    <tr>
      <th>User</th>
      <th>Problem</th>
      <th>Status</th>
    </tr>
  </thead>
  <tbody>
    <tr>
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
<div class="code-container">
  <button class="copy-button" type="button" onclick="copyCode(this)">Copy</button>
  <pre><?= htmlspecialchars($submission['source']) ?></pre>
</div>
