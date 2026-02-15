<h2>Add Answer</h2>
<form method="POST" action="index.php?action=add_answer" enctype="multipart/form-data">
  <textarea name="answer_text" style="white-space: nowrap" rows="3"></textarea>
  <p>Or upload as a file (.lean):</p>
  <input type="file" name="answer_file" accept=".lean">
  <input style="float: right" type="submit" value="Add answer">
</form>
