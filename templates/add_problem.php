<h2>Add Problem</h2>
<form method="POST" action="index.php?action=add_problem" enctype="multipart/form-data">
  <p>Title:</p>
  <input type="text" name="title" required>
  <p>Statement:</p>
  <textarea rows="5" name="statement" required></textarea>
  <p>Template (.lean):</p>
  <input type="file" name="template_file" accept=".lean" required>
  &nbsp
  <input type="submit" value="Add">
</form>
