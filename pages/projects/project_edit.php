<?php
// File: pages/projects/project_edit.php
session_start();
require '../../includes/auth_check.php';
include '../../includes/db.php';

$projectId = (int)$_GET['id'];
$userId   = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'user';  // could be 'admin', 'team_leader', etc.

// We'll get all teams where this user is either the leader or an admin user
if ($userRole === 'admin') {
  $canManage = true;
} else {
    $sqlTeams = "
        SELECT t.*
        FROM team_members tm
        JOIN teams t ON tm.team_id = t.id
        WHERE tm.user_id = ?
    ";
    $stmtT = $conn->prepare($sqlTeams);
    $stmtT->bind_param("i", $userId);
    $stmtT->execute();
    $resT = $stmtT->get_result();
    $myTeams = [];
    while ($rowT = $resT->fetch_assoc()) {
        $myTeams[] = $rowT;
    }
    $stmtT->close();

    $projectTeams = [];
    $sqlPT = "SELECT team_id FROM project_team WHERE project_id = ?";
    $stmtT = $conn->prepare($sqlPT);
    $stmtT->bind_param("i", $projectId);
    $stmtT->execute();
    $resT = $stmtT->get_result();
    while ($rowPT = $resT->fetch_assoc()) {
        $projectTeams[] = $rowPT['team_id'];
    }
    $stmtT->close();

    $canManage = false;
    foreach ($myTeams as $mt) {
        if (in_array($mt['id'], $projectTeams)) {
            $canManage = true;
            break;
        }
    }
}
if (!$canManage) {
    die("Access denied");
}

// 1. Check if project exists
if (!isset($_GET['id'])) {
    die("No project specified.");
}
$projectId = (int)$_GET['id'];

// Fetch project
$stmt = $conn->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->bind_param("i", $projectId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die("Project not found.");
}
$project = $result->fetch_assoc();
$stmt->close();

// 2. Optional: confirm this user is allowed to edit (team leader / admin).
//    For brevity, we won't show that logic here, but you'd check the user's role
//    or see if they're the leader of the team that owns the project, etc.

// 3. Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $newTitle       = $_POST['title'] ?? '';
    $newDescription = $_POST['description'] ?? '';
    $newStatus      = $_POST['status'] ?? 'open';

    if (!empty($newTitle)) {
        $upd = $conn->prepare("
            UPDATE projects
            SET title = ?, description = ?, status = ?
            WHERE id = ?
        ");
        $upd->bind_param("sssi", $newTitle, $newDescription, $newStatus, $projectId);

        if ($upd->execute()) {
            $successMsg = "Project updated!";
            $project['title']       = $newTitle;
            $project['description'] = $newDescription;
            $project['status']      = $newStatus;
        } else {
            $errorMsg = "Error updating project: " . $upd->error;
        }
        $upd->close();
    } else {
        $errorMsg = "Project title cannot be empty.";
    }
}

// 4. Handle delete
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    // Additional checks for role if needed
    $del = $conn->prepare("DELETE FROM projects WHERE id = ?");
    $del->bind_param("i", $projectId);
    if ($del->execute()) {
        header("Location: projects.php?msg=ProjectDeleted");
        exit;
    } else {
        $errorMsg = "Error deleting project: " . $del->error;
    }
    $del->close();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Project</title>
</head>
<body>
<h1>Edit Project</h1>

<?php
if (!empty($errorMsg)) {
    echo "<p style='color:red;'>$errorMsg</p>";
}
if (!empty($successMsg)) {
    echo "<p style='color:green;'>$successMsg</p>";
}
?>

<!-- Edit form -->
<form method="post">
    <input type="hidden" name="action" value="update">

    <label>Title:</label><br>
    <input type="text" name="title" value="<?php echo htmlspecialchars($project['title']); ?>"><br><br>

    <label>Description:</label><br>
    <textarea name="description"><?php echo htmlspecialchars($project['description']); ?></textarea><br><br>

    <label>Status:</label><br>
    <select name="status">
        <option value="open" <?php if ($project['status'] === 'open') echo 'selected'; ?>>Open</option>
        <option value="in progress" <?php if ($project['status'] === 'in progress') echo 'selected'; ?>>In Progress</option>
        <option value="completed" <?php if ($project['status'] === 'completed') echo 'selected'; ?>>Completed</option>
    </select><br><br>

    <button type="submit">Update Project</button>
</form>

<p>
    <a href="?id=<?php echo $projectId; ?>&action=delete"
       onclick="return confirm('Are you sure you want to delete this project?')">
       Delete Project
    </a>
</p>

<p><a href="projects.php">Back to Projects</a></p>
</body>
</html>
