<?php

// Load .env
$env_file = __DIR__ . '/.env';
if (!file_exists($env_file)) {
    die('Missing .env file');
}

$env = parse_ini_file($env_file, false, INI_SCANNER_RAW);
if ($env === false || empty($env['DB_PATH'])) {
    die('DB_PATH not configured in .env');
}
if (empty($env['CHECKER_FILES'])) {
    die('CHECKER_FILES not configured in .env');
}
if (empty($env['LEAN_TOOLCHAIN'])) {
    die('LEAN_TOOLCHAIN not configured in .env');
}
if (empty($env['CHECKER_PATH'])) {
    die('CHECKER_PATH not configured in .env');
}

$db_path = $env['DB_PATH'];
$checkerFiles = $env['CHECKER_FILES'];
$toolchain = $env['LEAN_TOOLCHAIN'];
$checkerPath = $env['CHECKER_PATH'];

$db = new PDO("sqlite:$db_path");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

echo "Worker started...\n";

while (true) {
  $stmt = $db->prepare("SELECT s.*, p.template, p.title, p.answer, a.imports, a.def FROM submissions s JOIN problems p ON s.problem = p.id LEFT JOIN answers a ON p.answer = a.id WHERE s.status = 'PENDING' LIMIT 1");
  $stmt->execute();
  $s = $stmt->fetch();
  if ($s) {
    echo "Processing submission #{$s["id"]}\n";

    $boxId = 0;
    $metaFile = __DIR__ . "/meta.txt";
    $checkerBinary = ".lake/build/bin/check";

    file_put_contents($checkerFiles . "/submission.lean", $s["source"]);
    if ($s['title'] === "xyzzy") {
      $checkerBinary = ".lake/build/bin/xyzzy";
    } else {
      file_put_contents($checkerFiles . "/template.lean", $s["template"]);
    }
    if (isset($s['answer'])) {
      file_put_contents($checkerFiles . "/answer.lean", $s["imports"] . "\n" . $s["def"]);
      $checkerBinary = ".lake/build/bin/check_with_answer";
    }

    $boxPath = trim(shell_exec("isolate --box-id=$boxId --cg --init"));

    $leanPath = "/checker/.lake/packages/batteries/.lake/build/lib/lean:/checker/.lake/packages/Qq/.lake/build/lib/lean:/checker/.lake/packages/aesop/.lake/build/lib/lean:/checker/.lake/packages/proofwidgets/.lake/build/lib/lean:/checker/.lake/packages/importGraph/.lake/build/lib/lean:/checker/.lake/packages/mathlib/.lake/build/lib/lean:/checker/.lake/packages/plausible/.lake/build/lib/lean:/checker/.lake/packages/LeanSearchClient/.lake/build/lib/lean:";

    $runCmd = [
      "nice -n 19 isolate --run --cg --box-id=$boxId",
      "--meta=" . escapeshellarg($metaFile),
      "--cg-mem=4194304",
      "--processes=0",
      "--time=300.0",
      "--wall-time=300.0",
      "--dir=/usr/share=/usr/share",
      "--dir=/box=" . escapeshellarg($checkerFiles) . ":rw",
      "--dir=/lean=" . escapeshellarg($toolchain),
      "--dir=/checker=" . escapeshellarg($checkerPath),
      "--env=LEAN_PATH=" . escapeshellarg($leanPath),
      "--env=PATH=/lean/bin:/usr/bin",
      "--chdir=/checker",
      "-- $checkerBinary /box"
    ];

    shell_exec(implode(" ", $runCmd));

    $meta = [];
    if (file_exists($metaFile)) {
      foreach (file($metaFile) as $line) {
        $parts = explode(":", trim($line), 2);
        if (count($parts) === 2) $meta[$parts[0]] = $parts[1];
      }
    }

    $exitCode = (int)($meta["exitcode"] ?? -1);
    $metaStatus = $meta["status"] ?? "OK";

    shell_exec("isolate --box-id=$boxId --cg --cleanup");
    if (file_exists($metaFile)) unlink($metaFile);

    $options = [
      42 => "PASSED",
      44 => "Judge error",
      45 => "Compilation error",
      46 => "Environment error",
      47 => "Forbidden axiom",
      48 => "Template mismatch",
      49 => "Bad answer",
    ];

    if ($metaStatus === "TO") {
      $status = "Time limit exceeded";
    } elseif (isset($meta["cg-oom-killed"]) && $meta["cg-oom-killed"] === "1") {
      $status = "Memory limit exceeded";
    } elseif (isset($options[$exitCode])) {
      $status = $options[$exitCode];
    } else {
      $status = "Unknown error";
    }

    $stmt = $db->prepare("UPDATE submissions SET status = :status WHERE id = :id");
    $stmt->bindValue(":status", $status);
    $stmt->bindValue(":id", $s["id"]);
    $stmt->execute();

    echo "Processed submission #{$s["id"]}: $status\n";
  } else {
    sleep(2);
  }
}
