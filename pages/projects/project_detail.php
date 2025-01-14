<?php
// File: pages/projects/project_detail.php
session_start();
require '../../includes/auth_check.php';
include '../../includes/db.php';

// 1) Get the requested project ID
if (!isset($_GET['id'])) {
    die("No project specified.");
}
$projectId = (int)$_GET['id'];

// Fetch project info
$stmt = $conn->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->bind_param("i", $projectId);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    die("Project not found.");
}
$project = $res->fetch_assoc();
$stmt->close();

// 2) Handle Update Project (edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_project') {
    $newTitle        = $_POST['title'] ?? '';
    $newDescription  = $_POST['description'] ?? '';
    $newHours        = (int)($_POST['hours_estimated'] ?? 0);
    $newStatus       = $_POST['status'] ?? 'open';

    if (!empty($newTitle)) {
        $upd = $conn->prepare("
            UPDATE projects
            SET title = ?, description = ?, hours_estimated = ?, status = ?
            WHERE id = ?
        ");
        $upd->bind_param("ssisi", $newTitle, $newDescription, $newHours, $newStatus, $projectId);

        if ($upd->execute()) {
            $successMsg = "Project updated successfully!";
            // Update our $project array
            $project['title']          = $newTitle;
            $project['description']    = $newDescription;
            $project['hours_estimated'] = $newHours;
            $project['status']        = $newStatus;
        } else {
            $errorMsg = "Error updating project: " . $upd->error;
        }
        $upd->close();
    } else {
        $errorMsg = "Project title cannot be empty.";
    }
}

// 3) Handle Delete Project
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $delStmt = $conn->prepare("DELETE FROM projects WHERE id = ?");
    $delStmt->bind_param("i", $projectId);
    if ($delStmt->execute()) {
        // If successful, redirect to list of projects
        header("Location: projects.php?msg=ProjectDeleted");
        exit;
    } else {
        $errorMsg = "Error deleting project: " . $delStmt->error;
    }
    $delStmt->close();
}

// 4) List tasks for this project via user_project_task
//    A single row in user_project_task means "Task #X is assigned to Project #Y"
$sqlTasks = "
    SELECT
        upt.id AS link_id,
        upt.status AS assignment_status,
        upt.team_estimated_hours,
        upt.actual_hours,
        t.id AS task_id,
        t.title AS task_title,
        t.description AS task_description,
        t.system_estimated_hours
    FROM user_project_task upt
    JOIN tasks t ON upt.task_id = t.id
    WHERE upt.project_id = ?
    ORDER BY upt.created_at ASC
";
$stmtTasks = $conn->prepare($sqlTasks);
$stmtTasks->bind_param("i", $projectId);
$stmtTasks->execute();
$tasksResult = $stmtTasks->get_result();
$stmtTasks->close();

$linkedTasks = [];
while ($row = $tasksResult->fetch_assoc()) {
    $linkedTasks[] = $row;
}

