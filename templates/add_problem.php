<h2>Add Problem</h2>
<form method="POST" action="index.php?action=add_problem" enctype="multipart/form-data">
  <label>Title:</label>
  <input type="text" name="title" required>
  <label>Statement:</label>
  <textarea name="statement" required></textarea>
  <label>Template (.lean):</label>
  <br>
  <input type="file" name="template_file" accept=".lean" required>
  <br>
  <input type="submit" value="Add Problem">
</form>
