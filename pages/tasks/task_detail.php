<?php
// File: pages/tasks/task_detail.php
session_start();
require '../../includes/auth_check.php';
include '../../includes/db.php';

if (!isset($_GET['id'])) {
    die("No task specified.");
}
$taskId = (int)$_GET['id'];

// Fetch the task
$stmt = $conn->prepare("SELECT * FROM tasks WHERE id = ?");
$stmt->bind_param("i", $taskId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die("Task not found.");
}
$task = $result->fetch_assoc();
$stmt->close();

// Handle edit form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_task') {
    $newTitle      = $_POST['title'] ?? '';
    $newDesc       = $_POST['description'] ?? '';
    $newSysEst     = (int)($_POST['system_estimated_hours'] ?? 0);

    if (!empty($newTitle)) {
        $upd = $conn->prepare("
            UPDATE tasks 
            SET title = ?, description = ?, system_estimated_hours = ?
            WHERE id = ?
        ");
        $upd->bind_param("ssii", $newTitle, $newDesc, $newSysEst, $taskId);
        if ($upd->execute()) {
            $successMsg = "Task updated successfully!";
            // Update local array
            $task['title']                  = $newTitle;
            $task['description']            = $newDesc;
            $task['system_estimated_hours'] = $newSysEst;
        } else {
            $errorMsg = "Error updating task: " . $upd->error;
        }
        $upd->close();
    } else {
        $errorMsg = "Task title cannot be empty.";
    }
}

// Handle delete
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $del = $conn->prepare("DELETE FROM tasks WHERE id = ?");
    $del->bind_param("i", $taskId);
    if ($del->execute()) {
        // This also removes it from user_project_task due to FK if you used ON DELETE CASCADE
        header("Location: tasks.php?msg=TaskDeleted");
        exit;
    } else {
        $errorMsg = "Error deleting task: " . $del->error;
    }
    $del->close();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Task Detail</title>
</head>
<body>
<h1>Task Detail</h1>

<?php
if (!empty($errorMsg)) {
    echo "<p style='color:red;'>$errorMsg</p>";
}
if (!empty($successMsg)) {
    echo "<p style='color:green;'>$successMsg</p>";
}
?>

<p>
    <strong>Task ID:</strong> <?php echo $task['id']; ?><br>
    <strong>Title:</strong> <?php echo htmlspecialchars($task['title']); ?><br>
    <strong>System Estimated Hours:</strong> <?php echo (int)$task['system_estimated_hours']; ?><br>
    <strong>Description:</strong><br>
    <?php echo nl2br(htmlspecialchars($task['description'])); ?>
</p>

<hr>
<h3>Edit Task</h3>
<form method="post">
    <input type="hidden" name="action" value="edit_task">

    <label>Title:</label><br>
    <input type="text" name="title" value="<?php echo htmlspecialchars($task['title']); ?>" required><br><br>

    <label>Description:</label><br>
    <textarea name="description"><?php echo htmlspecialchars($task['description']); ?></textarea><br><br>

    <label>System Estimated Hours:</label><br>
    <input type="number" name="system_estimated_hours" 
           value="<?php echo (int)$task['system_estimated_hours']; ?>" min="0"><br><br>

    <button type="submit">Update Task</button>
</form>

<p>
    <!-- Delete link -->
    <a href="?id=<?php echo $task['id']; ?>&action=delete"
       onclick="return confirm('Are you sure you want to delete this task?')">
       Delete Task
    </a>
</p>

<p><a href="tasks.php">Back to Tasks</a></p>
</body>
</html>
