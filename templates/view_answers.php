<h2>
  Answer Bank
  <?php if ($is_admin): ?>
    <span class="admin-link"><a href="index.php?action=add_answer">[Add]</a></span>
  <?php endif; ?>
</h2>
<?php if ($answers): ?>
  <?php foreach ($answers as $a): ?>
    <div class="code-container">
      <button class="copy-button" type="button" onclick="copyCode(this)">Copy</button>
      <pre><?= htmlspecialchars($a['body']) ?></pre>
    </div>
  <?php endforeach; ?>
  <div class="pagination">
    <?php if ($page > 1): ?>
      <a href="index.php?action=answer_bank&page=<?= $page - 1 ?>">&#9664 prev.</a>
    <?php endif; ?>
    <span>Page <?= $page ?> of <?= $total_pages ?></span>
    <?php if ($page < $total_pages): ?>
      <a href="index.php?action=answer_bank&page=<?= $page + 1 ?>">next &#9654</a>
    <?php endif; ?>
  </div>
<?php else: ?>
  <p>None yet.</p>
<?php endif; ?>
