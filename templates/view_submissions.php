<h2>All Submissions</h2>
<table>
  <thead>
    <tr>
      <th>ID</th>
      <th>Problem</th>
      <th>User</th>
      <th>Status</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($submissions as $s): ?>
      <tr>
        <td>
          <a href="index.php?action=view_submission&id=<?= (int)$s['id'] ?>">
            #<?= (int)$s['id'] ?>
          </a>
        </td>
        <td>
          <a href="index.php?action=view_problem&id=<?= (int)$s['problem'] ?>">
            <?= htmlspecialchars($s['problem_title']) ?>
          </a>
        </td>
        <td><?= htmlspecialchars($s['username']) ?></td>
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
    <a href="index.php?action=view_submissions&page=<?= $page - 1 ?>">&#9664; prev.</a>
  <?php endif; ?>
  <span>Page <?= $page ?> of <?= $total_pages ?></span>
  <?php if ($page < $total_pages): ?>
    <a href="index.php?action=view_submissions&page=<?= $page + 1 ?>">next &#9654;</a>
  <?php endif; ?>
</div>
