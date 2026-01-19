<?php
session_start();

$db = new PDO("sqlite:/var/www/database/leanoj.db");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$action = $_GET['action'] ?? "view_problems";
$user_id = $_SESSION['user_id'] ?? null;
$is_admin = isset($_SESSION['username']) && $_SESSION['username'] == "admin";
$message = $_GET['message'] ?? "";

function is_empty($field) {
    return !isset($_POST[$field]) || trim($_POST[$field]) === "";
}

function validate_file($file_key, $max_size = 262144) {
    if (empty($_FILES[$file_key]['tmp_name']) || $_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) return "Upload failed.";
    if ($_FILES[$file_key]['size'] > $max_size) return "File too large (max 256KB).";
    if (strpos(mime_content_type($_FILES[$file_key]['tmp_name']), 'text/') !== 0) return "Invalid file.";
    return null;
}

if ($action === "logout") {
  session_destroy();
  header("Location: index.php");
  exit;
}

elseif ($_SERVER['REQUEST_METHOD'] === "POST") {
  if ($action === "register") {
    if (is_empty('username')) {
      header("Location: index.php?action=register&message=Please+fill+all+required+fields");
    } else {
      $stmt = $db->prepare("SELECT id FROM users WHERE username = :username");
      $stmt->bindValue(":username", trim($_POST['username']));
      $stmt->execute();
      if ($stmt->fetch()) {
        header("Location: index.php?action=register&message=Username+already+taken");
      } elseif ($_POST['password'] !== $_POST['repeat-password']) {
        header("Location: index.php?action=register&message=Passwords+do+not+match");
      } else {
        $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, password) VALUES (:username, :password)");
        $stmt->bindValue(":username", trim($_POST['username']));
        $stmt->bindValue(":password", $hash);
        $stmt->execute();
        $_SESSION['user_id'] = $db->lastInsertId();
        $_SESSION['username'] = trim($_POST['username']);
        header("Location: index.php");
      }
    }
  }

  elseif ($action === "login") {
    if (is_empty('username')) {
      header("Location: index.php?action=login&message=Please+fill+all+reqiured+fields");
    } else {
      $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
      $stmt->bindValue(":username", trim($_POST['username']));
      $stmt->execute();
      $user = $stmt->fetch();
      if ($user && password_verify($_POST['password'], $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        header("Location: index.php");
      } else {
        header("Location: index.php?action=login&message=Invalid+credentials");
      }
    }
  }

  elseif ($action === "submit" && $user_id) {
    $err = validate_file('source_file');
    if ($err) {
      header("Location: index.php?action=view_problem&id=" . $_POST['problem_id'] . "&message=" . urlencode($err));
    } else {
      $source = file_get_contents($_FILES['source_file']['tmp_name']);
      $stmt = $db->prepare("INSERT INTO submissions (problem, user, source, status) VALUES (:problem, :user, :source, :status)");
      $stmt->bindValue(":problem", $_POST['problem_id']);
      $stmt->bindValue(":user", $user_id);
      $stmt->bindValue(":source", $source);
      $stmt->bindValue(":status", "PENDING");
      $stmt->execute();
      header("Location: index.php?action=view_problem&id=" . $_POST['problem_id']);
      exit;
    }
  }

  elseif ($action === "add_problem") {
    if (!$is_admin) { header("Location: index.php?message=Unauthorized"); exit; }
    $err = validate_file('template_file');
    if (is_empty('title') || is_empty('statement') || $err) {
      header("Location: index.php?action=add_problem&message=" . urlencode($err ?: "Fill all required fields"));
    } else {
      $template = file_get_contents($_FILES['template_file']['tmp_name']);
      $stmt = $db->prepare("INSERT INTO problems (title, statement, template) VALUES (:title, :statement, :template)");
      $stmt->bindValue(":title", trim($_POST['title']));
      $stmt->bindValue(":statement", trim($_POST['statement']));
      $stmt->bindValue(":template", $template);
      $stmt->execute();
      header("Location: index.php");
    }
  }

  elseif ($action === "edit_problem") {
    if (!$is_admin) { header("Location: index.php?message=Unauthorized"); exit; }
    if (is_empty('title') || is_empty('statement')) {
      header("Location: index.php?action=edit_problem&id=" . $_POST['id'] . "&message=Title+and+statement+required");
    } elseif (!empty($_FILES['template_file']['tmp_name'])) {
      $err = validate_file('template_file');
      if ($err) {
        header("Location: index.php?action=edit_problem&id=" . $_POST['id'] . "&message=" . urlencode($err));
      } else {
        $stmt = $db->prepare("UPDATE problems SET title = :title, statement = :statement, template = :template WHERE id = :id");
        $stmt->bindValue(":template", file_get_contents($_FILES['template_file']['tmp_name']));
        $stmt->bindValue(":title", trim($_POST['title']));
        $stmt->bindValue(":statement", trim($_POST['statement']));
        $stmt->bindValue(":id", $_POST['id']);
        $stmt->execute();
        header("Location: index.php?action=view_problem&id=" . $_POST['id']);
      }
    } else {
      $stmt = $db->prepare("UPDATE problems SET title = :title, statement = :statement WHERE id = :id");
      $stmt->bindValue(":title", trim($_POST['title']));
      $stmt->bindValue(":statement", trim($_POST['statement']));
      $stmt->bindValue(":id", $_POST['id']);
      $stmt->execute();
      header("Location: index.php?action=view_problem&id=" . $_POST['id']);
    }
  }

  elseif ($action === "rejudge" && $is_admin) {
    $stmt = $db->prepare("UPDATE submissions SET status = 'PENDING' WHERE id = :id");
    $stmt->bindValue(":id", $_POST['id']);
    $stmt->execute();
    header("Location: index.php?action=view_submission&id=" . $_POST['id']);
    exit;
  }
} ?>
<!DOCTYPE html>
<html>
<head>
  <title>Lean Online Judge</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css">
  <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/contrib/auto-render.min.js" onload="renderMathInElement(document.body, {delimiters: [{left: '$$', right: '$$', display: true}, {left: '$', right: '$', display: false}]});"></script>
  <script>
    function copyCode(button) {
      const code = button.nextElementSibling.innerText;
      navigator.clipboard.writeText(code).then(() => {
        const originalText = button.innerText;
        button.innerText = "Copied!";
        setTimeout(() => { button.innerText = originalText; }, 2000);
      });
    }
  </script>
