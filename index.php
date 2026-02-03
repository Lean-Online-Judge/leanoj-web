<?php
session_start();

$db_path = "/home/ansar/leanoj/database/leanoj.db";

$db = new PDO("sqlite:" . $db_path);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$action = $_GET['action'] ?? "view_problems";

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
      redirect("register", [], "Fill in all fieds");
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
    $err = validate_file('source_file');
    if ($err) {
      redirect("view_problem", ["id" => $problem_id], $err);
    }
    $stmt = $db->prepare("INSERT INTO submissions (problem, user, source, status) VALUES (:problem, :user, :source, :status)");
    $stmt->bindValue(":problem", $problem_id);
    $stmt->bindValue(":user", $_SESSION['user_id']);
    $stmt->bindValue(":source", file_get_contents($_FILES['source_file']['tmp_name']));
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

  elseif ($action === "add_problem" && $_SESSION['username'] === 'admin') {
    $title = trim($_POST['title'] ?? '');
    $statement = trim($_POST['statement'] ?? '');
    $err = validate_file('template_file');
    if (empty($title) || empty($statement) || $err) {
      redirect("add_problem", [], $err ?: "Fill in all fields");
    }
    $stmt = $db->prepare("INSERT INTO problems (title, statement, template) VALUES (:title, :statement, :template)");
    $stmt->bindValue(":title", $title);
    $stmt->bindValue(":statement", $statement);
    $stmt->bindValue(":template", file_get_contents($_FILES['template_file']['tmp_name']));
    $stmt->execute();
    redirect("view_problem", ["id" => $db->lastInsertId()]);
  }

  elseif ($action === "edit_problem" && $_SESSION['username'] === 'admin') {
    $id = $_POST['id'] ?? 0;
    $title = trim($_POST['title'] ?? '');
    $statement = trim($_POST['statement'] ?? '');
    if (empty($title) || empty($statement)) {
      redirect("edit_problem", ["id" => $id], "Fill in all required fields");
    }
    if (!empty($_FILES['template_file']['tmp_name'])) {
      $err = validate_file('template_file');
      if ($err) {
        redirect("edit_problem", ["id" => $id], $err);
      }
      $stmt = $db->prepare("UPDATE problems SET title = :title, statement = :statement, template = :template WHERE id = :id");
      $stmt->bindValue(":template", file_get_contents($_FILES['template_file']['tmp_name']));
    } else {
      $stmt = $db->prepare("UPDATE problems SET title = :title, statement = :statement WHERE id = :id");
    }
    $stmt->bindValue(":title", $title);
    $stmt->bindValue(":statement", $statement);
    $stmt->bindValue(":id", $id);
    $stmt->execute();
    redirect("view_problem", ["id" => $id]);
  }

  elseif ($action === "rejudge" && $_SESSION['username'] === "admin") {
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
    $stmt = $db->prepare("SELECT p.*, (SELECT COUNT(DISTINCT user) FROM submissions WHERE problem = p.id AND status = 'PASSED') as solves, EXISTS(SELECT 1 FROM submissions WHERE problem = p.id AND user = :user AND status = 'PASSED') as is_solved FROM problems p ORDER BY p.id DESC");
    $stmt->bindValue(":user", $_SESSION['user_id'] ?? null);
    $stmt->execute();
    $problems = $stmt->fetchAll();
    include "templates/view_problems.php";
  }

  elseif ($action === "scoreboard") {
    $stmt = $db->query("SELECT u.username, COUNT(DISTINCT s.problem) as solved, RANK() OVER (ORDER BY COUNT(DISTINCT s.problem) DESC) as rank FROM users u LEFT JOIN submissions s ON u.id = s.user AND s.status = 'PASSED' GROUP BY u.id ORDER BY solved DESC");
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
    $user_submissions = [];
    $all_submissions = [];
    $is_admin = ($_SESSION['username'] ?? '') === 'admin';
    $is_xyzzy = ($problem['title'] === 'xyzzy');

    if (isset($_SESSION['user_id'])) {
      $stmt = $db->prepare("SELECT * FROM submissions WHERE problem = :problem AND user = :user ORDER BY id DESC");
      $stmt->bindValue(":problem", $id);
      $stmt->bindValue(":user", $_SESSION['user_id']);
      $stmt->execute();
      $user_submissions = $stmt->fetchAll();

      $stmt = $db->prepare("SELECT EXISTS(SELECT 1 FROM submissions WHERE problem = :problem AND user = :user AND status = 'PASSED')");
      $stmt->bindValue(":problem", $id);
      $stmt->bindValue(":user", $_SESSION['user_id']);
      $stmt->execute();
      $is_solved = (bool)$stmt->fetchColumn();
      if ($is_admin || (!$is_xyzzy && $is_solved)) {
        $stmt = $db->prepare("SELECT s.*, u.username FROM submissions s JOIN users u ON s.user = u.id WHERE s.problem = :problem ORDER BY s.id DESC");
        $stmt->bindValue(":problem", $id);
        $stmt->execute();
        $all_submissions = $stmt->fetchAll();
      }
    }
    include "templates/view_problem.php";
  }

  elseif ($action === "view_submission") {
    $id = $_GET['id'] ?? 0;
    if (!isset($_SESSION['user_id'])) {
      redirect("login");
    }
    $stmt = $db->prepare("SELECT s.*, p.title, u.username FROM submissions s JOIN problems p ON s.problem = p.id JOIN users u ON s.user = u.id WHERE s.id = :id");
    $stmt->bindValue(":id", $id);
    $stmt->execute();
    $submission = $stmt->fetch();
    if (!$submission) {
      redirect("view_problems", [], "Not found");
    }
    $stmt = $db->prepare("SELECT EXISTS(SELECT 1 FROM submissions WHERE problem = :problem AND user = :user AND status = 'PASSED')");
    $stmt->bindValue(":problem", $submission['problem']);
    $stmt->bindValue(":user", $_SESSION['user_id']);
    $stmt->execute();
    $is_solved = (bool)$stmt->fetchColumn();
    $is_owner = $submission['user'] === $_SESSION['user_id'];
    $is_admin = ($_SESSION['username'] ?? '') === 'admin';
    if ($is_owner || $is_admin || (!$is_xyzzy && $is_solved)) {
      include "templates/view_submission.php";
    } else {
      redirect("view_problem", ["id" => $submission['problem']], "Not allowed");
    }
  }

  elseif ($action === "add_problem" && $_SESSION['username'] === "admin") {
    include "templates/add_problem.php";
  }

  elseif ($action === "edit_problem" && $_SESSION['username'] === "admin") {
    $id = $_GET['id'] ?? 0;
    $stmt = $db->prepare("SELECT * FROM problems WHERE id = :id");
    $stmt->bindValue(":id", $id);
    $stmt->execute();
    $problem = $stmt->fetch();
    if (!$problem) {
      redirect("view_problems", [], "Not found");
    }
    include "templates/edit_problem.php";
  }
  include "templates/footer.php";
}
?>
