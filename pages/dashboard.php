<?php
session_start();
require '../includes/auth_check.php'; // Check session & login

include '../includes/db.php';

$userId = $_SESSION['user_id'];

/**
 * 1) Fetch tasks assigned to this user
 *    We'll join user_project_task (the link table) with tasks and projects
 */
$tasksSql = "
    SELECT 
        upt.*,
        t.title AS task_title, 
        p.title AS project_title
    FROM user_project_task upt
    JOIN tasks t ON upt.task_id = t.id
    JOIN projects p ON upt.project_id = p.id
    WHERE upt.user_id = ?
    ORDER BY upt.status ASC, upt.created_at ASC
";
$stmtTasks = $conn->prepare($tasksSql);
$stmtTasks->bind_param("i", $userId);
$stmtTasks->execute();
$tasksResult = $stmtTasks->get_result();
$stmtTasks->close();

/**
 * 2) Fetch projects the user is on (distinct)
 */
$projectsSql = "
    SELECT DISTINCT p.*
    FROM projects p
    JOIN user_project_task upt ON p.id = upt.project_id
    WHERE upt.user_id = ?
    ORDER BY p.created_at DESC
";
$stmtProjects = $conn->prepare($projectsSql);
$stmtProjects->bind_param("i", $userId);
$stmtProjects->execute();
$projectsResult = $stmtProjects->get_result();
$stmtProjects->close();

/**
 * 3) Team membership
 */
$result = $conn->query("
    SELECT t.*, u.username AS leader_name
    FROM teams t
    LEFT JOIN users u ON t.leader_id = u.id
    ORDER BY t.id DESC
");

$teams = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $teams[] = $row;
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <link rel="stylesheet" type="text/css" href="../css/dashboard.css">
</head>
<body>
<h1>Welcome to Your Dashboard</h1>

<!-- 1) Tasks assigned to the user -->
<h2>Your Tasks</h2>
<?php if ($tasksResult->num_rows > 0): ?>
    <ul>
        <?php while ($task = $tasksResult->fetch_assoc()): ?>
            <li>
                <!-- Task Title -->
                <strong><?php echo htmlspecialchars($task['task_title']); ?></strong>

                <!-- Associated Project -->
                (Project: <?php echo htmlspecialchars($task['project_title']); ?>)

                <!-- Status -->
                - Status: <?php echo htmlspecialchars($task['status']); ?>

                <!-- Created At -->
                - Created At: <?php echo htmlspecialchars($task['created_at']); ?>
            </li>
        <?php endwhile; ?>
    </ul>
<?php else: ?>
    <p>No tasks assigned yet.</p>
<?php endif; ?>

<h2>Your Projects</h2>
<?php if ($projectsResult->num_rows > 0): ?>
    <ul>
        <?php while ($proj = $projectsResult->fetch_assoc()): ?>
            <li>
                <strong><?php echo htmlspecialchars($proj['title']); ?></strong>
                - Status: <?php echo htmlspecialchars($proj['status']); ?>
                - Created: <?php echo htmlspecialchars($proj['created_at']); ?>
            </li>
        <?php endwhile; ?>
    </ul>
<?php else: ?>
    <p>You are not assigned to any projects yet.</p>
<?php endif; ?>

<section id="your-teams">
    <?php if (count($teams) > 0): ?>
        <ul>
            <?php foreach ($teams as $team): ?>
                <li>
                    <strong><?php echo htmlspecialchars($team['team_name']); ?></strong>
                    (Leader:  <?php echo htmlspecialchars($team['leader_name'] ?? 'None'); ?>)
                    <a href="teams/team_detail.php?id=<?php echo $team['id']; ?>">Manage</a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>You are not a member of any teams yet.</p>
    <?php endif; ?>
</section>

<!-- Basic nav links -->
 <section id = "basic-links">
<a href="teams/teams.php">Manage Teams</a> |
<a href="projects/projects.php">Projects</a> |
<a href="tasks/tasks.php">Tasks</a> |
<a href="auth/logout.php">Logout</a>
    </section>

</body>
</html>
