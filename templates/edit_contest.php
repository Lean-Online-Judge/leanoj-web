<h2>Edit Contest</h2>
<form method="POST" action="index.php?action=edit_contest" enctype="multipart/form-data">
  <input type="hidden" name="id" value="<?= $contest['id'] ?>">
  <h3>Title</h3>
  <input type="text" name="title" value="<?= $contest['title'] ?>" required>
  <h3>Start & End (UTC)</h3>
  <input type="datetime-local" name="start" value="<?= $contest['start'] ?>" required>
  &nbsp;
  <input type="datetime-local" name="end" value="<?= $contest['end'] ?>" required>
  <input style="float: right" type="submit" value="Save Changes">
</form>
