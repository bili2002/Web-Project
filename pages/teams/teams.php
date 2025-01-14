<?php
// teams.php
session_start();
require '../../includes/auth_check.php';  // Protect this page
include '../../includes/db.php';

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teamName = $_POST['team_name'];

    if (!empty($teamName)) {
        // Insert new team, leader = current user
        $stmt = $conn->prepare("
            INSERT INTO teams (team_name, leader_id) 
            VALUES (?, ?)
        ");
        $stmt->bind_param("si", $teamName, $userId);
        
        if ($stmt->execute()) {
            // Get the new team's ID
            $newTeamId = $stmt->insert_id;
            
            // Also insert the creator into team_members
            // (so the dashboard query sees them as a member)
            $roleInTeam = 'Leader';
            $stmt2 = $conn->prepare("
                INSERT INTO team_members (team_id, user_id, role_in_team)
                VALUES (?, ?, ?)
            ");
            $stmt2->bind_param("iis", $newTeamId, $userId, $roleInTeam);
            $stmt2->execute();
            $stmt2->close();

            $successMsg = "Team '{$teamName}' created! You are now its leader.";
        } else {
            $errorMsg = "Error creating team: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $errorMsg = "Please enter a team name.";
    }
}

// Fetch all teams (any user can see them all here)
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
    <title>Teams</title>
    <link rel="stylesheet" type="text/css" href="../../css/teams.css">

</head>
<body>
<h1>Teams</h1>

<?php
if (!empty($errorMsg)) {
    echo "<p style='color:red;'>$errorMsg</p>";
}
if (!empty($successMsg)) {
    echo "<p style='color:green;'>$successMsg</p>";
}
?>

<!-- Form to create a new team -->
<form method="post" action="">
    <label>Team Name:</label><br>
    <input type="text" name="team_name" required>
    <button type="submit">Create Team</button>
</form>

<hr>

<!-- List of existing teams -->
<?php if (count($teams) > 0): ?>
    <ul>
        <?php foreach ($teams as $team): ?>
            <li>
                <strong><?php echo htmlspecialchars($team['team_name']); ?></strong>
                (Leader: <?php echo htmlspecialchars($team['leader_name'] ?? 'None'); ?>)
                —
                <a href="team_detail.php?id=<?php echo $team['id']; ?>">Manage</a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php else: ?>
    <p>No teams yet.</p>
<?php endif; ?>

<p><a href="../dashboard.php">< Back to Dashboard</a></p>
</body>
</html>
