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
<html>
<head>
    <title>Projects</title>
    <style>
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .error { color:red; }
        .success { color:green; }
        .modal-backdrop {
            display: none;
            position: fixed;
            inset: 0; 
            background: rgba(0,0,0,0.4);
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        .modal-backdrop.active { display: flex; }
        .modal {
            background: #fff;
            width: 400px;
            padding: 20px;
            position: relative;
            border-radius: 8px;
        }
        .close-btn {
            position: absolute; 
            top: 5px; 
            right: 10px; 
            cursor: pointer; 
            font-weight: bold;
        }
        .close-btn:hover { color: #999; }
        .form-group { margin-bottom:10px; }
        label { display:block; font-weight:bold; margin-bottom:5px; }
    </style>
</head>
<body>
<div class="container">
    <h1>Projects</h1>

    <!-- Display error/success messages -->
    <?php if (!empty($errorMsg)): ?>
        <p class="error"><?php echo $errorMsg; ?></p>
    <?php endif; ?>
    <?php if (!empty($successMsg)): ?>
        <p class="success"><?php echo $successMsg; ?></p>
    <?php endif; ?>

    <button type="button" onclick="openCreateProjectModal()">Create Project</button>

    <!-- List of projects -->
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
</div>

<!-- CREATE PROJECT MODAL -->
<div class="modal-backdrop" id="createProjectModal">
    <div class="modal">
        <span class="close-btn" onclick="closeCreateProjectModal()">x</span>
        <h2>Create a New Project</h2>

        <!-- We post back to the same file, so we handle it in the top code -->
        <form method="post">
            <input type="hidden" name="create_project" value="1"> 
            <!-- just a flag so we know it's a project creation form -->

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