<style>
  :root {
    --bg: #ffffff;
    --primary: lightblue;
    --primary-hover: #47f;
    --secondary: #ffff00;
    --text: #000000;
    --border: #aaa;
    --code-bg: #eee;
    --code-text: #000000;
    --error: #ff4500;
  }

  body {
    font-family: sans-serif;
    background-color: var(--bg);
    color: var(--text);
    line-height: 1.6;
    width: 900px;
    margin: 20px auto;
    font-size: 0.95rem;
    border: 1px solid #ccc;
    padding-top: 40px;
    padding-bottom: 20px;
  }

  .main-container {
    width: 800px;
    margin: 0 auto;
    background: var(--card-bg);
    background-color: white;
  }

  .logo {
    font-size: 2.0rem;
    line-height: 1.6em;
    font-weight: 800;
    color: var(--primary);
    text-decoration: none;
    letter-spacing: -0.5px;
  }

  nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 20px 0 20px 0;
  }

  hr {
    border: 1px dashed black;
  }

  input {
    margin-bottom: 18px;
    padding: 5px;
  }

  input[name="title"] {
    width: 100%;
  }

  input[type="file"] {
    border: 1px solid var(--border);
  }

  textarea {
    width: 100%;
    margin-bottom: 18px;
    padding: 5px;
    font-size: 0.9em;
    min-height: 120px;
  }

  table {
    border-collapse: collapse;
    border: 1px solid var(--border);
    margin: 20px 0;
  }

  th, td {
    border: 1px solid var(--border);
    padding: 2px 12px;
    text-align: left;
  }

  th {
    background-color: #eee;
    color: #333;
    font-size: 0.9em;
  }

  .code-container {
    position: relative;
    background: var(--code-bg);
    color: var(--code-text);
    padding: 10px;
    border: 1px solid var(--border);
    overflow-x: auto;
    margin: 10px 0;
  }

  pre { 
    margin: 0; 
    font-size: 0.8rem;
    overflow-x: auto;
  }

  .copy-button {
    position: absolute;
    top: 10px;
    right: 10px;
    font-size: 0.7rem;
    padding: 4px 8px;
  }

  .message {
    background: #fff5f5;
    color: #c53030;
    padding: 15px;
    margin-bottom: 20px;
  }

  .admin-link {
    font-size: 0.6em;
  }

  .status-cell {
    font-size: 0.8rem;
    color: darkblue;
  }

  .status-passed {
    font-size: 0.7rem;
    color: green;
    font-weight: bold;
  }

  .status-pending {
    color: orange;
    font-weight: bold;
}

