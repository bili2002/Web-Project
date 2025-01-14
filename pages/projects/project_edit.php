<?php
// File: pages/projects/project_edit.php
session_start();
require '../../includes/auth_check.php';
include '../../includes/db.php';

// 1) Make sure project ID is given
if (!isset($_GET['id'])) {
    die("No project specified.");
}
$projectId = (int)$_GET['id'];

// 2) Check if project exists
$stmt = $conn->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->bind_param("i", $projectId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die("Project not found.");
}
$project = $result->fetch_assoc();
$stmt->close();

$userId   = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'user';  

// 3) Check if user can manage this project:
//    We gather all team_ids for this project from project_team
$projectTeams = [];
$sqlPT = "SELECT team_id FROM project_team WHERE project_id = ?";
$stmtT = $conn->prepare($sqlPT);
$stmtT->bind_param("i", $projectId);
$stmtT->execute();
$resPT = $stmtT->get_result();
while ($rowPT = $resPT->fetch_assoc()) {
    $projectTeams[] = $rowPT['team_id'];
}
$stmtT->close();

// Next, gather the user’s teams (if user is admin => can manage all)
$canManage = false;
if ($userRole === 'admin') {
    $canManage = true;
} else {
    // We gather all teams where user is a member or specifically the leader
    // if you want only the leader to do it, you’d adapt logic:
    $sqlTeams = "
        SELECT t.id
        FROM team_members tm
        JOIN teams t ON tm.team_id = t.id
        WHERE tm.user_id = ?
    ";
    $stmt2 = $conn->prepare($sqlTeams);
    $stmt2->bind_param("i", $userId);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    $myTeamIds = [];
    while ($r2 = $res2->fetch_assoc()) {
        $myTeamIds[] = $r2['id'];
    }
    $stmt2->close();

    // If ANY of user’s teams is in $projectTeams => can manage
    foreach ($myTeamIds as $tid) {
        if (in_array($tid, $projectTeams)) {
            $canManage = true;
            break;
        }
    }
}
if (!$canManage) {
    die("Access denied: You are not on any team assigned to this project (or not admin).");
}

// 4) Handling form submissions (edit project, add teams, remove team)
$errorMsg   = '';
$successMsg = '';

// A) If user clicked "Update Project" for title/desc/status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $newTitle       = $_POST['title']       ?? '';
    $newDescription = $_POST['description'] ?? '';
    $newStatus      = $_POST['status']      ?? 'open';

    if (!empty($newTitle)) {
        $upd = $conn->prepare("
            UPDATE projects
            SET title = ?, description = ?, status = ?
            WHERE id = ?
        ");
        $upd->bind_param("sssi", $newTitle, $newDescription, $newStatus, $projectId);

        if ($upd->execute()) {
            $successMsg = "Project updated!";
            $project['title']       = $newTitle;       // update local
            $project['description'] = $newDescription; // to reflect in form
            $project['status']      = $newStatus;
        } else {
            $errorMsg = "Error updating project: " . $upd->error;
        }
        $upd->close();
    } else {
        $errorMsg = "Project title cannot be empty.";
    }
}

// B) If user is adding teams to this project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_teams') {
    $selectedTeams = $_POST['team_ids'] ?? [];
    if (!empty($selectedTeams)) {
        $stmtInsert = $conn->prepare("INSERT IGNORE INTO project_team (project_id, team_id) VALUES (?, ?)");
        foreach ($selectedTeams as $tid) {
            $tid = (int)$tid; 
            $stmtInsert->bind_param("ii", $projectId, $tid);
            if (!$stmtInsert->execute()) {
                $errorMsg = "Error adding team_id=$tid: " . $stmtInsert->error;
                break;
            }
        }
        $stmtInsert->close();
        if (!$errorMsg) {
            $successMsg = "Selected teams have been added to this project.";
        }
        // also refresh $projectTeams in memory
        $projectTeams = [];
        $sqlPT = "SELECT team_id FROM project_team WHERE project_id = ?";
        $stmtT = $conn->prepare($sqlPT);
        $stmtT->bind_param("i", $projectId);
        $stmtT->execute();
        $resPT = $stmtT->get_result();
        while ($rowPT = $resPT->fetch_assoc()) {
            $projectTeams[] = $rowPT['team_id'];
        }
        $stmtT->close();
    }
}

