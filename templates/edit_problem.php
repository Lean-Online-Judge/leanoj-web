<h2>Edit Problem</h2>
<form method="POST" action="index.php?action=edit_problem" enctype="multipart/form-data">
  <input type="hidden" name="id" value="<?= (int)$problem['id'] ?>">
  <label>Title:</label>
  <input type="text" name="title" value="<?= htmlspecialchars($problem['title']) ?>" required>
  <label>Statement:</label>
  <textarea name="statement" required><?= htmlspecialchars($problem['statement']) ?></textarea>
  <label>Template (Optional, upload to replace):</label>
  <br>
  <input type="file" name="template_file" accept=".lean">
  <br>
  <input type="submit" value="Save Changes">
</form>
