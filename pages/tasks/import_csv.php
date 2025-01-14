<?php
// File: import_csv.php
session_start();
// require '../../includes/auth_check.php';

include '../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $tmpName = $_FILES['csv_file']['tmp_name'];
    if (!is_uploaded_file($tmpName)) {
        die("No file uploaded or invalid file.");
    }

    $handle = fopen($tmpName, 'r');
    if (!$handle) {
        die("Error opening file.");
    }

    // 1) Optionally, read header row
    // $header = fgetcsv($handle); 

    // 2) Read each subsequent row
    while (($data = fgetcsv($handle)) !== false) {
        // Expect columns:
        // project_id, project_title, project_desc, project_status, project_hours,
        // task_id, task_title, task_parent, task_system_est_hours, task_status
        if (count($data) < 10) {
            // skip or handle error
            continue;
        }
        list($pid, $ptitle, $pdesc, $pstat, $phours,
             $tid, $ttitle, $tpar, $tsys, $tstat) = $data;

        // 3) Insert/update project
        // If $pid is empty, we create a new project. If it's numeric, we might update or skip
        if (!empty($ptitle)) {
            // A) Check if project_id already exists (if you want an "upsert" approach)
            if (!empty($pid)) {
                // see if there's a project with that ID
                $checkP = $conn->prepare("SELECT id FROM projects WHERE id=?");
                $checkP->bind_param("i", $pid);
                $checkP->execute();
                $resP = $checkP->get_result();
                if ($resP->num_rows > 0) {
                    // update
                    $updP = $conn->prepare("
                      UPDATE projects
                      SET title=?, description=?, status=?, hours_estimated=?
                      WHERE id=?
                    ");
                    $updP->bind_param("sssii", $ptitle, $pdesc, $pstat, $phours, $pid);
                    $updP->execute();
                    $updP->close();
                } else {
                    // insert new with that ID? MySQL won't let you specify your own auto_inc by default
                    // You might skip or do an insert ignoring ID. Let's do a fresh insert ignoring $pid:
                    $insP = $conn->prepare("
                      INSERT INTO projects (title, description, status, hours_estimated)
                      VALUES (?, ?, ?, ?)
                    ");
                    $insP->bind_param("sssi", $ptitle, $pdesc, $pstat, $phours);
                    $insP->execute();
                    $pid = $insP->insert_id; // new ID
                    $insP->close();
                }
                $checkP->close();
            } else {
                // insert new ignoring $pid
                $insP = $conn->prepare("
                  INSERT INTO projects (title, description, status, hours_estimated)
                  VALUES (?, ?, ?, ?)
                ");
                $insP->bind_param("sssi", $ptitle, $pdesc, $pstat, $phours);
                $insP->execute();
                $pid = $insP->insert_id;
                $insP->close();
            }
        }

        // 4) Insert/update tasks similarly
        if (!empty($ttitle)) {
            if (!empty($tid)) {
                // check if a task with that ID
                $checkT = $conn->prepare("SELECT id FROM tasks WHERE id=?");
                $checkT->bind_param("i", $tid);
                $checkT->execute();
                $resT = $checkT->get_result();
                if ($resT->num_rows > 0) {
                    // update
                    $updT = $conn->prepare("
                      UPDATE tasks
                      SET title=?, description='', parent_id=?, system_estimated_hours=?
                      WHERE id=?
                    ");
                    $sysEst = (int)$tsys;
                    $parentId = !empty($tpar) ? (int)$tpar : null;
                    $updT->bind_param("siii", $ttitle, $parentId, $sysEst, $tid);
                    $updT->execute();
                    $updT->close();
                } else {
                    // insert ignoring the provided $tid? or attempt a forced ID
                    $sysEst = (int)$tsys;
                    $parentId = !empty($tpar) ? (int)$tpar : null;
                    $insT = $conn->prepare("
                      INSERT INTO tasks (title, description, parent_id, system_estimated_hours)
                      VALUES (?, '', ?, ?)
                    ");
                    $insT->bind_param("sii", $ttitle, $parentId, $sysEst);
                    $insT->execute();
                    $tid = $insT->insert_id;
                    $insT->close();
                }
                $checkT->close();
            } else {
                // insert new
                $sysEst = (int)$tsys;
                $parentId = !empty($tpar) ? (int)$tpar : null;
                $insT = $conn->prepare("
                  INSERT INTO tasks (title, description, parent_id, system_estimated_hours)
                  VALUES (?, '', ?, ?)
                ");
                $insT->bind_param("sii", $ttitle, $parentId, $sysEst);
                $insT->execute();
                $tid = $insT->insert_id;
                $insT->close();
            }

            // link the newly inserted/updated task to the project => user_project_task or something
            // if your logic is that each task belongs to that project, do:
            $userId = 1; // or from session, or CSV
            // upsert into user_project_task? skipping details for brevity
        }
    }

    fclose($handle);

    echo "<p>Import complete!</p>";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Import CSV</title>
</head>
<body>
<h1>Import Projects + Tasks from CSV</h1>
<form method="post" enctype="multipart/form-data">
    <p>Select CSV file:</p>
    <input type="file" name="csv_file" accept=".csv">
    <button type="submit">Import</button>
</form>
</body>
</html>
