<?php
// File: export_csv.php
session_start();
// Optional: if you only allow certain roles
// require '../../includes/auth_check.php';

include '../../includes/db.php';

// 1) Set headers for CSV download
header("Content-Type: text/csv");
header("Content-Disposition: attachment; filename=projects_tasks_export.csv");

// 2) Output column headers
echo "project_id,project_title,project_description,project_status,project_hours," .
     "task_id,task_title,task_parent,task_system_estimated_hours,task_status\n";

// 3) Fetch all projects joined with tasks if you want them in one row
//    Or do separate queries. Here we do a left join approach:
$sql = "
  SELECT 
    p.id AS project_id,
    p.title AS project_title,
    p.description AS project_desc,
    p.status AS project_status,
    p.hours_estimated AS project_hours,

    t.id AS task_id,
    t.title AS task_title,
    t.parent_id AS task_parent,
    t.system_estimated_hours AS task_sys_est,
    
    -- The status for tasks might be in user_project_task or we store in tasks table
    -- If you do user_project_task, you'd have to do a more complex join
    -- For simplicity, assume each task has a column 'status' if you want
    -- or skip it. Let's assume we skip or fill a placeholder.

    '' AS task_status  -- placeholder if tasks don't store 'status' directly
  FROM projects p
  LEFT JOIN tasks t ON (t.parent_id IS NULL AND t.title = '??') 
  -- Or better: if you have a link table for tasks -> project, you'd do
  -- JOIN user_project_task upt ON (p.id = upt.project_id AND t.id = upt.task_id)
  -- but let's keep it simple and just do a naive approach
  ORDER BY p.id, t.id
";

// This example won't perfectly reflect your link structure, 
// you must adapt the join to your actual table relationships!

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        // 4) Extract columns
        $pid    = $row['project_id'] ?? '';
        $ptitle = $row['project_title'] ?? '';
        $pdesc  = $row['project_desc'] ?? '';
        $pstat  = $row['project_status'] ?? '';
        $phours = $row['project_hours'] ?? 0;

        $tid    = $row['task_id'] ?? '';
        $ttitle = $row['task_title'] ?? '';
        $tpar   = $row['task_parent'] ?? '';
        $tsys   = $row['task_sys_est'] ?? 0;
        $tstat  = $row['task_status'] ?? '';

        // 5) Output CSV row (escape quotes, etc.)
        // Typically you'd wrap each field in quotes or do str_replace on them if needed
        echo "$pid," .
             "\"" . str_replace("\"", "\"\"", $ptitle) . "\"," .
             "\"" . str_replace("\"", "\"\"", $pdesc) . "\"," .
             "\"" . str_replace("\"", "\"\"", $pstat) . "\"," .
             "$phours," .
             "$tid," .
             "\"" . str_replace("\"", "\"\"", $ttitle) . "\"," .
             "$tpar," .
             "$tsys," .
             "\"" . str_replace("\"", "\"\"", $tstat) . "\"" .
             "\n";
    }
}
exit; // end
