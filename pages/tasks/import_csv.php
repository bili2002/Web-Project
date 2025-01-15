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

    // If you have a header row, skip it:
    // $header = fgetcsv($handle);

    // read each line
    while (($data = fgetcsv($handle)) !== false) {
        // Expecting 7 columns:
        // project_title, project_desc, project_status,
        // task_title, task_desc, parent_task_title, hours
        if (count($data) < 7) {
            continue; // skip
        }

        list($pTitle, $pDesc, $pStatus,
             $tTitle, $tDesc, $parentTitle, 
             $hours
        ) = $data;

        // ========== 1) Find or create project by its title ==========
        $pTitle = trim($pTitle);
        if (!empty($pTitle)) {
            // check if project exists by title
            $selP = $conn->prepare("SELECT id FROM projects WHERE title=? LIMIT 1");
            $selP->bind_param("s", $pTitle);
            $selP->execute();
            $resP = $selP->get_result();
            if ($rowP = $resP->fetch_assoc()) {
                $projectId = $rowP['id'];
            } else {
                // create new project ignoring numeric ID
                $insP = $conn->prepare("
                  INSERT INTO projects (title, description, status)
                  VALUES (?, ?, ?)
                ");
                $insP->bind_param("sss", $pTitle, $pDesc, $pStatus);
                $insP->execute();
                $projectId = $insP->insert_id;
                $insP->close();
            }
            $selP->close();

            // ========== 2) Handle parent task if needed ==========
            $parentId = null;
            $parentTitle = trim($parentTitle);
            if (!empty($parentTitle)) {
                // see if there's a task with this title for the same project
                // we look in tasks t plus user_project_task upt
                // to ensure it's linked to that project
                $selParent = $conn->prepare("
                  SELECT t.id 
                  FROM tasks t
                  JOIN user_project_task upt ON t.id=upt.task_id
                  WHERE t.title=? AND upt.project_id=?
                  LIMIT 1
                ");
                $selParent->bind_param("si", $parentTitle, $projectId);
                $selParent->execute();
                $resPar = $selParent->get_result();
                if ($rowPar = $resPar->fetch_assoc()) {
                    $parentId = $rowPar['id'];
                } 
                $selParent->close();
            }

            // ========== 3) Find or create the child task by $tTitle ==========
            $tTitle = trim($tTitle);
            if (!empty($tTitle)) {
                // see if a task with tTitle exists in this project
                $selT = $conn->prepare("
                  SELECT t.id 
                  FROM tasks t
                  JOIN user_project_task upt ON t.id=upt.task_id
                  WHERE t.title=? AND upt.project_id=?
                  LIMIT 1
                ");
                $selT->bind_param("si", $tTitle, $projectId);
                $selT->execute();
                $resT = $selT->get_result();
                if ($rowT = $resT->fetch_assoc()) {
                    $taskId = $rowT['id'];
                } else {
                    // create new task
                    $insT = $conn->prepare("
                      INSERT INTO tasks (title, description, parent_id, system_estimated_hours)
                      VALUES (?, ?, ?, ?)
                    ");
                    $tparent = $parentId !== null ? $parentId : null;
                    $sysEst = (int)$hours; // we put 'hours' in system_est here, naive
                    $insT->bind_param("ssii", $tTitle, $tDesc, $tparent, $sysEst);
                    $insT->execute();
                    $taskId = $insT->insert_id;
                    $insT->close();

                    // link new task to the project
                    $insUPT = $conn->prepare("
                      INSERT INTO user_project_task
                      (project_id, task_id, team_estimated_hours, actual_hours, status)
                      VALUES (?, ?, ?, 0, 'pending')
                    ");
                    $insUPT->bind_param("iii", $projectId, $taskId, $sysEst);
                    $insUPT->execute();
                    $insUPT->close();
                }
                $selT->close();
            }
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
    <title>Import Projects & Tasks (No IDs)</title>
</head>
<body>
<h1>Import from CSV (No IDs)</h1>
<p>CSV columns in order:
<pre>
project_title,project_desc,project_status,
task_title,task_desc,parent_task_title,hours
</pre>
</p>
<form method="post" enctype="multipart/form-data">
    <p>Select CSV file:</p>
    <input type="file" name="csv_file" accept=".csv">
    <button type="submit">Import</button>
</form>
</body>
</html>
