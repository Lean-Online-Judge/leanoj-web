<h2>Add Problem</h2>
<form method="POST" action="index.php?action=add_problem" enctype="multipart/form-data">
  <h3>Title</h3>
  <input type="text" name="title" required>
  <h3>Statement</h3>
  <textarea rows="4" name="statement" required></textarea>
  <h3>Template</h3>
  <textarea name="template_text" style="white-space: nowrap" rows="4"></textarea>
  <p>Or upload as a file (.lean):</p>
  <input type="file" name="template_file" accept=".lean">
  <h3>Answer</h3>
  <textarea name="answer_text" style="white-space: nowrap" rows="1"></textarea>
  <p>Or upload as a file (.lean):</p>
  <input type="file" name="answer_file" accept=".lean">
  <br><br>
  <div style="width: 100%; display: flex; justify-content: flex-end">
    <input type="submit" value="Add Problem">
  </div>
</form>
