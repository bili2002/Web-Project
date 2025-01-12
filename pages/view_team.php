<?php
include 'includes/db.php';

$team_id = intval($_GET['team_id']);

// Fetch team members
$sql = "SELECT u.id AS user_id, u.username, tm.role 
        FROM team_members tm
        JOIN users u ON tm.user_id = u.id
        WHERE tm.team_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $team_id);
$stmt->execute();
$result = $stmt->get_result();

$members = [];
while ($row = $result->fetch_assoc()) {
    $members[] = $row;
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Team</title>
</head>
<body>
    <h1>Team Members</h1>
    <ul>
        <?php foreach ($members as $member): ?>
            <li>
                <?php echo htmlspecialchars($member['username']); ?> (<?php echo htmlspecialchars($member['role']); ?>)
                <a href="edit_member.php?user_id=<?php echo $member['user_id']; ?>&team_id=<?php echo $team_id; ?>">Edit Role</a>
                <a href="remove_member.php?user_id=<?php echo $member['user_id']; ?>&team_id=<?php echo $team_id; ?>">Remove</a>
            </li>
        <?php endforeach; ?>
    </ul>
    <a href="dashboard.php">Back to Dashboard</a>
</body>
</html>
