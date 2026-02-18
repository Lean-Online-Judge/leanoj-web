<?php
session_start();
$env_file = __DIR__ . '/.env';
if (!file_exists($env_file)) {
    die('Missing .env file');
}

$env = parse_ini_file($env_file, false, INI_SCANNER_RAW);
if ($env === false || empty($env['DB_PATH'])) {
    die('DB_PATH not configured in .env');
}

$db = new PDO("sqlite:" . $env['DB_PATH']);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$action = $_GET['action'] ?? "view_problems";
$is_admin = ($_SESSION['username'] ?? "") === 'admin';
$user_id = (int)$_SESSION['user_id'] ?: null;

function validate_file($file_key, $max_size = 262144) {
  if ($_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) {
    return "Upload failed";
  }
  if ($_FILES[$file_key]['size'] > $max_size) {
    return "File too large (max 256KB)";
  }
  if (strpos(mime_content_type($_FILES[$file_key]['tmp_name']), 'text/') !== 0) {
    return "Invalid file";
  }
}

function redirect($action = "view_problems", $params = [], $message = "") {
  $query = $params;
  $query['action'] = $action;
  if ($message) {
    $query['message'] = $message;
  }
  header("Location: index.php?" . http_build_query($query));
  exit;
}

function separate_imports($content) {
  $lines = explode("\n", str_replace("\r", "", $content));
  $imports = [];
  $body = [];
  foreach ($lines as $line) {
    $trimmed = trim($line);
    if ($trimmed === "") {
      continue;
    }
    if (strpos($trimmed, "import") === 0) {
      $imports[] = $line;
    } else {
      $body[] = $line;
    }
  }
  return [
    "imports" => implode("\n", $imports),
    "body" => implode("\n", $body)
  ];
}

