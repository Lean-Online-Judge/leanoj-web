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
<h3>Submit Solution</h3>
<?php if (isset($_SESSION['user_id'])): ?>
<form action="index.php?action=submit_solution" method="POST" enctype="multipart/form-data">
  <input type="hidden" name="problem_id" value="<?= $problem['id'] ?>">
  <div>
  <textarea name="source_text" style="white-space: nowrap" rows="4" placeholder="Paste your code here..."></textarea>
  </div>
<p>Or upload as a file (.lean):</p>
<input type="file" name="source_file" accept=".lean">
&nbsp;
<input type="submit" value="Submit">
</form>
<?php else: ?>
  <p><a href="index.php?action=login">Login</a> to submit a solution.</p>
<?php endif; ?>
<h3>Recent Submissions</h3>
<?php if ($recent_submissions): ?>
<table>
  <thead>
    <tr>
      <th style="text-align: center">#</th>
      <th>User</th>
      <th>Status</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($recent_submissions as $s): ?>
      <tr>
        <td>
          <a href="index.php?action=view_submission&id=<?= (int)$s['id'] ?>">
            <?= (int)$s['id'] ?>
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
  <a href="index.php?action=view_submissions&id=<?= $problem['id'] ?>">View all</a>
<?php else: ?>
  <p>None yet.</p>
<?php endif; ?>
