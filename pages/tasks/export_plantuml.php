<?php
// File: export_plantuml.php
session_start();
// Optional: if you need to restrict access
// require '../../includes/auth_check.php';
include '../../includes/db.php';

/**
 * 1) Fetch tasks with parent_id
 */
$tasks = [];
$sql = "
    SELECT t.id AS task_id,
           t.parent_id,
           t.title,
           upt.status
    FROM tasks t
    JOIN user_project_task upt ON t.id = upt.task_id
    -- Optionally filter by project, or show all
    -- WHERE upt.project_id = ?
    ORDER BY t.id ASC
";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    $tasks[] = $row;
}

/**
 * 2) Build a map of tasksByParent for DFS
 */
$tasksByParent = [];
$taskTitles    = []; // store each task's title for quick lookup
foreach ($tasks as $task) {
    $parent = $task['parent_id'] ?? 0;
    $tasksByParent[$parent][] = $task;
    $taskTitles[$task['task_id']] = $task['title'];
}
// Sort children if you want a stable order
foreach ($tasksByParent as &$childArr) {
    usort($childArr, function($a, $b){ 
        return $a['task_id'] - $b['task_id'];
    });
}
unset($childArr);

/**
 * 3) Build the PlantUML code in an array
 */
$umlLines   = ["@startuml", "' This diagram shows task/subtask relationships"];

/**
 * Recursive function to walk DFS
 */
function exportPlantUmlDFS($parentId, $tasksByParent, $taskTitles) {
    if (empty($tasksByParent[$parentId])) {
        return;
    }
    foreach ($tasksByParent[$parentId] as $child) {
        global $umlLines;
        $childId    = $child['task_id'];
        $childTitle = $child['title'];

        // If parentId != 0, connect parent→child
        if ($parentId != 0) {
            // "ParentTitle" --> "ChildTitle"
            $umlLines[] = '"' . addslashes($taskTitles[$parentId]) . '" --> "' 
                        . addslashes($childTitle) . '"';
        }
        // Recurse
        exportPlantUmlDFS($childId, $tasksByParent, $taskTitles);
    }
}

// 4) Start from parentId=0 => top-level tasks
exportPlantUmlDFS(0, $tasksByParent, $taskTitles);

// End
$umlLines[] = "@enduml";

// Combine into one string
$plantUmlCode = implode("\n", $umlLines);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Tasks → PlantUML Export</title>
    <meta charset="utf-8">
</head>
<body>
<h1>Tasks → PlantUML Diagram</h1>

<p>This page generates a PlantUML diagram for tasks/subtasks. Below is the raw code, and then the rendered diagram.</p>

<!-- Show the PlantUML code in a textarea for debugging -->
<textarea style="width:100%;height:200px;">
<?php echo htmlspecialchars($plantUmlCode); ?>
</textarea>

<div id="umlDiagram" style="margin-top:20px; text-align:center;">
    Loading diagram...
</div>

<!-- 1) Include the plantuml-encoder library (CDN or local) -->
<script src="https://cdn.jsdelivr.net/npm/plantuml-encoder/dist/plantuml-encoder.min.js"></script>

<script>
/**
 * 2) We have the raw $plantUmlCode from PHP.
 *    We'll pass it to the library to create an encoded URL,
 *    then we display the resulting <img>.
 */
(function(){
    const code = <?php echo json_encode($plantUmlCode); ?>; // safer JSON encode
    // Encode using plantuml-encoder
    const encoded = window.plantumlEncoder.encode(code);

    // Create the full URL for the public PlantUML server
    const url = "https://www.plantuml.com/plantuml/svg/" + encoded;

    // Insert an <img> into #umlDiagram
    const diagramDiv = document.getElementById("umlDiagram");
    diagramDiv.innerHTML = "";
    const img = document.createElement("img");
    img.src = url;
    img.alt = "PlantUML Diagram";
    img.style.maxWidth = "100%";
    diagramDiv.appendChild(img);
})();
</script>
</body>
</html>
