<?php
// File: pages/projects/projects.php
session_start();
require '../../includes/auth_check.php'; // Ensure user is logged in
include '../../includes/db.php';

// For displaying success/error
$errorMsg   = '';
$successMsg = '';

// 1) Fetch the current user's teams where they are leader OR admin
//    So we can show these in the multi-select if they are the "owner" of the team
$userId   = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'user';  // could be 'admin', 'team_leader', etc.

// We'll get all teams where this user is either the leader or an admin user
// If you want strict "leader only," adapt your logic.
if ($userRole === 'admin') {
    // Admin can see all teams
    $sqlTeams = "SELECT * FROM teams ORDER BY id ASC";
    $stmtT = $conn->prepare($sqlTeams);
    $stmtT->execute();
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
}
$resT = $stmtT->get_result();
$myTeams = [];
while ($rowT = $resT->fetch_assoc()) {
    $myTeams[] = $rowT;
}
$stmtT->close();

/**
 * 2) Handle CREATE PROJECT (with multi-select teams)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_project'])) {
    $projTitle       = $_POST['proj_title']       ?? '';
    $projDescription = $_POST['proj_description'] ?? '';
    $projStatus      = $_POST['proj_status']      ?? 'open';

    // The selected team IDs from the multi-select
    $selectedTeamIds = isset($_POST['team_ids']) ? $_POST['team_ids'] : [];

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
            $newProjectId = $stmt->insert_id;
            $stmt->close();

            // Insert each selected team into project_team
            // This ensures the project -> team link
            if (!empty($selectedTeamIds)) {
                $insPT = $conn->prepare("INSERT INTO project_team (project_id, team_id) VALUES (?, ?)");
                foreach ($selectedTeamIds as $teamId) {
                    $insPT->bind_param("ii", $newProjectId, $teamId);
                    if (!$insPT->execute()) {
                        $errorMsg = "Project created, but linking to team_id=$teamId failed: " . $insPT->error;
                        break;
                    }
                }
                $insPT->close();
            }

            if (empty($errorMsg)) {
                $successMsg = "Project '{$projTitle}' created successfully!";
            }
        } else {
            $errorMsg = "Error creating project: " . $stmt->error;
            $stmt->close();
        }
    }
}

/**
 * 3) Fetch and Display all projects
 *    We also check which teams are assigned to each project, so we can see if the user can edit
 */
$sqlProjects = "SELECT * FROM projects ORDER BY id DESC";
$resP = $conn->query($sqlProjects);
$projects = [];
if ($resP) {
    while ($rowP = $resP->fetch_assoc()) {
        $projects[] = $rowP;
    }
}
$resP->close();

// We'll build a map: project_id => [team_ids...]
$projectTeams = [];
$sqlPT = "SELECT project_id, team_id FROM project_team";
$resPT = $conn->query($sqlPT);
while ($rowPT = $resPT->fetch_assoc()) {
    $pid = $rowPT['project_id'];
    $tid = $rowPT['team_id'];
    if (!isset($projectTeams[$pid])) {
        $projectTeams[$pid] = [];
    }
    $projectTeams[$pid][] = $tid;
}
$resPT->close();
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
        <?php foreach ($projects as $p): 
            $pid       = $p['id'];
            $projTeams = $projectTeams[$pid] ?? []; // array of team_ids
            // We check if the user leads ANY of those teams => can edit
            // If user is admin, also can edit
            $canEditOrManage = false;
            if ($userRole === 'admin') {
                $canEditOrManage = true;
            } else {
                // If the user's leader_id is in $projTeams
                // Actually we stored team IDs, so we must see if user leads any of them
                foreach ($myTeams as $mt) {
                    $myTeamId   = $mt['id'];
                    if (in_array($myTeamId, $projTeams)) {
                        // user leads this team => can manage
                        $canEditOrManage = true;
                        break;
                    }
                }
            }
            ?>
            <li>
                <strong><?php echo htmlspecialchars($p['title']); ?></strong>
                (Status: <?php echo htmlspecialchars($p['status']); ?>)<br>
                
                Assigned Teams:
                <?php 
                if (count($projTeams) > 0) {
                    echo implode(", ", $projTeams); // or fetch actual names if you like
                } else {
                    echo "(none)";
                }
                ?>
                <br>

                <!-- If user can't manage/edit, hide links -->
                <?php if ($canEditOrManage): ?>
                    <!-- Link to edit project details -->
                    <a href="project_edit.php?id=<?php echo $p['id']; ?>">Edit Project</a>
                    |
                    <!-- Link to manage tasks -->
                    <a href="project_manage.php?id=<?php echo $p['id']; ?>">Manage Tasks</a>
                <?php else: ?>
                    <!-- Just show read-only info, no manage links -->
                    <em>You are not a leader for this project’s team(s).</em>
                <?php endif; ?>
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

            <div class="form-group">
                <label for="team_ids">Assign Teams (only teams you lead or admin sees all):</label>
                <!-- Multi-select of the user’s teams (or all if admin) -->
                <select name="team_ids[]" id="team_ids" multiple size="5">
                <?php foreach ($myTeams as $t): ?>
                    <option value="<?php echo $t['id']; ?>">
                        <?php echo htmlspecialchars($t['team_name'] . " (ID:" . $t['id'] . ")"); ?>
                    </option>
                <?php endforeach; ?>
                </select>
                <p style="font-size:0.9em;">Hold Ctrl/Cmd to select multiple.</p>
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
