<?php
// File: pages/projects/project_edit.php
session_start();
require '../../includes/auth_check.php';
include '../../includes/db.php';

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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../css/projects/project_edit.css">
    <title>Edit Project</title>
</head>
<body>
    <div class="container">
        <header>
            <h1>Edit Project</h1>
        </header>

        <main>
            <!-- Display error/success messages -->
            <?php if (!empty($errorMsg)): ?>
                <section class="alert error">
                    <p><?php echo htmlspecialchars($errorMsg); ?></p>
                </section>
            <?php endif; ?>
            <?php if (!empty($successMsg)): ?>
                <section class="alert success">
                    <p><?php echo htmlspecialchars($successMsg); ?></p>
                </section>
            <?php endif; ?>

            <!-- Edit form -->
            <section>
                <form method="post">
                    <input type="hidden" name="action" value="update">

                    <div class="form-group">
                        <label for="title">Title:</label>
                        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($project['title']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="description">Description:</label>
                        <textarea id="description" name="description"><?php echo htmlspecialchars($project['description']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="status">Status:</label>
                        <select id="status" name="status">
                            <option value="open" <?php if ($project['status'] === 'open') echo 'selected'; ?>>Open</option>
                            <option value="in progress" <?php if ($project['status'] === 'in progress') echo 'selected'; ?>>In Progress</option>
                            <option value="completed" <?php if ($project['status'] === 'completed') echo 'selected'; ?>>Completed</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="submit">Update Project</button>
                    </div>
                </form>
            </section>

            <!-- Delete link -->
            <section class="delete-section">
                <p>
                    <a href="?id=<?php echo $projectId; ?>&action=delete"
                        onclick="return confirm('Are you sure you want to delete this project?')" >
                        Delete Project
                    </a>
                </p>
            </section>
        </main>

        <footer>
            <p><a href="projects.php"> < Back to Projects</a></p>
        </footer>
    </div>
</body>
</html>

