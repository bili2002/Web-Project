<?php
// File: pages/projects/project_manage.php
session_start();
require '../../includes/auth_check.php';  // or your login-check
include '../../includes/db.php';

if (!isset($_GET['id'])) {
    die("No project specified.");
}
$projectId = (int)$_GET['id'];

// 1) Check if the project exists
$stmtProj = $conn->prepare("SELECT id, title FROM projects WHERE id=? LIMIT 1");
$stmtProj->bind_param("i", $projectId);
$stmtProj->execute();
$resProj = $stmtProj->get_result();
if ($resProj->num_rows === 0) {
    die("Project not found.");
}
$project = $resProj->fetch_assoc();
$stmtProj->close();

$errorMsg   = '';
$successMsg = '';

/**
 * 2) CREATE / EDIT / UPDATE STATUS / DELETE 
 *    but for tasks belonging to THIS single project
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $crudAction = $_POST['crud_action'] ?? '';

    // A) CREATE new task for this project (no status/hours fields, defaults to pending/0)
    if ($crudAction === 'create') {
        $title       = $_POST['create_title'] ?? '';
        $description = $_POST['create_description'] ?? '';

        if (empty($title)) {
            $errorMsg = "Task title is required.";
        } else {
            // 1) Insert into tasks
            $stmtT = $conn->prepare("
                INSERT INTO tasks (title, description)
                VALUES (?, ?)
            ");
            $stmtT->bind_param("ss", $title, $description);

            if ($stmtT->execute()) {
                $newTaskId = $stmtT->insert_id;
                $stmtT->close();

                // 2) Insert into user_project_task => link to this project
                $currentUserId = $_SESSION['user_id'];
                $linkSql = "
                    INSERT INTO user_project_task
                    (user_id, project_id, task_id, team_estimated_hours, actual_hours, status)
                    VALUES (?, ?, ?, 0, 0, 'pending')
                ";
                $stmtLink = $conn->prepare($linkSql);
                $stmtLink->bind_param("iii", $currentUserId, $projectId, $newTaskId);
                if ($stmtLink->execute()) {
                    $successMsg = "Task '$title' created for project '{$project['title']}'.";
                } else {
                    $errorMsg = "Task created but link failed: " . $stmtLink->error;
                }
                $stmtLink->close();
            } else {
                $errorMsg = "Error creating task: " . $stmtT->error;
                $stmtT->close();
            }
        }
    }
    // B) EDIT an existing task (title/description only)
    elseif ($crudAction === 'update_task') {
        $taskId      = (int)($_POST['edit_task_id'] ?? 0);
        $title       = $_POST['edit_title'] ?? '';
        $description = $_POST['edit_description'] ?? '';

        if ($taskId < 1) {
            $errorMsg = "Invalid task ID.";
        } elseif (empty($title)) {
            $errorMsg = "Task title cannot be empty.";
        } else {
            $updT = $conn->prepare("
                UPDATE tasks
                SET title = ?, description = ?
                WHERE id = ?
            ");
            $updT->bind_param("ssi", $title, $description, $taskId);
            if ($updT->execute()) {
                $successMsg = "Task #$taskId updated.";
            } else {
                $errorMsg = "Error updating task: " . $updT->error;
            }
            $updT->close();
        }
    }
    // C) UPDATE STATUS inline (prompt for actual hours if done)
    elseif ($crudAction === 'update_status') {
        $linkId    = (int)($_POST['link_id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? 'pending';
        $actual    = (int)($_POST['new_actual_hours'] ?? 0);

        if ($linkId < 1) {
            $errorMsg = "Invalid link ID for status update.";
        } else {
            if ($newStatus !== 'done') {
                $actual = 0;  // reset actual hours if not done
            }
            $updSt = $conn->prepare("
                UPDATE user_project_task
                SET status = ?, actual_hours = ?
                WHERE id = ?
            ");
            $updSt->bind_param("sii", $newStatus, $actual, $linkId);
            if ($updSt->execute()) {
                $successMsg = "Status updated to '$newStatus'.";
            } else {
                $errorMsg = "Error updating status: " . $updSt->error;
            }
            $updSt->close();
        }
    }
}

// 3) DELETE a task for this project
$action = $_GET['action'] ?? '';
$delTaskId = (int)($_GET['task_id'] ?? 0);
if ($action === 'delete' && $delTaskId > 0) {
    // (Optional) If you do ON DELETE CASCADE, the link row is removed automatically
    // Otherwise do: $conn->query("DELETE FROM user_project_task WHERE task_id=$delTaskId AND project_id=$projectId");
    $delStmt = $conn->prepare("DELETE FROM tasks WHERE id=?");
    $delStmt->bind_param("i", $delTaskId);
    if ($delStmt->execute()) {
        $successMsg = "Task #$delTaskId deleted from project '{$project['title']}'.";
    } else {
        $errorMsg = "Error deleting task: " . $delStmt->error;
    }
    $delStmt->close();
}

/**
 * 4) Fetch tasks that belong only to this project
 */
