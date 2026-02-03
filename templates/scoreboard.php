<h2>Scoreboard</h2>
<table>
  <thead>
    <tr>
      <th>Rank</th>
      <th>Username</th>
      <th>Solved</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($scoreboard as $row): ?>
      <tr>
        <td><?= (int)$row['rank'] ?></td>
        <td><?= htmlspecialchars($row['username']) ?></td>
        <td><?= (int)$row['solved'] ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
