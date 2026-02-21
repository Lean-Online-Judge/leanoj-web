<h3>
  Submissions for
  <a href="index.php?action=view_problem&id=<?= (int)$problem['id'] ?>">
    <?= htmlspecialchars($problem['title']) ?>
  </a>
</h3>
<?php if ($submissions): ?>
  <table>
    <thead>
      <tr>
        <th style="text-align: center">#</th>
        <th>User</th>
        <th>Time (UTC)</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($submissions as $s): ?>
        <tr>
          <td>
            <a href="index.php?action=view_submission&id=<?= (int)$s['id'] ?>">
              <?= (int)$s['id'] ?>
            </a>
          </td>
          <td><?= htmlspecialchars($s['username']) ?></td>
          <td><?= $s['time'] ?? "Long time ago" ?></td>
          <td class="status-cell">
            <span class="status-<?= strtolower($s['status']) ?>">
              <?= htmlspecialchars($s['status']) ?>
            </span>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="pagination">
    <?php if ($page > 1): ?>
      <a href="index.php?action=view_submissions&id=<?= $problem['id'] ?>&page=<?= $page - 1 ?>">&#9664; prev.</a>
    <?php endif; ?>
    <span>Page <?= $page ?> of <?= $total_pages ?></span>
    <?php if ($page < $total_pages): ?>
      <a href="index.php?action=view_submissions&id=<?= $problem['id'] ?>&page=<?= $page + 1 ?>">next &#9654;</a>
    <?php endif; ?>
  </div>
<?php else: ?>
  <p>None yet.</p>
<?php endif; ?>
