<?php
// File: pages/projects/projects.php
session_start();
require '../../includes/auth_check.php';
include '../../includes/db.php';

// Fetch all projects
$sql = "SELECT * FROM projects ORDER BY id DESC";
$res = $conn->query($sql);
$projects = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $projects[] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Projects</title>
</head>
<body>
<h1>Projects</h1>

<!-- Simple list of projects -->
<?php if (count($projects) > 0): ?>
    <ul>
    <?php foreach ($projects as $p): ?>
        <li>
            <strong><?php echo htmlspecialchars($p['title']); ?></strong>
            (Status: <?php echo htmlspecialchars($p['status']); ?>)
            <br>
            <!-- Link to edit project details -->
            <a href="project_edit.php?id=<?php echo $p['id']; ?>">Edit Project</a>
            |
            <!-- Link to manage tasks for this project -->
            <a href="project_manage.php?id=<?php echo $p['id']; ?>">Manage Tasks</a>
        </li>
    <?php endforeach; ?>
    </ul>
<?php else: ?>
    <p>No projects found.</p>
<?php endif; ?>

<p><a href="../dashboard.php">Back to Dashboard</a></p>
</body>
</html>
