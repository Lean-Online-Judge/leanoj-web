<h2>Edit Problem</h2>
<form method="POST" action="index.php?action=edit_problem" enctype="multipart/form-data">
  <input type="hidden" name="id" value="<?= (int)$problem['id'] ?>">
  <p>Title:</p>
  <input type="text" name="title" value="<?= htmlspecialchars($problem['title']) ?>" required>
  <p>Statement:</p>
  <textarea rows="5" name="statement" required><?= htmlspecialchars($problem['statement']) ?></textarea>
  <p>Template (Optional):</p>
  <input type="file" name="template_file" accept=".lean">
  &nbsp;
  <input type="submit" value="Save">
</form>
