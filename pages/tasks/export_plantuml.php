<?php
// File: export_plantuml.php
session_start();
// require '../../includes/auth_check.php'; // if needed
include '../../includes/db.php';

/**
 * 1) Fetch tasks
 */
$tasks = [];
$sql = "
    SELECT t.id AS task_id,
           t.parent_id,
           t.title,
           upt.team_estimated_hours,
           upt.status
    FROM tasks t
    JOIN user_project_task upt ON t.id = upt.task_id
    ORDER BY t.id ASC
";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    $tasks[] = $row;
}

/**
 * 2) Build tasksByParent + store titles
 */
$tasksByParent = [];
$taskTitles    = [];
foreach ($tasks as $task) {
    $parent = $task['parent_id'] ?? 0;
    $tasksByParent[$parent][] = $task;
    $taskTitles[$task['task_id']] = $task['title'];
}
// (Optional) sort each parent's children
foreach ($tasksByParent as &$childArr) {
    usort($childArr, function($a,$b){ return $a['task_id'] - $b['task_id']; });
}
unset($childArr);

/**
 * 3) Build PlantUML lines with styling
 */
$umlLines = [
    "@startuml",
    "skinparam shadowing false",
    "skinparam rectangle {",
    "  BorderColor #666666",
    "  BackgroundColor #ECECEC",
    "  FontColor #333333",
    "}",
    "left to right direction",
];

// We'll first define each task as a rectangle
foreach ($tasks as $t) {
    $tid      = $t['task_id'];
    $title    = addslashes($t['title']);
    $status   = $t['status'];
    $estHours = (int)$t['team_estimated_hours'];

    // map estimated hours to color
    if ($estHours < 2) {
        $color = "#C1E1C1"; // green for less than 2 hours
    } elseif ($estHours <= 4) {
        $color = "#FFD580"; // orange for 3-4 hours
    } elseif ($estHours > 5) {
        $color = "#FF6F61"; // red for more than 5 hours
    } else {
        $color = "#FFFFFF"; // default white
    }

    // create a label
    $label = "$title\\nEst: {$estHours}h";
    $umlLines[] = "rectangle \"$label\" as T$tid $color";
}

// Then define connections
function connectDFS($parentId, $tasksByParent) {
    if (empty($tasksByParent[$parentId])) return;
    foreach ($tasksByParent[$parentId] as $child) {
        $childId = $child['task_id'];
        if ($parentId != 0) {
            global $umlLines;
            $umlLines[] = "T$parentId --> T$childId";
        }
        connectDFS($childId, $tasksByParent);
    }
}
connectDFS(0, $tasksByParent);

$umlLines[] = "@enduml";

// Combine into string
$plantUmlCode = implode("\n", $umlLines);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Tasks → PlantUML Export</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0; 
            padding: 0;
            background-color: #f9f9f9;
            color: #333;
        }
        .container {
            max-width: 960px;
            margin: 40px auto;
            padding: 0 20px;
        }
        h1 {
            text-align: center;
            margin-bottom: 1em;
        }
        .desc {
            background: #fff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 1em;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .desc p {
            margin: 0.5em 0;
            line-height: 1.5;
        }
        textarea {
            width: 100%;
            height: 200px;
            padding: 10px;
            box-sizing: border-box;
            font-family: monospace;
            resize: vertical;
            margin-bottom: 20px;
        }
        #umlDiagram {
            text-align: center;
            background: #fff;
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        #umlDiagram img {
            max-width: 100%;
            margin: 0 auto;
            display: block;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Tasks → PlantUML Diagram</h1>

    <div class="desc">
        <p>This page generates a PlantUML diagram for tasks/subtasks, with improved styling.</p>
        <p>Below is the raw code, followed by the rendered diagram using an external PlantUML server.</p>
    </div>

    <!-- Show the PlantUML code in a textarea for debugging -->
    <textarea readonly><?php echo htmlspecialchars($plantUmlCode); ?></textarea>

    <div id="umlDiagram">
        <p>Loading diagram...</p>
    </div>
</div>

<!-- Include the plantuml-encoder library -->
<script src="https://cdn.jsdelivr.net/npm/plantuml-encoder/dist/plantuml-encoder.min.js"></script>
<script>
(function(){
    const code = <?php echo json_encode($plantUmlCode); ?>;
    const encoded = window.plantumlEncoder.encode(code);
    const url = "https://www.plantuml.com/plantuml/svg/" + encoded;

    const diagramDiv = document.getElementById("umlDiagram");
    diagramDiv.innerHTML = "";
    const img = document.createElement("img");
    img.src = url;
    img.alt = "PlantUML Diagram";
    diagramDiv.appendChild(img);
})();
</script>
</body>
</html>