$tasksList = [];
$sql = "
    SELECT
      t.id AS task_id,
      t.title,
      t.description,
      upt.id AS link_id,
      upt.team_estimated_hours,
      upt.actual_hours,
      upt.status
    FROM user_project_task upt
    JOIN tasks t ON upt.task_id = t.id
    WHERE upt.project_id = ?
    ORDER BY t.id DESC
";
$stmtList = $conn->prepare($sql);
$stmtList->bind_param("i", $projectId);
$stmtList->execute();
$resList = $stmtList->get_result();
while ($row = $resList->fetch_assoc()) {
    $tasksList[] = $row;
}
$stmtList->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Tasks for Project "<?php echo htmlspecialchars($project['title']); ?>"</title>
    <style>
        .error { color:red; }
        .success { color:green; }
        table { border-collapse: collapse; }
        td, th { border:1px solid #ccc; padding:8px; }

        /* Modal backdrop for create/edit popups */
        .modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.4);
            justify-content: center;
            align-items: center;
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
            top:5px; 
            right:10px; 
            cursor: pointer; 
            font-weight: bold;
        }
        .close-btn:hover { color:#999; }
        .form-group { margin-bottom:10px; }
        label { display:block; font-weight:bold; margin-bottom:5px; }
    </style>
</head>
<body>
<h1>Manage Tasks for Project: <?php echo htmlspecialchars($project['title']); ?></h1>

<?php
if ($errorMsg)   echo "<p class='error'>$errorMsg</p>";
if ($successMsg) echo "<p class='success'>$successMsg</p>";
?>

<!-- CREATE TASK BUTTON -->
<button type="button" onclick="openCreateModal()">Create Task</button>
<br><br>

<!-- TABLE of tasks (NO project column) -->
<table>
  <thead>
    <tr>
      <th>ID</th>
      <th>Title</th>
      <th>Description</th>
      <th>Team Est</th>
      <th>Actual</th>
      <th>Status</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
  <?php if (count($tasksList) > 0): ?>
    <?php foreach ($tasksList as $ts): ?>
      <tr>
        <td><?php echo $ts['task_id']; ?></td>
        <td><?php echo htmlspecialchars($ts['title']); ?></td>
        <td><?php echo nl2br(htmlspecialchars($ts['description'])); ?></td>
        <td><?php echo (int)$ts['team_estimated_hours']; ?></td>
        <td><?php echo (int)$ts['actual_hours']; ?></td>
        <td>
          <form method="post" style="margin:0;" 
                onsubmit="return handleStatusChange(<?php echo $ts['link_id']; ?>)">
            <input type="hidden" name="crud_action" value="update_status">
            <input type="hidden" name="link_id" value="<?php echo $ts['link_id']; ?>">
            <input type="hidden" name="new_actual_hours" 
                   id="actualHidden<?php echo $ts['link_id']; ?>" 
                   value="<?php echo (int)$ts['actual_hours']; ?>">
            <select name="new_status" id="statusSelect<?php echo $ts['link_id']; ?>">
              <option value="pending" 
                <?php if ($ts['status'] === 'pending') echo 'selected'; ?>>Pending</option>
              <option value="in progress" 
                <?php if ($ts['status'] === 'in progress') echo 'selected'; ?>>In Progress</option>
              <option value="done" 
                <?php if ($ts['status'] === 'done') echo 'selected'; ?>>Done</option>
            </select>
            <button type="submit">Update</button>
          </form>
        </td>
        <td>
          <button type="button"
            onclick="openEditModal(
              <?php echo $ts['task_id']; ?>,
              '<?php echo addslashes($ts['title']); ?>',
              '<?php echo addslashes($ts['description']); ?>'
            )">Edit</button>
          |
          <a href="?action=delete&task_id=<?php echo $ts['task_id']; ?>&id=<?php echo $projectId; ?>"
             onclick="return confirm('Delete Task #<?php echo $ts['task_id']; ?> from project?');">
             Delete
          </a>
        </td>
      </tr>
    <?php endforeach; ?>
  <?php else: ?>
    <tr><td colspan="7">No tasks found for this project.</td></tr>
  <?php endif; ?>
  </tbody>
