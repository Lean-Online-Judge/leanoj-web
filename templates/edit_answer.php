<h2>Edit Answer</h2>
<form method="POST" action="index.php?action=edit_answer" enctype="multipart/form-data">
  <input type="hidden" name="id" value="<?= $answer['id']?>">
  <textarea name="answer_text" style="white-space: nowrap" rows="3"><?= $answer_source ?></textarea>
  <p>Or upload as a file (.lean):</p>
  <input type="file" name="answer_file" accept=".lean">
  <input style="float: right" type="submit" value="Save Changes">
</form>
