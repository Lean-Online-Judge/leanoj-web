<h2>Add Contest</h2>
<form method="POST" action="index.php?action=add_contest" enctype="multipart/form-data">
  <h3>Title</h3>
  <input type="text" name="title" required>
  <h3>Start & End (UTC)</h3>
  <input type="datetime-local" name="start" required>
  <input type="datetime-local" name="end" required>
  <input style="float: right" type="submit" value="Add Contest">
</form>