// 5) Handle creating a new Task *and* linking it to this project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_task') {
    $taskTitle             = $_POST['task_title'] ?? '';
    $taskDescription       = $_POST['task_description'] ?? '';
    $taskSystemEstHours    = (int)($_POST['system_estimated_hours'] ?? 0);
    // Optional: let them specify "team_estimated_hours," or we can default to 0
    $teamEstHours          = (int)($_POST['team_estimated_hours'] ?? 0);

    if (!empty($taskTitle)) {
        // Step 1: create a new row in `tasks`
        $insTask = $conn->prepare("
            INSERT INTO tasks (title, description, system_estimated_hours)
            VALUES (?, ?, ?)
        ");
        $insTask->bind_param("ssi", $taskTitle, $taskDescription, $taskSystemEstHours);

        if ($insTask->execute()) {
            $newTaskId = $insTask->insert_id; // newly created task
            $insTask->close();

            // Step 2: link that task to this project in user_project_task
            // We'll just assume it belongs to the "current user" or "no user" for now.
            // If you want to assign it to a certain user, set user_id accordingly.
            $insLink = $conn->prepare("
                INSERT INTO user_project_task 
                (project_id, task_id, team_estimated_hours, status)
                VALUES (?, ?, ?, 'pending')
            ");
            $insLink->bind_param("iii", $projectId, $newTaskId, $teamEstHours);
            if ($insLink->execute()) {
                $successMsg = "New task created and linked to this project!";
                // Refresh the page so we see the newly added task in the list
                header("Location: project_detail.php?id=$projectId");
                exit;
            } else {
                $errorMsg = "Error linking task to project: " . $insLink->error;
            }
            $insLink->close();
        } else {
            $errorMsg = "Error creating task: " . $insTask->error;
            $insTask->close();
        }
    } else {
        $errorMsg = "Task title cannot be empty.";
    }
}

// If we created or updated something AFTER we fetched tasks, we won't see the changes
// in $linkedTasks until we re-fetch them. For simplicity, we won't re-fetch again here.
// Instead, we used a redirect above for "create_task."

?>
<!DOCTYPE html>
<html>
<head>
    <title>Project Detail</title>
</head>
<body>
<h1>Project Detail</h1>

<?php
if (!empty($errorMsg)) {
    echo "<p style='color:red;'>$errorMsg</p>";
}
if (!empty($successMsg)) {
    echo "<p style='color:green;'>$successMsg</p>";
}
?>

<p>
    <strong>ID:</strong> <?php echo $project['id']; ?><br>
    <strong>Title:</strong> <?php echo htmlspecialchars($project['title']); ?><br>
    <strong>Status:</strong> <?php echo htmlspecialchars($project['status']); ?><br>
    <strong>Hours Estimated:</strong> <?php echo (int)$project['hours_estimated']; ?><br>
    <strong>Description:</strong><br>
    <?php echo nl2br(htmlspecialchars($project['description'])); ?>
</p>

<hr>

<!-- Edit project form -->
<h3>Edit Project</h3>
<form method="post">
    <input type="hidden" name="action" value="edit_project">

    <label>Title:</label><br>
    <input type="text" name="title" value="<?php echo htmlspecialchars($project['title']); ?>"><br><br>

    <label>Description:</label><br>
    <textarea name="description"><?php echo htmlspecialchars($project['description']); ?></textarea><br><br>

    <label>Estimated Hours:</label><br>
    <input type="number" name="hours_estimated" 
           value="<?php echo (int)$project['hours_estimated']; ?>"><br><br>

    <label>Status:</label><br>
    <select name="status">
        <option value="open" <?php if ($project['status'] === 'open') echo 'selected'; ?>>Open</option>
        <option value="in progress" <?php if ($project['status'] === 'in progress') echo 'selected'; ?>>In Progress</option>
        <option value="completed" <?php if ($project['status'] === 'completed') echo 'selected'; ?>>Completed</option>
    </select><br><br>

    <button type="submit">Update Project</button>
</form>

<p>
    <!-- Delete link -->
    <a href="?id=<?php echo $projectId; ?>&action=delete"
       onclick="return confirm('Are you sure you want to delete this project?')">
       Delete Project
    </a>
</p>

<hr>
<h2>Tasks Linked to This Project</h2>

<?php if (count($linkedTasks) > 0): ?>
    <ul>
    <?php foreach ($linkedTasks as $lt): ?>
        <li>
            <strong><?php echo htmlspecialchars($lt['task_title']); ?></strong>
            <br>
            System-Estimated: <?php echo (int)$lt['system_estimated_hours']; ?>h
            <br>
            Team-Estimated: <?php echo (int)$lt['team_estimated_hours']; ?>h
            <br>
            Current Assignment Status: <?php echo htmlspecialchars($lt['assignment_status']); ?>
            <br>
            <em><?php echo nl2br(htmlspecialchars($lt['task_description'])); ?></em>

            <hr style="border:none;border-top:1px dashed #ccc;">
        </li>
    <?php endforeach; ?>
    </ul>
<?php else: ?>
    <p>No tasks linked to this project yet.</p>
<?php endif; ?>

<h3>Create & Link a New Task to This Project</h3>
<form method="post">
    <input type="hidden" name="action" value="create_task">

    <label>Task Title:</label><br>
    <input type="text" name="task_title" required><br><br>

    <label>Description:</label><br>
    <textarea name="task_description"></textarea><br><br>

    <label>System Estimated Hours:</label><br>
    <input type="number" name="system_estimated_hours" min="0" value="0"><br><br>

    <label>Team Estimated Hours:</label><br>
    <input type="number" name="team_estimated_hours" min="0" value="0"><br><br>

    <button type="submit">Create & Link Task</button>
</form>

<br>
<p><a href="projects.php">Back to Projects</a></p>
</body>
</html>
