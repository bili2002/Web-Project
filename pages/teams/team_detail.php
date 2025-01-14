<?php
// team_detail.php
require '../../includes/auth_check.php'; // Adjust path as needed
include '../../includes/db.php';

// Check & parse the team id
if (!isset($_GET['id'])) {
    die("No team specified.");
}
$teamId = (int) $_GET['id'];

// Current user info
$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role']; // e.g., 'admin', 'user', etc.

// 1) Fetch team info
$sql = "SELECT t.*, u.username AS leader_name, u.id AS leader_user_id
        FROM teams t
        LEFT JOIN users u ON t.leader_id = u.id
        WHERE t.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $teamId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Team not found.");
}
$team = $result->fetch_assoc();
$stmt->close();

// Determine if the current user can manage (leader or admin)
$canManage = ($currentUserId == $team['leader_user_id'] || $currentUserRole === 'admin');

/**
 * 2) Handle "Delete Team" - only if $canManage
 *    We can do it via a GET param or a form POST. Here’s a GET example.
 */
if (isset($_GET['action']) && $_GET['action'] === 'deleteTeam' && $canManage) {
    // Delete the team
    $delStmt = $conn->prepare("DELETE FROM teams WHERE id = ?");
    $delStmt->bind_param("i", $teamId);
    if ($delStmt->execute()) {
        // Team deleted -> redirect somewhere
        header("Location: ../../pages/teams/teams.php?msg=TeamDeleted");
        exit;
    } else {
        $errorMsg = "Error deleting team: " . $delStmt->error;
    }
    $delStmt->close();
}

/**
 * 3) Handle "Edit Team Name" - only if $canManage
 */
if (isset($_POST['action']) && $_POST['action'] === 'edit_team' && $canManage) {
    $newName = $_POST['new_team_name'] ?? '';
    if (!empty($newName)) {
        $editStmt = $conn->prepare("UPDATE teams SET team_name = ? WHERE id = ?");
        $editStmt->bind_param("si", $newName, $teamId);
        if ($editStmt->execute()) {
            $team['team_name'] = $newName; // update local copy
            $successMsg = "Team name updated!";
        } else {
            $errorMsg = "Error updating team name: " . $editStmt->error;
        }
        $editStmt->close();
    } else {
        $errorMsg = "Team name cannot be empty.";
    }
}

/**
 * 4) Handle "Add Member" - only if $canManage
 */
if (isset($_POST['action']) && $_POST['action'] === 'add_member' && $canManage) {
    $userIdentifier = $_POST['user_identifier'] ?? ''; // username or faculty_number
    $roleInTeam     = $_POST['role_in_team'] ?? '';    // e.g. "developer"

    if (!empty($userIdentifier)) {
        // Find user by username or faculty_number
        $sqlUser = "SELECT id FROM users WHERE faculty_number = ? OR username = ? LIMIT 1";
        $stmtU = $conn->prepare($sqlUser);
        $stmtU->bind_param("ss", $userIdentifier, $userIdentifier);
        $stmtU->execute();
        $res = $stmtU->get_result();

        if ($res && $res->num_rows > 0) {
            $u = $res->fetch_assoc();
            $foundUserId = $u['id'];

            // Insert into team_members (or update role_in_team if already exists)
            // We'll do a simple "INSERT ... ON DUPLICATE KEY UPDATE" approach:
            $insertSql = "
                INSERT INTO team_members (team_id, user_id, role_in_team)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE role_in_team = VALUES(role_in_team)
            ";
            $insertStmt = $conn->prepare($insertSql);
            $insertStmt->bind_param("iis", $teamId, $foundUserId, $roleInTeam);

            if ($insertStmt->execute()) {
                $successMsg = "User added/updated in team.";
            } else {
                $errorMsg = "Error adding/updating user: " . $insertStmt->error;
            }
            $insertStmt->close();
        } else {
            $errorMsg = "User not found.";
        }
        $stmtU->close();
    } else {
        $errorMsg = "Please provide a username or faculty number.";
    }
}

/**
 * 5) Handle "Remove Member" - only if $canManage
 */