function check_can_submit($db, $problem_id) {
  $stmt = $db->prepare("
    SELECT p.contest, c.start, c.end
    FROM problems p
    LEFT JOIN contests c ON p.contest = c.id
    WHERE p.id = :id");
  $stmt->execute([":id" => $problem_id]);
  $problem = $stmt->fetch();
  if ($problem['contest']) {
    $cur = time();
    $start = strtotime($problem['start']);
    $end = strtotime($problem['end']);
    if ($cur < $start || $cur > $end) {
      return "Contest is inactive";
    }
  }
}

if ($action === "logout") {
  session_destroy();
  redirect("view_problems");
}

if ($_SERVER['REQUEST_METHOD'] === "POST") {
  if ($action === "register") {
    $username = trim($_POST['username'] ?? "");
    $password = $_POST['password'] ?? "";
    $repeat = $_POST['repeat-password'] ?? "";
    if (empty($username) || empty($password)) {
      redirect("register", [], "Fill in all fields");
    }
    if ($password !== $repeat) {
      redirect("register", [], "Passwords do not match");
    }
    $stmt = $db->prepare("SELECT id FROM users WHERE username = :username");
    $stmt->execute([":username" => $username]);
    if ($stmt->fetch()) {
      redirect("register", [], "Username already taken");
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (username, password) VALUES (:username, :password)");
    $stmt->execute([
      ":username" => $username,
      ":password" => $hash,
    ]);
    $_SESSION['user_id'] = $db->lastInsertId();
    $_SESSION['username'] = $username;
    redirect("view_problems");
  }

  elseif ($action === "login") {
    $username = trim($_POST['username'] ?? "");
    $password = $_POST['password'] ?? "";
    if (empty($username) || empty($password)) {
      redirect("login", [], "Fill in all fields");
    }
    $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->execute([":username" => $username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
      $_SESSION['user_id'] = $user['id'];
      $_SESSION['username'] = $user['username'];
      redirect("view_problems");
    }
    redirect("login", [], "Invalid credentials");
  }

  elseif ($action === "submit_solution" && $user_id) {
    $problem_id = $_POST['problem_id'] ?? 0;
    $err = check_can_submit($db, $problem_id);
    if ($err) {
      redirect("view_problem", ["id" => $problem_id], $err);
    }
    $source_code = trim($_POST['source_text'] ?? "");
    if (!empty($_FILES['source_file']['tmp_name'])) {
      $err = validate_file('source_file');
      if ($err) {
        redirect("view_problem", ["id" => $problem_id], $err);
      }
      $source_code = trim(file_get_contents($_FILES['source_file']['tmp_name']));
    }
    if (empty($source_code)) {
      redirect("view_problem", ["id" => $problem_id], "Solution can't be empty");
    }
    $stmt = $db->prepare("
      INSERT INTO submissions (problem, user, source, status)
      VALUES (:problem, :user, :source, :status)");
    $stmt->execute([
      ":problem" => $problem_id,
      ":user" => $user_id,
      ":source" => $source_code,
      ":status" => "PENDING",
    ]);
    redirect("view_problem", ["id" => $problem_id]);
  }

  elseif ($action === "add_problem" && $is_admin) {
    $title = trim($_POST['title'] ?? "");
    $statement = trim($_POST['statement'] ?? "");
    $note = trim($_POST['note'] ?? "") ?: null;
    $template = trim($_POST['template_text'] ?? "");
    $answer_text = trim($_POST['answer'] ?? "");
    $contest = (int)$_POST['contest'] ?: null;
    if (!empty($_FILES['template_file']['tmp_name'])) {
      $err = validate_file('template_file');
      if ($err) {
        redirect("add_problem", [], $err);
      }
      $template = trim(file_get_contents($_FILES['template_file']['tmp_name']));
    }
    if (empty($title) || empty($statement) || empty($template)) {
      redirect("add_problem", [], "Fill in required fields");
    }
    if ($answer_text) {
      $stmt = $db->prepare("SELECT id from answers WHERE body = :body");
      $stmt->execute([":body" => $answer_text]);
      $answer = $stmt->fetchColumn();
      if (!$answer) {
        redirect("add_problem", [], "Answer not found");
      }
    }
    if ($contest) {
      $stmt = $db->prepare("SELECT id from contests WHERE id = :id");
      $stmt->execute([":id" => $contest]);
      $contest = $stmt->fetchColumn();
      if (!$contest) {
        redirect("add_problem", [], "Contest not found");
      }
    }
    $stmt = $db->prepare("
      INSERT INTO problems (title, statement, note, template, answer, contest)
      VALUES (:title, :statement, :note, :template, :answer, :contest)");
    $stmt->bindValue(":title", $title);
    $stmt->bindValue(":statement", $statement);
    $stmt->bindValue(":note", $note);
    $stmt->bindValue(":template", $template);
    $stmt->bindValue(":answer", $answer);
    $stmt->bindValue(":contest", $contest);
    $stmt->execute([
      ":title" => $title,
      ":statement" => $statement,
      ":note" => $note,
      ":template" => $template,
      ":answer" => $answer,
      ":contest" => $contest,
    ]);
    redirect("view_problem", ["id" => $db->lastInsertId()]);
  }

  elseif ($action === "edit_problem" && $is_admin) {
    $id = (int)$_POST['id'] ?: null;
    $title = trim($_POST['title'] ?? "");
    $statement = trim($_POST['statement'] ?? "");
    $template = trim($_POST['template_text'] ?? "");
    $note = trim($_POST['note'] ?? "") ?: null;
    $answer_text = trim($_POST['answer'] ?? "");
    $contest = (int)$_POST['contest'] ?: null;
    if (empty($title) || empty($statement)) {
      redirect("edit_problem", ["id" => $id], "Fill in required fields");
    }
    if (!empty($_FILES['template_file']['tmp_name'])) {
      $err = validate_file('template_file');
      if ($err) {
        redirect("edit_problem", ["id" => $id], $err);
      }
      $template = trim(file_get_contents($_FILES['template_file']['tmp_name']));
    }
    if (empty($template)) {
      redirect("edit_problem", ["id" => $id], "Fill in required fields");
    }
    if ($answer_text) {
      $stmt = $db->prepare("SELECT id from answers WHERE body = :body");
      $stmt->execute([":body" => $answer_text]);
      $answer = $stmt->fetchColumn();
      if (!$answer) {
        redirect("edit_problem", ["id" => $id], "Answer not found");
      }
    }
    if ($contest) {
      $stmt = $db->prepare("SELECT id from contests WHERE id = :id");
      $stmt->execute([":id" => $contest]);
      $contest = $stmt->fetchColumn();
      if (!$contest) {
        redirect("edit_problem", ["id" => $id], "Contest not found");
      }
    }
    $stmt = $db->prepare("
      UPDATE problems
      SET title = :title, statement = :statement, note = :note, template = :template,
        answer = :answer, contest = :contest
      WHERE id = :id");
    $stmt->execute([
      ":id" => $id,
      ":title" => $title,
      ":statement" => $statement,
      ":note" => $note,
      ":template" => $template,
      ":answer" => $answer,
      ":contest" => $contest,
    ]);
    redirect("view_problem", ["id" => $id]);
  }

  elseif ($action === "add_answer" && $is_admin) {
    $answer_source = trim($_POST['answer_text'] ?? "");
    if (!empty($_FILES['answer_file']['tmp_name'])) {
      $err = validate_file('answer_file');
      if ($err) {
        redirect("add_answer", [], $err);
      }
      $answer_source = trim(file_get_contents($_FILES['answer_file']['tmp_name']));
    }
    if (empty($answer_source)) {
      redirect("add_answer", [], "Fill in required fields");
    }
    $answer = separate_imports($answer_source);
    $stmt = $db->prepare("INSERT INTO answers (imports, body) VALUES (:imports, :body)");
    $stmt->execute([
      ":imports" => $answer['imports'],
      ":body" => $answer['body']
    ]);
    redirect("view_answers");
  }

  elseif ($action === "edit_answer" && $is_admin) {
    $id = (int)$_POST['id'];
    $answer_source = trim($_POST['answer_text'] ?? "");
    if (!empty($_FILES['answer_file']['tmp_name'])) {
      $err = validate_file('answer_file');
      if ($err) {
        redirect("edit_answer", ["id" => $id], $err);
      }
      $answer_source = trim(file_get_contents($_FILES['answer_file']['tmp_name']));
    }
    if (empty($answer_source)) {
      redirect("edit_answer", ["id" => $id], "Fill in required fields");
    }
    $answer = separate_imports($answer_source);
    $stmt = $db->prepare("
      UPDATE answers
      SET imports = :imports, body = :body
      WHERE id = :id");
    $stmt->execute([
      ":imports" => $answer['imports'],
      ":body" => $answer['body'],
      ":id" => $id,
    ]);
    redirect("view_answers");
  }

  elseif ($action === "add_contest" && $is_admin) {
    $title = trim($_POST['title'] ?? "");
    $start = $_POST['start'] ?? "";
    $end = $_POST['end'] ?? "";
    if (empty($title) || empty($start) || empty($end)) {
      redirect("add_contest", [], "Fill in required fields");
    }
    $stmt = $db->prepare("
      INSERT INTO contests (title, start, end)
      VALUES (:title, :start, :end)");
    $stmt->execute([
      ":title" => $title,
      ":start" => $start,
      ":end" => $end,
    ]);
    redirect("view_contest", ["id" => $db->lastInsertId()]);
  }

  elseif ($action === "edit_contest" && $is_admin) {
    $id = (int)$_POST['id'] ?: null;
    $title = trim($_POST['title'] ?? "");
    $start = $_POST['start'] ?? "";
    $end = $_POST['end'] ?? "";
    if (empty($title) || empty($start) || empty($end)) {
      redirect("edit_contest", ["id" => $id], "Fill in required fields");
    }
    $stmt = $db->prepare("
      UPDATE contests
      SET title = :title, start = :start, end = :end where id = :id");
    $stmt->execute([
      ":id" => $id,
      ":title" => $title,
      ":start" => $start,
      ":end" => $end,
    ]);
    redirect("view_contest", ["id" => $id]);
  }

  elseif ($action === "rejudge" && $is_admin) {
    $id = (int)$_POST['id'] ?: null;
    $stmt = $db->prepare("UPDATE submissions SET status = 'PENDING' WHERE id = :id");
    $stmt->execute([":id" => $id]);
    redirect("view_submission", ["id" => $id]);
  }
}

if ($_SERVER['REQUEST_METHOD'] === "GET") {
  include "templates/header.php";

  if ($action === "register") {
    include "templates/register.php";
  }

  elseif ($action === "login") {
    include "templates/login.php";
  }

  elseif ($action === "view_problems") {
    $per_page = 25;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * $per_page;
    $stmt = $db->query("SELECT COUNT(*) FROM problems");
    $total_problems = $stmt->fetchColumn();
    $total_pages = ceil($total_problems / $per_page);
    $stmt = $db->prepare("
      SELECT p.*,
        (SELECT COUNT(DISTINCT user) FROM submissions
          WHERE problem = p.id AND status = 'PASSED' AND p.title != 'xyzzy') as solves,
        EXISTS(SELECT 1 FROM submissions WHERE problem = p.id AND user = :user_id AND
          status = 'PASSED' AND p.title != 'xyzzy') as is_solved
      FROM problems p
      WHERE p.contest IS NULL
      ORDER BY p.id DESC
      LIMIT :limit OFFSET :offset");
    $stmt->bindValue(":limit", $per_page, PDO::PARAM_INT);
    $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
    $stmt->bindValue(":user_id", $user_id);
    $stmt->execute();
    $problems = $stmt->fetchAll();
    include "templates/view_problems.php";
  }

  elseif ($action === "scoreboard") {
    $per_page = 25;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * $per_page;
    $total_stmt = $db->query("SELECT COUNT(*) FROM users");
    $total_users = $total_stmt->fetchColumn();
    $total_pages = ceil($total_users / $per_page);
    $stmt = $db->prepare("
      SELECT u.username, COUNT(DISTINCT s.problem) as solved
      FROM users u
      LEFT JOIN submissions s ON u.id = s.user
        AND s.status = 'PASSED'
        AND s.problem != (SELECT id FROM problems WHERE title = 'xyzzy' LIMIT 1)
      GROUP BY u.id
      ORDER BY solved DESC, u.id ASC
      LIMIT :limit OFFSET :offset");
    $stmt->bindValue(":limit", $per_page, PDO::PARAM_INT);
    $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
    $stmt->execute();
    $scoreboard = $stmt->fetchAll();
    include "templates/scoreboard.php";
  }

  elseif ($action === "info") {
    include "templates/info.php";
  }

  elseif ($action === "view_problem") {
    $id = (int)$_GET['id'];
    $stmt = $db->prepare("
      SELECT p.*, c.start, c.end
      FROM problems p
      LEFT JOIN contests c on p.contest = c.id
      WHERE p.id = :id");
    $stmt->execute([":id" => $id]);
    $problem = $stmt->fetch();
    if (!$problem) {
      redirect("view_problems", [], "Not found");
    }
    $stmt = $db->prepare("
      SELECT s.*, u.username
      FROM submissions s
      JOIN users u ON s.user = u.id
      WHERE s.problem = :id
      ORDER BY s.id DESC LIMIT 10");
    $stmt->execute(["id" => $id]);
    $recent_submissions = $stmt->fetchAll();

    $can_view = true;
    $can_submit = true;
    if ($problem['contest']) {
      $cur = time();
      $start = strtotime($problem['start']);
      $end = strtotime($problem['end']);
      $can_view = $is_admin || $cur >= $start;
      $can_submit = $cur <= $end;
    }
    include "templates/view_problem.php";
  }

  elseif ($action === "view_submissions") {
    $id = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT * FROM problems WHERE id = :id");
    $stmt->execute(["id" => $id]);
    $problem = $stmt->fetch();
    if (!$problem) {
      redirect("view_problems", [], "Not found");
    }
    $per_page = 25;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * $per_page;
    $stmt = $db->prepare("SELECT COUNT(*) FROM submissions WHERE problem = :problem_id");
    $stmt->execute([":problem_id" => $id]);
    $total_submissions = $stmt->fetchColumn();
    $total_pages = ceil($total_submissions / $per_page);
    $stmt = $db->prepare("
      SELECT s.*, u.username
      FROM submissions s
      JOIN users u ON s.user = u.id
      WHERE s.problem = :problem_id
      ORDER BY s.id DESC
      LIMIT :limit OFFSET :offset");
    $stmt->bindValue(":limit", $per_page, PDO::PARAM_INT);
    $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
    $stmt->bindValue("problem_id", $id);
    $stmt->execute();
    $submissions = $stmt->fetchAll();
    include "templates/view_submissions.php";
  }

  elseif ($action === "view_submission") {
    $id = (int)$_GET['id'];
    $stmt = $db->prepare("
      SELECT s.*, p.title, u.username
      FROM submissions s
      JOIN problems p ON s.problem = p.id
      JOIN users u ON s.user = u.id
      WHERE s.id = :id");
    $stmt->execute([":id" => $id]);
    $submission = $stmt->fetch();
    if (!$submission) {
      redirect("view_problems", [], "Not found");
    }
    $show_source = false;
    if ($user_id) {
      $stmt = $db->prepare("
        SELECT EXISTS(
          SELECT 1 FROM submissions
          WHERE problem = :problem AND user = :user AND status = 'PASSED')");
      $stmt->execute([
        ":problem" => $submission['problem'],
        ":user" => $user_id,
      ]);
      $is_solved = (bool)$stmt->fetchColumn();
      $is_owner = $submission['user'] === $user_id;
      $is_xyzzy = ($submission['title'] === 'xyzzy');
      $show_source = $is_admin || $is_owner || ($is_solved && !$is_xyzzy);
    }
    include "templates/view_submission.php";
  }

  elseif ($action === "view_answers") {
    $per_page = 25;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * $per_page;
    $total_stmt = $db->query("SELECT COUNT(*) FROM answers");
    $total_problems = $total_stmt->fetchColumn();
    $total_pages = ceil($total_problems / $per_page);

    $stmt = $db->prepare("SELECT * FROM answers LIMIT :limit OFFSET :offset");
    $stmt->bindValue(":limit", $per_page, PDO::PARAM_INT);
    $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
    $stmt->execute();
    $answers = $stmt->fetchAll();
    include "templates/view_answers.php";
  }

  elseif ($action === "view_contests") {
    $per_page = 25;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * $per_page;
    $total_stmt = $db->query("SELECT COUNT(*) FROM contests");
    $total_problems = $total_stmt->fetchColumn();
    $total_pages = ceil($total_problems / $per_page);

    $stmt = $db->prepare("
      SELECT * FROM contests ORDER BY id DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(":limit", $per_page, PDO::PARAM_INT);
    $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
    $stmt->execute();
    $contests = $stmt->fetchAll();
    include "templates/view_contests.php";
  }

  elseif ($action === "add_problem" && $is_admin) {
    include "templates/add_problem.php";
  }

  elseif ($action === "edit_problem" && $is_admin) {
    $id = (int)$_GET['id'];
    $stmt = $db->prepare("
      SELECT p.*, a.body
      FROM problems p
      LEFT JOIN answers a ON p.answer = a.id
      WHERE p.id = :id");
    $stmt->execute([":id" => $id]);
    $problem = $stmt->fetch();
    if (!$problem) {
      redirect("view_problems", [], "Not found");
    }
    include "templates/edit_problem.php";
  }

  elseif ($action === "add_answer" && $is_admin) {
    include "templates/add_answer.php";
  }

  elseif ($action === "edit_answer" && $is_admin) {
    $id = (int)$_GET['id'];
    $stmt = $db->prepare(" SELECT * FROM answers WHERE id = :id");
    $stmt->execute([":id" => $id]);
    $answer = $stmt->fetch();
    if (!$answer) {
      redirect("view_answers", [], "Not found");
    }
    $answer_source = trim($answer['imports'] . "\n\n" . $answer['body']);
    include "templates/edit_answer.php";
  }

  elseif ($action === "add_contest" && $is_admin) {
    include "templates/add_contest.php";
  }

  elseif ($action === "edit_contest" && $is_admin) {
    $id = (int)$_GET['id'] ?? 0;
    $stmt = $db->prepare("SELECT * FROM contests WHERE id = :id");
    $stmt->bindValue(":id", $id);
    $stmt->execute();
    $contest = $stmt->fetch();
    if (!$contest) {
      redirect("view_contests", [], "Not found");
    }
    include "templates/edit_contest.php";
  }

  elseif ($action === "view_contest") {
    $id = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT * FROM contests where id = :id");
    $stmt->execute([":id" => $id]);
    $contest = $stmt->fetch();

    if (!$contest) {
      redirect("view_contests", [], "Not found");
    }
    $stmt = $db->prepare("
      SELECT p.*,
      (SELECT COUNT(DISTINCT user) FROM submissions
        WHERE problem = p.id AND status = 'PASSED') as solves,
      EXISTS(SELECT 1 FROM submissions
        WHERE problem = p.id AND user = :user_id AND status = 'PASSED') as is_solved
      FROM problems p WHERE p.contest = :id");
    $stmt->execute([":user_id" => $user_id, ":id" => $id]);
    $problems = $stmt->fetchAll();
    include "templates/view_contest.php";
  }

  elseif ($action === "results") {
    $id = (int)$_GET['id'];
    $per_page = 25;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * $per_page;
    $stmt = $db->prepare("
      SELECT COUNT(DISTINCT s.user)
      FROM submissions s
      JOIN problems p ON s.problem = p.id
      WHERE s.status = 'PASSED' AND p.contest = :id");
    $stmt->bindValue(":id", $id);
    $stmt->execute();
    $total_users = $stmt->fetchColumn();
    $total_pages = ceil($total_users / $per_page);

    $stmt = $db->prepare("
      SELECT u.username, COUNT(DISTINCT s.problem) as solved
      FROM users u
      LEFT JOIN submissions s ON u.id = s.user
      LEFT JOIN problems p ON s.problem = p.id
      WHERE p.contest = :id AND s.status = 'PASSED'
      GROUP BY u.id
      ORDER BY solved DESC, u.id ASC
      LIMIT :limit OFFSET :offset");
    $stmt->bindValue(":limit", $per_page, PDO::PARAM_INT);
    $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
    $stmt->bindValue(":id", $id);
    $stmt->execute();
    $results = $stmt->fetchAll();
    include "templates/results.php";
  }

  include "templates/footer.php";
}
?>