// C) If user clicked "Remove" on a team
if (isset($_GET['remove_team'])) {
    $removeTid = (int)$_GET['remove_team'];
    // remove from project_team
    $delPT = $conn->prepare("DELETE FROM project_team WHERE project_id=? AND team_id=?");
    $delPT->bind_param("ii", $projectId, $removeTid);
    if ($delPT->execute()) {
        $successMsg = "Team ID $removeTid removed from project.";
        // also update $projectTeams
        $key = array_search($removeTid, $projectTeams);
        if ($key !== false) {
            unset($projectTeams[$key]);
        }
    } else {
        $errorMsg = "Error removing team: " . $delPT->error;
    }
    $delPT->close();
}

// D) If user clicked "Delete Project"
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
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

// 5) For the "Add teams" multi-select, we build a list of teams the user leads or is admin
//    but we exclude teams already assigned
$myPossibleTeams = [];
if ($userRole === 'admin') {
    $sqlAll = "SELECT * FROM teams ORDER BY id ASC";
    $stmtAll = $conn->query($sqlAll);
    while ($rA = $stmtAll->fetch_assoc()) {
        $myPossibleTeams[] = $rA;
    }
} else {
    // If normal user, gather all teams they lead or are member of
    $sqlAll = "
        SELECT t.* 
        FROM team_members tm
        JOIN teams t ON tm.team_id = t.id
        WHERE tm.user_id = ?
    ";
    $stmtX = $conn->prepare($sqlAll);
    $stmtX->bind_param("i", $userId);
    $stmtX->execute();
    $rsX = $stmtX->get_result();
    while ($rx = $rsX->fetch_assoc()) {
        $myPossibleTeams[] = $rx;
    }
    $stmtX->close();
}
// Filter out teams that are already in $projectTeams
$myTeamsToAdd = [];
foreach ($myPossibleTeams as $mt) {
    if (!in_array($mt['id'], $projectTeams)) {
        $myTeamsToAdd[] = $mt;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Project</title>
</head>
<body>
<h1>Edit Project</h1>

<?php if (!empty($errorMsg)) : ?>
    <p style='color:red;'><?php echo $errorMsg; ?></p>
<?php endif; ?>
<?php if (!empty($successMsg)) : ?>
    <p style='color:green;'><?php echo $successMsg; ?></p>
<?php endif; ?>

<!-- Edit form for project basics -->
<form method="post">
    <input type="hidden" name="action" value="update">

    <label>Title:</label><br>
    <input type="text" name="title" value="<?php echo htmlspecialchars($project['title']); ?>"><br><br>

    <label>Description:</label><br>
    <textarea name="description"><?php echo htmlspecialchars($project['description']); ?></textarea><br><br>

    <label>Status:</label><br>
    <select name="status">
        <option value="open" 
          <?php if ($project['status'] === 'open') echo 'selected'; ?>>Open</option>
        <option value="in progress" 
          <?php if ($project['status'] === 'in progress') echo 'selected'; ?>>In Progress</option>
        <option value="completed" 
          <?php if ($project['status'] === 'completed') echo 'selected'; ?>>Completed</option>
    </select><br><br>

    <button type="submit">Update Project</button>
</form>

<!-- Remove project link -->
<p>
    <a href="?id=<?php echo $projectId; ?>&action=delete"
       onclick="return confirm('Are you sure you want to delete this project?')">
       Delete Project
    </a>
</p>

<hr>
<!-- Show currently assigned teams, with a remove link -->
<h3>Assigned Teams</h3>
<?php
if (count($projectTeams) > 0) {
    echo "<ul>";
    foreach ($projectTeams as $tid) {
        echo "<li>Team ID #$tid 
             <a href='?id=$projectId&remove_team=$tid'
                onclick=\"return confirm('Remove team #$tid from project?');\">
                [Remove]
             </a>
             </li>";
    }
    echo "</ul>";
} else {
    echo "<p>No teams assigned yet.</p>";
}
?>

<hr>
<!-- Add new teams -->
<h3>Add Teams to Project</h3>
<?php if (count($myTeamsToAdd) > 0): ?>
<form method="post">
    <input type="hidden" name="action" value="add_teams">
    <label>Select teams to add:</label><br>
    <select name="team_ids[]" multiple size="5">
        <?php foreach ($myTeamsToAdd as $mt): ?>
            <option value="<?php echo $mt['id']; ?>">
                <?php echo htmlspecialchars($mt['team_name'] . " (ID:" . $mt['id'] . ")"); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <p style="font-size:0.9em;">(Hold Ctrl/Cmd to select multiple)</p>
    <button type="submit">Add Selected Teams</button>
</form>
<?php else: ?>
    <p>No additional teams you belong to are available to add.</p>
<?php endif; ?>

<p><a href="projects.php">Back to Projects</a></p>
</body>
</html>