</style>
</head>

<body>
<div class="main-container">
<a href="index.php" class="logo">Lean Online Judge</a>

  <nav>
    <a href="index.php">Problems</a>
    <a href="index.php?action=scoreboard">Scoreboard</a>
    <a href="index.php?action=info">What is this?</a>
    <?php if ($user_id) { ?>
      <span>
        <strong><?= htmlspecialchars($_SESSION['username']) ?></strong> |
        <a href="index.php?action=logout">Logout</a>
      </span>
    <?php } else { ?>
      <span>
        <a href="index.php?action=login">Login</a> |
        <a href="index.php?action=register">Register</a>
      </span>
    <?php } ?>
  </nav>

  <hr>

  <?php if ($message) { ?><div class="message"><?= htmlspecialchars($message) ?></div><?php } ?>

  <?php if ($action === "info") { ?>
  <p>
  <b>Lean Online Judge</b> is an online Math Olympiad platform that makes use of the <a href="https://lean-lang.org/">Lean interactive theorem prover</a>.
  This means that problems and solutions are written in the special formal syntax of Lean, which enables automated solution checking without human intervention.
  In particular, this makes it possible to host regular online math competitions similar to how it's done in competitive programming on platforms like <a href="https://codeforces.com/">Codeforces</a> and <a href="https://atcoder.jp/">AtCoder</a>.
  </p>

  <p>
  The goal of the project is to introduce Lean to the Math Olympiad community to make that happen. A more technical challenge is to develop a specialized Math Olympiad library in Lean. For now, Lean's generic mathematics library <em>Mathlib</em> is being used.
  </p>

  <p>
  The project is under active development. Join the <a href="https://discord.gg/a4xYPXXBxU">Discord server</a> to participate in all related discussions.
  </p>

  <?php }

  else if ($action === "login") { ?>
    <h2>Login</h2>
    <form method="POST">
      <input type="text" name="username" placeholder="Username" required>
      <br>
      <input type="password" name="password" placeholder="Password" required>
      <br>
      <input type="submit" name="login" value="Login">
    </form>
  <?php }

  elseif ($action === "register") { ?>
    <h2>Register</h2>
    <form method="POST">
      <input type="text" name="username" placeholder="Username" required>
      <br>
      <input type="password" name="password" placeholder="Password" required>
      <br>
      <input type="password" name="repeat-password" placeholder="Repeat Password" required>
      <br>
      <input type="submit" name="register" value="Register">
    </form>
  <?php }

  elseif ($action === "view_problems") {
    $stmt = $db->prepare("SELECT p.*, COUNT(DISTINCT sa.user) as solves, MAX(CASE WHEN su.status = 'PASSED' THEN 1 else 0 END) as is_solved FROM problems p LEFT JOIN submissions sa ON p.id = sa.problem AND sa.status = 'PASSED' LEFT JOIN submissions su ON p.id = su.problem AND su.user = :user AND su.status = 'PASSED' GROUP BY p.id ORDER BY p.id DESC");
    $stmt->bindValue(":user", $user_id);
    $stmt->execute();
    $problems = $stmt->fetchAll();
  ?>
    <h2>Problems <?php if ($is_admin) { ?><span class="admin-link"><a href="index.php?action=add_problem">[Add]</a></span><?php } ?></h2>
    <table>
      <thead><tr><th>Title</th><th>Solves</th></tr></thead>
      <tbody>
        <?php foreach ($problems as $p) { ?>
          <tr>
            <td><?= $p['is_solved'] ? "🎉 " : "" ?><a href="index.php?action=view_problem&id=<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['title']) ?></a></td>
            <td><?= (int)$p['solves'] ?></td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
  <?php }

  elseif ($action === "scoreboard") {
    $scoreboard = $db->query("SELECT u.username, COUNT(DISTINCT s.problem) as solved, RANK() OVER (ORDER BY COUNT(DISTINCT s.problem) DESC) as rank FROM users u LEFT JOIN submissions s ON u.id = s.user AND s.status = 'PASSED' GROUP BY u.id ORDER BY solved DESC")->fetchAll();
  ?>
    <h2>Scoreboard</h2>
    <table>
      <thead><tr><th>Rank</th><th>Username</th><th>Solved</th></tr></thead>
      <tbody>
        <?php foreach ($scoreboard as $row) { ?>
          <tr>
            <td><?= $row['rank'] ?></td>
            <td><?= htmlspecialchars($row['username']) ?></td>
            <td><?= (int)$row['solved'] ?></td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
  <?php }

  elseif ($action === "add_problem") { 
    if (!$is_admin) {
      header("Location: index.php?message=Unauthorized.");
      exit;
    }
  ?>
    <form method="POST" enctype="multipart/form-data">
      <label>Title:</label><input type="text" name="title" required>
      <label>Statement:</label><textarea name="statement" required></textarea>
      <label>Template:</label><br>
      <input type="file" name="template_file" accept=".lean"><br>
      <input type="submit" value="Add">
    </form>
  <?php } 

  elseif ($action === "edit_problem") {
    if (!$is_admin) {
      header("Location: index.php?message=Unauthorized.");
      exit;
    }
    $stmt = $db->prepare("SELECT * FROM problems WHERE id = :id");
    $stmt->bindValue(":id", $_GET['id'] ?? 0);
    $stmt->execute();
    $p = $stmt->fetch();
  ?>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="id" value="<?= htmlspecialchars($p['id']) ?>">
      <label>Title:</label><input type="text" name="title" value="<?= htmlspecialchars($p['title']) ?>" required>
      <label>Statement:</label><textarea name="statement" required><?= htmlspecialchars($p['statement']) ?></textarea>
      <label>Template (Optional):</label><br>
      <input type="file" name="template_file" accept=".lean"><br>
      <input type="submit" value="Save">
    </form>
  <?php }

  elseif ($action === "view_problem") {
    $stmt = $db->prepare("SELECT * FROM problems WHERE id = :id");
    $stmt->bindValue(":id", $_GET['id'] ?? 0);
    $stmt->execute();
    $p = $stmt->fetch();
    if (!$p) {
      header("Location: index.php?message=Not+found.");
      exit;
    } ?>

    <h2>
      <?= htmlspecialchars($p['title']) ?>
      <?php if ($is_admin) { ?><span class="admin-link"><a href="index.php?action=edit_problem&id=<?= $p['id'] ?>">[Edit]</a></span><?php } ?>
    </h2>
    <p><?= nl2br(htmlspecialchars($p['statement'])) ?></p>
    <p style="font-size: 0.9em"><em>Replace </em><code>sorry</code><em> in the template below with your solution. The Mathlib version currently used is v4.26.0.</em></p>
    <div class="code-container">
        <button class="copy-button" type="button" onclick="copyCode(this)">Copy</button>
        <pre><?= htmlspecialchars($p['template']) ?></pre>
    </div>

    <?php if ($user_id) { ?>
      <h3>Submit Solution</h3>
      <form action="index.php?action=submit" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="problem_id" value="<?= $p['id'] ?>">
        <input type="file" name="source_file" accept=".lean" required>
        <input type="submit" value="Submit">
      </form>

      <?php
        $stmt = $db->prepare("SELECT * FROM submissions WHERE problem = :problem AND user = :user ORDER BY id DESC");
        $stmt->bindValue(":problem", $p['id']);
        $stmt->bindValue(":user", $user_id);
        $stmt->execute();
        $user_submissions = $stmt->fetchAll(); 
      ?>

      <h3>Your Submissions</h3>
      <?php if ($user_submissions) { ?>
        <table>
          <thead><tr><th>ID</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($user_submissions as $s) { ?>
              <tr>
                <td><a href="index.php?action=view_submission&id=<?= $s['id'] ?>">#<?= $s['id'] ?></a></td>
                <td class="status-cell">
                    <span class="status-<?= strtolower($s['status']) ?>">
                        <?= htmlspecialchars($s['status']) ?>
                    </span>
                </td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      <?php } else { ?> <p>None yet.</p> <?php } ?>

      <h3>All Submissions</h3>
      <?php
        $stmt = $db->prepare("SELECT EXISTS(SELECT 1 FROM submissions WHERE problem = :problem AND user = :user AND status = 'PASSED')");
        $stmt->bindValue(":problem", $p['id']);
        $stmt->bindValue(":user", $user_id);
        $stmt->execute();
        $is_solved = (bool)$stmt->fetchColumn();

        if ($is_solved || $is_admin) {
          $stmt = $db->prepare("SELECT s.*, u.username FROM submissions s JOIN users u ON s.user = u.id WHERE s.problem = :problem ORDER BY s.id DESC");
          $stmt->bindValue(":problem", $p['id']);
          $stmt->execute();
          $all_submissions = $stmt->fetchAll();
	  if ($all_submissions) {
      ?>
          <table>
            <thead><tr><th>ID</th><th>User</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($all_submissions as $s) { ?>
                <tr>
                  <td><a href="index.php?action=view_submission&id=<?= $s['id'] ?>">#<?= $s['id'] ?></a></td>
                  <td><?= htmlspecialchars($s['username']) ?></td>
                  <td class="status-cell">
                      <span class="status-<?= strtolower($s['status']) ?>">
                          <?= htmlspecialchars($s['status']) ?>
                      </span>
                  </td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
	  <?php } else { ?>
	  <p>None yet.</p>
	  <?php }
	} else { ?> <p>Solve the problem first to view others' submissions.</p> <?php }
    } else { ?> <p><a href='index.php?action=login'>Login</a> to submit a solution.</p> <?php }
  }

  elseif ($action === "view_submission") {
    if (!$user_id) {
      header("Location: index.php?action=login");
      exit;
    }
    $stmt = $db->prepare("SELECT s.*, p.title, u.username FROM submissions s JOIN problems p ON s.problem = p.id JOIN users u ON s.user = u.id WHERE s.id = :id");
    $stmt->bindValue(":id", $_GET['id'] ?? 0);
    $stmt->execute();
    $s = $stmt->fetch();
    if (!$s) {
      header("Location: index.php?message=Not+found");
      exit;
    } 

    $stmt = $db->prepare("SELECT EXISTS(SELECT 1 FROM submissions WHERE problem = :problem AND user = :user AND status = 'PASSED')");
    $stmt->bindValue(":problem", $s['problem']);
    $stmt->bindValue(":user", $user_id);
    $stmt->execute();
    $is_solved = (bool)$stmt->fetchColumn();
    if ($s['user'] === $user_id || $is_admin || $is_solved) {
    ?>
      <h2>
        Submission #<?= $s['id']?>
        <?php if ($is_admin) { ?>
          <form method="POST" action="index.php?action=rejudge" style="display:inline;">
            <input type="hidden" name="id" value="<?= $s['id'] ?>">
            <input type="submit" value="Rejudge" onclick="return confirm('Rejudge submission?');">
          </form>
        <?php } ?>
      </h2>

      <table>
        <thead><tr><th>User</th><th>Problem</th><th>Status</th></tr></thead>
        <tbody>
          <tr>
            <td><?= htmlspecialchars($s['username']) ?></td>
            <td><a href="index.php?action=view_problem&id=<?= $s['problem'] ?>"><?= htmlspecialchars($s['title']) ?></a></td>
            <td class="status-cell">
                <span class="status-<?= strtolower($s['status']) ?>">
                    <?= htmlspecialchars($s['status']) ?>
                </span>
            </td>
          </tr>
        </tbody>
      </table>
      <div class="code-container">
          <button class="copy-button" type="button" onclick="copyCode(this)">Copy</button>
          <pre><?= htmlspecialchars($s['source']) ?></pre>
      </div>
    <?php } else { 
      header("Location: index.php?message=Not+allowed");
      exit;
    }
  } else {
    header("Location: index.php?message=Not+found");
    exit;
  } ?>
</div>
</body>
</html>