if (isset($_GET['remove_user']) && $canManage) {
    $removeUserId = (int)$_GET['remove_user'];
    // Don’t allow removing the team leader themself if that’s critical 
    // (but this depends on your logic)
    if ($removeUserId === (int)$team['leader_user_id'] && $currentUserRole !== 'admin') {
        $errorMsg = "Only an admin can remove the team leader.";
    } else {
        $delStmt = $conn->prepare("DELETE FROM team_members WHERE team_id = ? AND user_id = ?");
        $delStmt->bind_param("ii", $teamId, $removeUserId);
        if ($delStmt->execute()) {
            $successMsg = "User removed from team.";
        } else {
            $errorMsg = "Error removing user: " . $delStmt->error;
        }
        $delStmt->close();
    }
}

// 6) Fetch all members
$membersSql = "
    SELECT tm.user_id, tm.role_in_team,
           u.username, u.faculty_number
    FROM team_members tm
    JOIN users u ON tm.user_id = u.id
    WHERE tm.team_id = ?
";
$stmtM = $conn->prepare($membersSql);
$stmtM->bind_param("i", $teamId);
$stmtM->execute();
$members = $stmtM->get_result();
$stmtM->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Team Detail</title>
    <link rel="stylesheet" type="text/css" href="../../css/team_detail.css">
</head>
<body>
    <div class = "team-container">
        <div class = "team-header">
            <h1>Team Detail</h1>
</div>

<?php
// Display any error/success messages
if (!empty($errorMsg)) {
    echo "<p style='color:red;'>$errorMsg</p>";
}
if (!empty($successMsg)) {
    echo "<p style='color:green;'>$successMsg</p>";
}
?>

<dic class = "team-details">
    <div class = "team-card">
    <h2><?php echo htmlspecialchars($team['team_name']); ?></h2>
                <p><strong>Leader:</strong> <?php echo htmlspecialchars($team['leader_name']); ?> (ID: <?php echo htmlspecialchars($team['leader_id']); ?>)</p>
                <p><strong>Team ID:</strong> <?php echo htmlspecialchars($team['id']); ?></p>
    </div>
</div>
</div>

<!-- Only leader or admin can edit the team name and delete -->
<?php if ($canManage): ?>
    <hr>
    <h3>Edit Team</h3>
    <form method="post">
        <input type="hidden" name="action" value="edit_team">
        <label>New Team Name:</label><br>
        <input type="text" name="new_team_name" value="<?php echo htmlspecialchars($team['team_name']); ?>" required><br><br>
        <button type="submit">Update Team Name</button>
    </form>
    <br>
    <!-- Delete Team (use GET param for simplicity) -->
    <a href="?id=<?php echo $teamId; ?>&action=deleteTeam"
       onclick="return confirm('Are you sure you want to delete this team?')">
       Delete Team
    </a>
<?php endif; ?>

<hr>
<h3>Members</h3>
<?php if ($members->num_rows > 0): ?>
    <ul>
        <?php while ($m = $members->fetch_assoc()): ?>
            <li>
                <?php 
                    echo "User: " . htmlspecialchars($m['username'])
                       . " (Faculty#: " . htmlspecialchars($m['faculty_number']) . ")"
                       . " — Role in Team: " . htmlspecialchars($m['role_in_team'] ?? ''); 
                ?>
                <?php if ($canManage): ?>
                    <!-- Remove member link -->
                    <a href="?id=<?php echo $teamId; ?>&remove_user=<?php echo $m['user_id']; ?>"
                       onclick="return confirm('Remove this member?')">
                       Remove
                    </a>
                <?php endif; ?>
            </li>
        <?php endwhile; ?>
    </ul>
<?php else: ?>
    <p>No members yet.</p>
<?php endif; ?>

<!-- Only leader or admin can add new members -->
<?php if ($canManage): ?>
    <hr>
    <h3>Add New Member</h3>
    <form method="post">
        <input type="hidden" name="action" value="add_member">
        
        <label>Username or Faculty Number:</label><br>
        <input type="text" name="user_identifier" required><br><br>

        <label>Role in Team:</label><br>
        <input type="text" name="role_in_team" placeholder="e.g. Developer, Designer"><br><br>

        <button type="submit">Add to Team</button>
    </form>
<?php endif; ?>

<br>
<p><a href="../../pages/teams/teams.php">< Back to Teams</a></p>
</body>
</html>
