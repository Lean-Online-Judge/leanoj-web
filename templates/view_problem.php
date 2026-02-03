<h2>
  <?= htmlspecialchars($problem['title']) ?>
  <?php if (($_SESSION['username'] ?? '') === 'admin'): ?>
    <span class="admin-link"><a href="index.php?action=edit_problem&id=<?= $problem['id'] ?>">[Edit]</a></span>
  <?php endif; ?>
</h2>
<p><?= nl2br(htmlspecialchars($problem['statement'])) ?></p>
<p style="font-size: 0.9em">
  <em>Replace </em><code>sorry</code><em> in the template below with your solution. The Mathlib version currently used is v4.27.0.</em>
</p>
<div class="code-container">
  <button class="copy-button" type="button" onclick="copyCode(this)">Copy</button>
  <pre><?= htmlspecialchars($problem['template']) ?></pre>
</div>
<?php if (isset($_SESSION['user_id'])): ?>
  <h3>Submit Solution</h3>
  <form action="index.php?action=submit_solution" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="problem_id" value="<?= $problem['id'] ?>">
    <input type="file" name="source_file" accept=".lean" required>
    <input type="submit" value="Submit">
  </form>
  <h3>Your Submissions</h3>
  <?php if ($user_submissions): ?>
    <table>
      <thead><tr><th>ID</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($user_submissions as $s): ?>
          <tr>
            <td><a href="index.php?action=view_submission&id=<?= $s['id'] ?>">#<?= $s['id'] ?></a></td>
            <td class="status-cell">
              <span class="status-<?= strtolower($s['status']) ?>">
                <?= htmlspecialchars($s['status']) ?>
              </span>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p>None yet.</p>
  <?php endif; ?>
  <h3>All Submissions</h3>
  <?php if ($is_solved || ($_SESSION['username'] ?? '') === 'admin'): ?>
    <?php if ($all_submissions): ?>
      <table>
        <thead><tr><th>ID</th><th>User</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($all_submissions as $s): ?>
            <tr>
              <td><a href="index.php?action=view_submission&id=<?= $s['id'] ?>">#<?= $s['id'] ?></a></td>
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
    <?php else: ?>
      <p>None yet.</p>
    <?php endif; ?>
  <?php else: ?>
    <p>Solve the problem first to view others' submissions.</p>
  <?php endif; ?>
<?php else: ?>
  <p><a href="index.php?action=login">Login</a> to submit a solution.</p>
<?php endif; ?>
