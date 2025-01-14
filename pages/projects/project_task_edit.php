<?php
// File: pages/projects/project_task_edit.php
session_start();
require '../../includes/auth_check.php';
include '../../includes/db.php';

// 1. Check link_id from user_project_task
if (!isset($_GET['link_id'])) {
    die("No link specified.");
}
$linkId = (int)$_GET['link_id'];

// 2. Fetch the link row (and the associated task info)
$sql = "
    SELECT 
       upt.id AS link_id,
       upt.project_id,
       upt.team_estimated_hours,
       upt.actual_hours,
       upt.status,
       t.title AS task_title,
       t.description AS task_description
    FROM user_project_task upt
    JOIN tasks t ON upt.task_id = t.id
    WHERE upt.id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $linkId);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    die("Link not found.");
}
$linkRow = $res->fetch_assoc();
$stmt->close();

// (Optional) check if the current user is allowed to edit this link
// e.g. if they're the team leader or the assigned user, or have 'admin' role. 
// We'll skip that logic here for brevity.

// 3. Handle POST -> update user_project_task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $newTeamEst = (int)($_POST['team_estimated_hours'] ?? 0);
    $newActual  = (int)($_POST['actual_hours'] ?? 0);
    $newStatus  = $_POST['status'] ?? 'pending';

    // We update user_project_task’s relevant fields
    $updStmt = $conn->prepare("
        UPDATE user_project_task
        SET team_estimated_hours = ?, actual_hours = ?, status = ?
        WHERE id = ?
    ");
    $updStmt->bind_param("iisi", $newTeamEst, $newActual, $newStatus, $linkId);

    if ($updStmt->execute()) {
        $successMsg = "Task assignment updated!";
        // Update local data
        $linkRow['team_estimated_hours'] = $newTeamEst;
        $linkRow['actual_hours']         = $newActual;
        $linkRow['status']              = $newStatus;
    } else {
        $errorMsg = "Error updating: " . $updStmt->error;
    }
    $updStmt->close();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Task Assignment</title>
</head>
<body>
<h1>Edit Task Assignment</h1>

<?php
if (!empty($errorMsg)) {
    echo "<p style='color:red;'>$errorMsg</p>";
}
if (!empty($successMsg)) {
    echo "<p style='color:green;'>$successMsg</p>";
}
?>

<!-- Display read-only info about the task -->
<p>
    <strong>Task:</strong> <?php echo htmlspecialchars($linkRow['task_title']); ?><br>
    <strong>Description:</strong><br>
    <?php echo nl2br(htmlspecialchars($linkRow['task_description'])); ?><br>
</p>

<hr>

<!-- Edit team_estimated_hours, actual_hours, status -->
<form method="post">
    <input type="hidden" name="action" value="update">

    <label>Team Estimated Hours:</label><br>
    <input type="number" name="team_estimated_hours" min="0"
           value="<?php echo (int)$linkRow['team_estimated_hours']; ?>"><br><br>

    <label>Actual Hours:</label><br>
    <input type="number" name="actual_hours" min="0"
           value="<?php echo (int)$linkRow['actual_hours']; ?>"><br><br>

    <label>Status:</label><br>
    <select name="status">
        <option value="pending" <?php if ($linkRow['status'] === 'pending') echo 'selected'; ?>>Pending</option>
        <option value="in progress" <?php if ($linkRow['status'] === 'in progress') echo 'selected'; ?>>In Progress</option>
        <option value="done" <?php if ($linkRow['status'] === 'done') echo 'selected'; ?>>Done</option>
    </select><br><br>

    <button type="submit">Update</button>
</form>

<p>
    <!-- Link back to the manage page for the project -->
    <a href="project_manage.php?id=<?php echo $linkRow['project_id']; ?>">
        Back to Project
    </a>
</p>
</body>
</html>
