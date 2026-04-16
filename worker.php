<?php
$env = parse_ini_file(__DIR__ . '/.env', false, INI_SCANNER_RAW);
$db = new PDO("sqlite:" . $env['DB_PATH']);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$toolchain = $env['LEAN_TOOLCHAIN'];
$checkerFiles = $env['CHECKER_FILES'];
$checkerBins = $env['CHECKER_BINS'];

function writeStatus($db, $id, $status) {
  $stmt = $db->prepare("UPDATE submissions SET status = :status WHERE id = :id");
  $stmt->bindValue(":status", $status);
  $stmt->bindValue(":id", $id);
  $stmt->execute();
}

function parseMeta($file) {
  $meta = [];
  foreach (file($file) as $line) {
    $parts = explode(":", trim($line), 2);
    if (count($parts) === 2) $meta[$parts[0]] = $parts[1];
  }
  return $meta;
}

echo "Worker started...\n";
shell_exec("isolate --cg --init");

while (true) {
  $out = [];
  $err = 0;
  $stmt = $db->prepare("
    SELECT s.*, p.template, p.title, p.answer, a.imports, a.body
    FROM submissions s
    JOIN problems p ON s.problem = p.id
    LEFT JOIN answers a ON p.answer = a.id
    WHERE s.status = 'PENDING'
    LIMIT 1"
  );
  $stmt->execute();
  $row = $stmt->fetch();
  if (!$row) {
    sleep(1);
    continue;
  }

  echo "Processing submission #{$row["id"]}\n";
  $status = "";

  echo "Building submission...\n";
  file_put_contents($checkerFiles . "/CheckerFiles/Submission.lean", $row["source"]);
  $metaFile = __DIR__ . "/meta.txt";
  $cmd = [
    "isolate --cg --run --processes=0 --meta=$metaFile",
    "--cg-mem=4194304",
    "--time=60.0",
    "--wall-time=300.0",
    "--dir=/lean=" . escapeshellarg($toolchain),
    "--dir=/checker-files=" . escapeshellarg($checkerFiles) . ":rw",
    "--chdir=/checker-files",
    "-- /lean/bin/lake build CheckerFiles.Submission:olean"
  ];
  exec(implode(" ", $cmd), $out, $err);
  # echo implode("\n", $out) . "\n";
  $meta = parseMeta($metaFile);
  unlink($metaFile);
  if (isset($meta['status']) && $meta['status'] === "TO") {
    $status = "Time out";
  } elseif (isset($mega["cg-oom-killed"]) && $meta["cg-oom-killed"] === "1") {
    $status = "Out of memory";
  } elseif ($err) {
    $status = "Compilation error";
  }
  if ($status) {
    writeStatus($db, $row['id'], $status);
    echo "Processed submission #{$row["id"]}: $status\n";
    continue;
  }

  if ($row["title"] !== "xyzzy") {
    echo "Building template...\n";
    file_put_contents($checkerFiles . "/CheckerFiles/Template.lean", $row["template"]);
    $cmd = [
      "isolate --cg --run --processes=0",
      "--dir=/lean=" . escapeshellarg($toolchain),
      "--dir=/checker-files=" . escapeshellarg($checkerFiles) . ":rw",
      "--chdir=/checker-files",
      "-- /lean/bin/lake build CheckerFiles.Template:olean"
    ];
    exec(implode(" ", $cmd), $out, $err);
    if ($err) {
      $status = "System error";
      writeStatus($db, $row['id'], $status);
      echo "Processed submission #{$row["id"]}: $status\n";
      continue;
    }

    if ($row['answer']) {
      echo "Building answer...\n";
      file_put_contents(
        $checkerFiles . "/CheckerFiles/Answer.lean",
        $row["imports"] . "\n" . $row["body"]
      );
      $cmd = [
        "isolate --cg --run --processes=0",
        "--dir=/lean=" . escapeshellarg($toolchain),
        "--dir=/checker-files=" . escapeshellarg($checkerFiles) . ":rw",
        "--chdir=/checker-files",
        "-- /lean/bin/lake build CheckerFiles.Answer:olean"
      ];
      exec(implode(" ", $cmd), $out, $err);
      if ($err) {
        $status = "System error";
        writeStatus($db, $row['id'], $status);
        echo "Processed submission #{$row["id"]}: $status\n";
        continue;
      }

      echo "Checking answer...\n";
      $cmd = [
        "isolate --cg --run --processes=0",
        "--dir=/lean=" . escapeshellarg($toolchain),
        "--dir=/bin=" . escapeshellarg($checkerBins),
        "--dir=/checker-files=" . escapeshellarg($checkerFiles) . ":rw",
        "--chdir=/checker-files",
        "-- /lean/bin/lake env /bin/check_answer CheckerFiles.Answer CheckerFiles.Submission"
      ];
      exec(implode(" ", $cmd), $out, $err);
      if ($err) {
        $status = "Bad answer";
        writeStatus($db, $row['id'], $status);
        echo "Processed submission #{$row["id"]}: $status\n";
        continue;
      }
    }

    echo "Checking declarations...\n";
    $cmd = [
      "isolate --cg --run --processes=0",
      "--dir=/lean=" . escapeshellarg($toolchain),
      "--dir=/bin=" . escapeshellarg($checkerBins),
      "--dir=/checker-files=" . escapeshellarg($checkerFiles) . ":rw",
      "--chdir=/checker-files",
      "-- /lean/bin/lake env /bin/check CheckerFiles.Template CheckerFiles.Submission"
    ];
    exec(implode(" ", $cmd), $out, $err);
    if ($err) {
      $status = "Template mismatch";
      writeStatus($db, $row['id'], $status);
      echo "Processed submission #{$row["id"]}: $status\n";
      continue;
    }
  }

  echo "Exporting...\n";
  $cmd = [
    "isolate --cg --run --processes=0",
    "--dir=/lean=" . escapeshellarg($toolchain),
    "--dir=/bin=" . escapeshellarg($checkerBins),
    "--dir=/checker-files=" . escapeshellarg($checkerFiles) . ":rw",
    "--chdir=/checker-files",
    "--stdout=/checker-files/submission.export",
    "-- /lean/bin/lake env /bin/lean4export CheckerFiles.Submission -- solution"
  ];
  exec(implode(" ", $cmd), $out, $err);
  if ($err) {
    $status = "System error ";
    writeStatus($db, $row['id'], $status);
    echo "Processed submission #{$row["id"]}: $status\n";
    continue;
  }

  echo "Type checking with Nanoda...\n";
  $cmd = [
    "isolate --cg --run --processes=0",
    "--dir=/bin=" . escapeshellarg($checkerBins),
    "--dir=/checker-files=" . escapeshellarg($checkerFiles) . ":rw",
    "--chdir=/checker-files",
    "-- /bin/nanoda_bin nanoda.json"
  ];
  exec(implode(" ", $cmd), $out, $err);
  if ($err) {
    $status = "Rejected";
    writeStatus($db, $row['id'], $status);
    echo "Processed submission #{$row["id"]}: $status\n";
    continue;
  }

  $status = "PASSED";
  writeStatus($db, $row['id'], $status);
  echo "Processed submission #{$row["id"]}: $status\n";
}
