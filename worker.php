<?php
$db = new PDO("sqlite:/var/www/database/leanoj.db");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

echo "Worker started...\n";

while (true) {
  $stmt = $db->prepare("SELECT s.*, p.template FROM submissions s JOIN problems p ON s.problem = p.id WHERE s.status = 'PENDING' LIMIT 1");
  $stmt->execute();
  $s = $stmt->fetch();

  if ($s) {
    echo "Processing submission #{$s["id"]}\n";

    $boxId = 0;
    $checkerFiles = __DIR__ . "/checker_files";
    $metaFile = __DIR__ . "/meta.txt";
    
    file_put_contents($checkerFiles . "/template.lean", $s["template"]);
    file_put_contents($checkerFiles . "/submission.lean", $s["source"]);

    $boxPath = trim(shell_exec("isolate --box-id=$boxId --cg --init"));
    
    $toolchain = "/usr/local/elan/toolchains/leanprover--lean4---v4.26.0";
    $checkerPath = __DIR__ . "/checker";
    $leanPath = "/checker/.lake/packages/batteries/.lake/build/lib/lean:/checker/.lake/packages/Qq/.lake/build/lib/lean:/checker/.lake/packages/aesop/.lake/build/lib/lean:/checker/.lake/packages/proofwidgets/.lake/build/lib/lean:/checker/.lake/packages/importGraph/.lake/build/lib/lean:/checker/.lake/packages/mathlib/.lake/build/lib/lean:/checker/.lake/packages/plausible/.lake/build/lib/lean:/checker/.lake/packages/LeanSearchClient/.lake/build/lib/lean:";

    $runCmd = [
      "isolate --run --cg --box-id=$boxId",
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
      "-- .lake/build/bin/check /box template.lean submission.lean"
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
      43 => "Bad template", 
      44 => "Compilation error",
      45 => "Environment error", 
      46 => "Solution not found", 
      47 => "Solution type mismatch", 
      48 => "Forbidden axiom"
    ];

    if ($metaStatus === "TO") {
      $status = "Time limit exceeded";
    } elseif (isset($meta["cg-oom-killed"]) && $meta["cg-oom-killed"] === "1") {
      $status = "Memory limit exceeded";
    } elseif ($exitCode === 42) {
      $status = "PASSED";
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