</table>

<br>
<p><a href="projects.php">Back to Projects</a></p>

<!-- CREATE MODAL -->
<div class="modal-backdrop" id="createModalBackdrop">
  <div class="modal">
    <span class="close-btn" onclick="closeCreateModal()">x</span>
    <h2>Create Task in "<?php echo htmlspecialchars($project['title']); ?>"</h2>
    <form method="post">
      <input type="hidden" name="crud_action" value="create">

      <div class="form-group">
        <label for="create_title">Title:</label>
        <input type="text" name="create_title" id="create_title" required>
      </div>
      <div class="form-group">
        <label for="create_description">Description:</label>
        <textarea name="create_description" id="create_description"></textarea>
      </div>

      <!-- No project dropdown, because we already know projectId from the URL. 
           No status or hours here. -->

      <button type="submit">Create</button>
    </form>
  </div>
</div>

<!-- EDIT MODAL (no project/hours) -->
<div class="modal-backdrop" id="editModalBackdrop">
  <div class="modal">
    <span class="close-btn" onclick="closeEditModal()">x</span>
    <h2>Edit Task</h2>
    <form method="post">
      <input type="hidden" name="crud_action" value="update_task">
      <input type="hidden" name="edit_task_id" id="edit_task_id">

      <div class="form-group">
        <label for="edit_title">Title:</label>
        <input type="text" name="edit_title" id="edit_title" required>
      </div>
      <div class="form-group">
        <label for="edit_description">Description:</label>
        <textarea name="edit_description" id="edit_description"></textarea>
      </div>

      <button type="submit">Update</button>
    </form>
  </div>
</div>

<script>
// CREATE
function openCreateModal(){
  document.getElementById('create_title').value = '';
  document.getElementById('create_description').value = '';
  document.getElementById('createModalBackdrop').classList.add('active');
}
function closeCreateModal(){
  document.getElementById('createModalBackdrop').classList.remove('active');
}

// EDIT
function openEditModal(taskId, title, description){
  document.getElementById('edit_task_id').value = taskId;
  document.getElementById('edit_title').value = title;
  document.getElementById('edit_description').value = description;
  document.getElementById('editModalBackdrop').classList.add('active');
}
function closeEditModal(){
  document.getElementById('editModalBackdrop').classList.remove('active');
}

// If user sets status=done => prompt for actual hours
function handleStatusChange(linkId){
  var sel       = document.getElementById("statusSelect"+linkId);
  var hiddenFld = document.getElementById("actualHidden"+linkId);

  if (sel.value === 'done') {
    var hours = prompt("Enter actual hours:", "0");
    if (hours === null) { 
      // user canceled => do not submit
      return false;
    }
    if (isNaN(hours) || hours < 0) {
      alert("Please enter a valid non-negative number for hours.");
      return false;
    }
    hiddenFld.value = hours;
  } else {
    hiddenFld.value = 0;
  }
  return true; 
}
</script>

</body>
</html>
