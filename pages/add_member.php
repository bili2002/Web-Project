<?php
include 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $team_id = intval($_POST['team_id']);
    $user_id = intval($_POST['user_id']);
    $role = $_POST['role'];

    $sql = "INSERT INTO team_members (team_id, user_id, role) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iis", $team_id, $user_id, $role);

    if ($stmt->execute()) {
        echo "Member added successfully!";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Member</title>
</head>
<body>
    <form action="add_member.php" method="POST">
        <input type="hidden" name="team_id" value="<?php echo intval($_GET['team_id']); ?>">
        <label for="user_id">Select User:</label>
        <select name="user_id" id="user_id" required>
            <?php
            include 'includes/db.php';
            $result = $conn->query("SELECT id, username FROM users");
            while ($row = $result->fetch_assoc()) {
                echo "<option value='{$row['id']}'>{$row['username']}</option>";
            }
            ?>
        </select>
        <label for="role">Role:</label>
        <select name="role" id="role" required>
            <option value="team_leader">Team Leader</option>
            <option value="developer">Developer</option>
            <option value="observer">Observer</option>
        </select>
        <button type="submit">Add Member</button>
    </form>
    <a href="dashboard.php">Back to Dashboard</a>
</body>
</html>
