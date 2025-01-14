<?php
// File: pages/projects/projects.php
session_start();
require '../../includes/auth_check.php'; // Ensure user is logged in
include '../../includes/db.php';

// For displaying success/error after creation
$errorMsg   = '';
$successMsg = '';

/**
 * 1) Handle CREATE PROJECT in the same file
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_project'])) {
    // Grab form fields
    $projTitle       = $_POST['proj_title']       ?? '';
    $projDescription = $_POST['proj_description'] ?? '';
    $projStatus      = $_POST['proj_status']      ?? 'open';

    // Basic validation
    if (empty($projTitle)) {
        $errorMsg = "Project title is required.";
    } else {
        // Insert into projects
        $stmt = $conn->prepare("
            INSERT INTO projects (title, description, status)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("sss", $projTitle, $projDescription, $projStatus);
        if ($stmt->execute()) {
            $successMsg = "Project '{$projTitle}' created successfully!";
        } else {
            $errorMsg = "Error creating project: " . $stmt->error;
        }
        $stmt->close();
    }
}

/**
 * 2) Fetch and Display all projects
 */
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects</title>
    <link rel="stylesheet" href="../../css/projects/projects.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Projects</h1>
        </header>

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

        <!-- Create Project Button -->
        <section class="actions">
            <button type="button" onclick="openCreateProjectModal()">Create Project</button>
        </section>

        <!-- List of projects -->
        <main>
            <?php if (count($projects) > 0): ?>
                <ul class="project-list">
                <?php foreach ($projects as $p): ?>
                    <li class="project-item">
                        <div class="project-info">
                            <strong><?php echo htmlspecialchars($p['title']); ?></strong>
                            <span class="project-status">(Status: <?php echo htmlspecialchars($p['status']); ?>)</span>
                        </div>
                        <div class="project-actions">
                            <a href="project_edit.php?id=<?php echo $p['id']; ?>">Edit Project</a>
                            |
                            <a href="project_manage.php?id=<?php echo $p['id']; ?>">Manage Tasks</a>
                        </div>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="no-projects">No projects found.</p>
            <?php endif; ?>
        </main>

        <!-- Back to Dashboard -->
        <footer>
            <p><a href="../dashboard.php">&lt; Back to Dashboard</a></p>
        </footer>
    </div>

    <!-- CREATE PROJECT MODAL -->
    <div class="modal-backdrop" id="createProjectModal">
        <div class="modal">
            <span class="close-btn" onclick="closeCreateProjectModal()">x</span>
            <h2>Create a New Project</h2>

            <!-- Project creation form -->
            <form method="post">
                <input type="hidden" name="create_project" value="1">

                <div class="form-group">
                    <label for="proj_title">Project Title:</label>
                    <input type="text" name="proj_title" id="proj_title" required>
                </div>

                <div class="form-group">
                    <label for="proj_description">Description:</label>
                    <textarea name="proj_description" id="proj_description"></textarea>
                </div>

                <div class="form-group">
                    <label for="proj_status">Status:</label>
                    <select name="proj_status" id="proj_status">
                        <option value="open">Open</option>
                        <option value="in progress">In Progress</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>

                <button type="submit">Create</button>
            </form>
        </div>
    </div>

    <script>
        function openCreateProjectModal() {
            document.getElementById('createProjectModal').classList.add('active');
        }
        function closeCreateProjectModal() {
            document.getElementById('createProjectModal').classList.remove('active');
        }
    </script>
</body>
</html>
