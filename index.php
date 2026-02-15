<?php
session_start();

// Load .env
$env_file = __DIR__ . '/.env';
if (!file_exists($env_file)) {
    die('Missing .env file');
}

$env = parse_ini_file($env_file, false, INI_SCANNER_RAW);
if ($env === false || empty($env['DB_PATH'])) {
    die('DB_PATH not configured in .env');
}

$db_path = $env['DB_PATH'];

$db = new PDO("sqlite:" . $db_path);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$action = $_GET['action'] ?? "view_problems";
$is_admin = ($_SESSION['username'] ?? '') === 'admin';

function validate_file($file_key, $max_size = 262144) {
    if (empty($_FILES[$file_key]['tmp_name']) || $_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) return "Upload failed";
    if ($_FILES[$file_key]['size'] > $max_size) return "File too large (max 256KB)";
    if (strpos(mime_content_type($_FILES[$file_key]['tmp_name']), 'text/') !== 0) return "Invalid file";
    return null;
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

if ($action === "logout") {
  session_destroy();
  redirect("view_problems");
}

if ($_SERVER['REQUEST_METHOD'] === "POST") {
  if ($action === "register") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $repeat = $_POST['repeat-password'] ?? '';
    if (empty($username) || empty($password)) {
      redirect("register", [], "Fill in all fields");
    }
    if ($password !== $repeat) {
      redirect("register", [], "Passwords do not match");
    }
    $stmt = $db->prepare("SELECT id FROM users WHERE username = :username");
    $stmt->bindValue(":username", $username);
    $stmt->execute();
    if ($stmt->fetch()) {
      redirect("register", [], "Username already taken");
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (username, password) VALUES (:username, :password)");
    $stmt->bindValue(":username", $username);
    $stmt->bindValue(":password", $hash);
    $stmt->execute();
    $_SESSION['user_id'] = $db->lastInsertId();
    $_SESSION['username'] = $username;
    redirect("view_problems");
  }

  elseif ($action === "login") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (empty($username) || empty($password)) {
      redirect("login", [], "Fill in all fields");
    }
    $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->bindValue(":username", $username);
    $stmt->execute();
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
      $_SESSION['user_id'] = $user['id'];
      $_SESSION['username'] = $user['username'];
      redirect("view_problems");
    }
    redirect("login", [], "Invalid credentials");
  }

  elseif ($action === "submit_solution" && isset($_SESSION['user_id'])) {
    $problem_id = $_POST['problem_id'] ?? 0;
    $source_code = "";
    if (isset($_FILES['template_file']) && $_FILES['template_file']['error'] === UPLOAD_ERR_OK) {
      $err = validate_file('source_file');
      if ($err) {
        redirect("view_problem", ["id" => $problem_id], $err);
      }
      $source_code = file_get_contents($_FILES['source_file']['tmp_name']);
    } elseif (!empty($_POST['source_text'])) {
      $source_code = $_POST['source_text'];
    } else {
      redirect("view_problem", ["id" => $problem_id], "Solution can't be empty");
    }
    $stmt = $db->prepare("INSERT INTO submissions (problem, user, source, status) VALUES (:problem, :user, :source, :status)");
    $stmt->bindValue(":problem", $problem_id);
    $stmt->bindValue(":user", $_SESSION['user_id']);
    $stmt->bindValue(":source", $source_code);
    $stmt->bindValue(":status", "PENDING");
    $stmt->execute();
    redirect("view_problem", ["id" => $problem_id]);
  }

  elseif ($action === "xyzzy" && isset($_SESSION['user_id'])) {
    $err = validate_file('source_file');
    if ($err) {
      redirect("xyzzy", [], $err);
    }
    $stmt = $db->prepare("SELECT id FROM problems WHERE title = 'xyzzy' LIMIT 1");
    $stmt->execute();
    $prob = $stmt->fetch();
    if (!$prob) {
      redirect("view_problems", [], "Special problem not configured");
    }
    $stmt = $db->prepare("INSERT INTO submissions (problem, user, source, status) VALUES (:problem, :user, :source, :status)");
    $stmt->bindValue(":problem", $prob['id']);
    $stmt->bindValue(":user", $_SESSION['user_id']);
    $stmt->bindValue(":source", file_get_contents($_FILES['source_file']['tmp_name']));
    $stmt->bindValue(":status", "PENDING");
    $stmt->execute();
    redirect("xyzzy");
  }

  elseif ($action === "add_problem" && $is_admin) {
    $title = trim($_POST['title']);
    $statement = trim($_POST['statement']);
    $template = trim($_POST['template_text']);
    $def = trim($_POST['answer']);
    $answer = null;
    if (empty($title) || empty($statement)) {
      redirect("add_problem", [], "Fill in required fields");
    }
    if (isset($_FILES['template_file']) && $_FILES['template_file']['error'] === UPLOAD_ERR_OK) {
      $err = validate_file('template_file');
      if ($err) {
        redirect("add_problem", [], $err);
      }
      $template = trim(file_get_contents($_FILES['template_file']['tmp_name']));
    }
    if (empty($template)) {
      redirect("add_problem", [], "Fill in required fields");
    }
    $def = trim($_POST['answer']);
    if (!empty($def)) {
      $stmt = $db->prepare("SELECT id from answers WHERE def = :def");
      $stmt->bindValue(":def", $def);
      $stmt->execute();
      $answer = $stmt->fetch();
      if (!$answer) {
        redirect("add_problem", [], "Answer not found");
      }
    }
    $stmt = $db->prepare("INSERT INTO problems (title, statement, template, answer) VALUES (:title, :statement, :template, :answer)");
    $stmt->bindValue(":title", $title);
    $stmt->bindValue(":statement", $statement);
    $stmt->bindValue(":template", $template);
    $stmt->bindValue(":answer", $answer);
    $stmt->execute();
    redirect("view_problem", ["id" => $db->lastInsertId()]);
  }

  elseif ($action === "edit_problem" && $is_admin) {
    $id = $_POST['id'];
    $title = trim($_POST['title']);
    $statement = trim($_POST['statement']);
    $template = trim($_POST['template_text']);
    $answer = null;
  if (empty($title) || empty($statement)) {
      redirect("edit_problem", ["id" => $id], "Fill in required fields");
    }
    if (isset($_FILES['template_file']) && $_FILES['template_file']['error'] === UPLOAD_ERR_OK) {
      $err = validate_file('template_file');
      if ($err) {
        redirect("edit_problem", ["id" => $id], $err);
      }
      $template = trim(file_get_contents($_FILES['template_file']['tmp_name']));
    }
    if (empty($template)) {
      redirect("edit_problem", ["id" => $id], "Fill in required fields");
    }
    $def = trim($_POST['answer']);
    if (!empty($def)) {
      $stmt = $db->prepare("SELECT id from answers WHERE def = :def");
      $stmt->bindValue(":def", $def);
      $stmt->execute();
      $answer = $stmt->fetchColumn();
      if (!$answer) {
        redirect("edit_problem", ["id" => $id], "Answer not found");
      }
    }
    $stmt = $db->prepare("UPDATE problems SET title = :title, statement = :statement, template = :template, answer = :answer WHERE id = :id");
    $stmt->bindValue(":id", $id);
    $stmt->bindValue(":title", $title);
    $stmt->bindValue(":statement", $statement);
    $stmt->bindValue(":template", $template);
    $stmt->bindValue(":answer", $answer);
    $stmt->execute();
    redirect("view_problem", ["id" => $id]);
  }

  elseif ($action === "add_answer" && $is_admin) {
    $answer = trim($_POST['answer_text']);
    if (isset($_FILES['answer_file']) && $_FILES['answer_file']['error'] === UPLOAD_ERR_OK) {
      $err = validate_file('answer_file');
      if ($err) {
        redirect("add_answer", [], $err);
      }
      $answer = trim(file_get_contents($_FILES['answer_file']['answer_file']));
    }
    if (empty($answer)) {
      redirect("add_anwer", [], "Fill in required fields");
    }
    $sep = separate_imports($answer);
    $stmt = $db->prepare("INSERT INTO answers (imports, def) VALUES (:imports, :def)");
    $stmt->bindValue(":imports", $answer['imports']);
    $stmt->bindValue(":def", $answer['body']);
    $stmt->execute();
    redirect("view_answers");
  }

  elseif ($action === "rejudge" && $is_admin) {
    $stmt = $db->prepare("UPDATE submissions SET status = 'PENDING' WHERE id = :id");
    $stmt->bindValue(":id", $_POST['id']);
    $stmt->execute();
    header("Location: index.php?action=view_submission&id=" . $_POST['id']);
    exit;
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
    $total_stmt = $db->query("SELECT COUNT(*) FROM problems");
    $total_problems = $total_stmt->fetchColumn();
    $total_pages = ceil($total_problems / $per_page);
    $sql = "SELECT p.*,
      (SELECT COUNT(DISTINCT user) FROM submissions WHERE problem = p.id AND status = 'PASSED' AND p.title != 'xyzzy') as solves,
      EXISTS(SELECT 1 FROM submissions WHERE problem = p.id AND user = :user AND status = 'PASSED' AND p.title != 'xyzzy') as is_solved
      FROM problems p
      ORDER BY p.id DESC
      LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(":user", $_SESSION['user_id'] ?? null);
    $stmt->bindValue(":limit", $per_page, PDO::PARAM_INT);
    $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
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
    $sql = "SELECT u.username, COUNT(DISTINCT s.problem) as solved
      FROM users u
      LEFT JOIN submissions s ON u.id = s.user
        AND s.status = 'PASSED'
        AND s.problem != (SELECT id FROM problems WHERE title = 'xyzzy' LIMIT 1)
      GROUP BY u.id
      ORDER BY solved DESC, u.id ASC
      LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($sql);
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
    $id = $_GET['id'] ?? 0;
    $stmt = $db->prepare("SELECT * FROM problems WHERE id = :id");
    $stmt->bindValue(":id", $id);
    $stmt->execute();
    $problem = $stmt->fetch();
    if (!$problem) {
      redirect("view_problems", [], "Not found");
    }
    $stmt = $db->prepare("SELECT s.*, u.username FROM submissions s JOIN users u ON s.user = u.id WHERE s.problem = :id ORDER BY s.id DESC LIMIT 10");
    $stmt->bindValue(":id", $id);
    $stmt->execute();
    $recent_submissions = $stmt->fetchAll();
    include "templates/view_problem.php";
  }

  elseif ($action === "view_submissions") {
    $id = $_GET['id'] ?? 0;
    $stmt = $db->prepare("SELECT * FROM problems WHERE id = :id");
    $stmt->bindValue(":id", $id);
    $stmt->execute();
    $problem = $stmt->fetch();
    if (!$problem) {
      redirect("view_problems", [], "Not found");
    }
    $per_page = 25;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * $per_page;
    $stmt = $db->prepare("SELECT COUNT(*) FROM submissions WHERE problem = :problem_id");
    $stmt->bindValue(":problem_id", $_GET['id']);
    $stmt->execute();
    $total_submissions = $stmt->fetchColumn();
    $total_pages = ceil($total_submissions / $per_page);
    $sql = "SELECT s.*, u.username
      FROM submissions s
      JOIN users u ON s.user = u.id
      WHERE s.problem = :problem_id
      ORDER BY s.id DESC
      LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(":problem_id", $problem['id']);
    $stmt->bindValue(":limit", $per_page, PDO::PARAM_INT);
    $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
    $stmt->execute();
    $submissions = $stmt->fetchAll();
    include "templates/view_submissions.php";
  }

  elseif ($action === "view_submission") {
    $id = $_GET['id'] ?? 0;
    $stmt = $db->prepare("SELECT s.*, p.title, u.username FROM submissions s JOIN problems p ON s.problem = p.id JOIN users u ON s.user = u.id WHERE s.id = :id");
    $stmt->bindValue(":id", $id);
    $stmt->execute();
    $submission = $stmt->fetch();
    if (!$submission) {
      redirect("view_problems", [], "Not found");
    }
    $show_source = false;
    if (isset($_SESSION['user_id'])) {
      $stmt = $db->prepare("SELECT EXISTS(SELECT 1 FROM submissions WHERE problem = :problem AND user = :user AND status = 'PASSED')");
      $stmt->bindValue(":problem", $submission['problem']);
      $stmt->bindValue(":user", $_SESSION['user_id']);
      $stmt->execute();
      $is_solved = (bool)$stmt->fetchColumn();
      $is_owner = $submission['user'] === $_SESSION['user_id'];
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

  elseif ($action === "add_problem" && $is_admin) {
    include "templates/add_problem.php";
  }

  elseif ($action === "edit_problem" && $is_admin) {
    $id = $_GET['id'] ?? 0;
    $stmt = $db->prepare("SELECT p.*, a.def FROM problems p LEFT JOIN answers a ON p.answer = a.id WHERE p.id = :id");
    $stmt->bindValue(":id", $id);
    $stmt->execute();
    $problem = $stmt->fetch();
    if (!$problem) {
      redirect("view_problems", [], "Not found");
    }
    include "templates/edit_problem.php";
  }

  elseif ($action === "add_answer" && $is_admin) {
    include "templates/add_answer.php";
  }
  include "templates/footer.php";
}
?>
