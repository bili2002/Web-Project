<?php
// File: export_csv.php
session_start();
// Optional: require '../../includes/auth_check.php';
include '../../includes/db.php';

// 1) Set CSV headers
header("Content-Type: text/csv");
header("Content-Disposition: attachment; filename=projects_tasks_export.csv");

// 2) Column headers (no IDs, just names and fields):
echo "project_title,project_desc,project_status," 
   . "task_title,task_desc,parent_task_title,hours\n";

/**
 * 3) Query:
 *    - We skip numeric IDs and project hours. 
 *    - We do a LEFT JOIN on tasks' parent to get the parent's title.
 *    - Use CASE for done vs not-done hours.
 */
$sql = "
  SELECT
    p.title AS project_title,
    p.description AS project_desc,
    p.status AS project_status,
    
    t.title AS task_title,
    t.description AS task_desc,
    parent.title AS parent_task_title,

    CASE WHEN upt.status = 'done'
         THEN upt.actual_hours
         ELSE upt.team_estimated_hours
    END AS hours

  FROM user_project_task upt
  JOIN projects p ON upt.project_id = p.id
  JOIN tasks t    ON upt.task_id    = t.id
  LEFT JOIN tasks parent ON t.parent_id = parent.id

  ORDER BY p.title, t.id
";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $pTitle    = $row['project_title']      ?? '';
        $pDesc     = $row['project_desc']       ?? '';
        $pStatus   = $row['project_status']     ?? 'open';

        $tTitle    = $row['task_title']         ?? '';
        $tDesc     = $row['task_desc']          ?? '';
        $parentT   = $row['parent_task_title']  ?? '';
        $hrs       = $row['hours']              ?? 0;

        // 4) Output row
        // Escape quotes by doubling them
        echo '"' . str_replace('"','""', $pTitle)   . '",' 
           . '"' . str_replace('"','""', $pDesc)    . '",' 
           . '"' . str_replace('"','""', $pStatus)  . '",'
           . '"' . str_replace('"','""', $tTitle)   . '",' 
           . '"' . str_replace('"','""', $tDesc)    . '",' 
           . '"' . str_replace('"','""', $parentT)  . '",'
           . $hrs 
           . "\n";
    }
}
exit; // done
