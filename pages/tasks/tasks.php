<?php
// File: pages/tasks/tasks.php
session_start();
require '../../includes/auth_check.php'; // or your login-check
include '../../includes/db.php';

$errorMsg   = '';
$successMsg = '';

// 1) Fetch projects for the "Create Task" modal
$projects = [];
$pRes = $conn->query("SELECT id, title FROM projects ORDER BY title ASC");
while ($pr = $pRes->fetch_assoc()) {
    $projects[] = $pr;
}

/**
 * 2) Handle CREATE (no status/hours) & EDIT (title/description) & UPDATE STATUS
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $crudAction = $_POST['crud_action'] ?? '';

    // A) CREATE a new task
    if ($crudAction === 'create') {
        $title       = $_POST['create_title'] ?? '';
        $description = $_POST['create_description'] ?? '';
        $projectId   = (int)($_POST['create_project_id'] ?? 0);

        if (empty($title)) {
            $errorMsg = "Task title is required.";
        } elseif ($projectId < 1) {
            $errorMsg = "Please choose a project.";
        } else {
            // Insert into tasks
            $stmt = $conn->prepare("
                INSERT INTO tasks (title, description)
                VALUES (?, ?)
            ");
            $stmt->bind_param("ss", $title, $description);

            if ($stmt->execute()) {
                $newTaskId = $stmt->insert_id;
                $stmt->close();

                // Insert link row with default status/hours
                $currentUserId = $_SESSION['user_id'];
                $linkSql = "
                    INSERT INTO user_project_task
                    (user_id, project_id, task_id, team_estimated_hours, actual_hours, status)
                    VALUES (?, ?, ?, 0, 0, 'pending')
                ";
                $stmt2 = $conn->prepare($linkSql);
                $stmt2->bind_param("iii", $currentUserId, $projectId, $newTaskId);

                if ($stmt2->execute()) {
                    $successMsg = "Task '$title' created successfully!";
                } else {
                    $errorMsg = "Task created but link failed: " . $stmt2->error;
                }
                $stmt2->close();
            } else {
                $errorMsg = "Error creating task: " . $stmt->error;
                $stmt->close();
            }
        }
    }
    // B) EDIT an existing task's title/description
    elseif ($crudAction === 'update_task') {
        $taskId      = (int)($_POST['edit_task_id'] ?? 0);
        $title       = $_POST['edit_title'] ?? '';
        $description = $_POST['edit_description'] ?? '';

        if ($taskId < 1) {
            $errorMsg = "Invalid task ID for edit.";
        } elseif (empty($title)) {
            $errorMsg = "Task title cannot be empty.";
        } else {
            $upd = $conn->prepare("
                UPDATE tasks
                SET title = ?, description = ?
                WHERE id = ?
            ");
            $upd->bind_param("ssi", $title, $description, $taskId);

            if ($upd->execute()) {
                $successMsg = "Task #$taskId updated!";
            } else {
                $errorMsg = "Error updating task: " . $upd->error;
            }
            $upd->close();
        }
    }
    // C) UPDATE STATUS (with optional actual_hours if done)
    elseif ($crudAction === 'update_status') {
        $linkId     = (int)($_POST['link_id'] ?? 0);
        $newStatus  = $_POST['new_status'] ?? 'pending';
        $actual     = (int)($_POST['new_actual_hours'] ?? 0);

        if ($linkId < 1) {
            $errorMsg = "Invalid link ID for status update.";
        } else {
            // If status != 'done', force actual=0
            if ($newStatus !== 'done') {
                $actual = 0;
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

// 3) DELETE a task via GET
$action   = $_GET['action'] ?? '';
$delTaskId= (int)($_GET['id'] ?? 0);
if ($action === 'delete' && $delTaskId > 0) {
    // If ON DELETE CASCADE is set, link rows are removed automatically.
    // Otherwise remove them manually: 
    // $conn->query("DELETE FROM user_project_task WHERE task_id=$delTaskId");

    $delStmt = $conn->prepare("DELETE FROM tasks WHERE id=?");
    $delStmt->bind_param("i", $delTaskId);
    if ($delStmt->execute()) {
        $successMsg = "Task #$delTaskId deleted.";
    } else {
        $errorMsg = "Error deleting task: " . $delStmt->error;
    }
    $delStmt->close();
}

/**
 * 4) Fetch tasks joined with user_project_task:
 *    We'll show: title, description, team_estimated_hours, actual_hours, status
 */
$tasksList = [];
$sql = "
    SELECT
      t.id AS task_id,
      t.title,
      t.description,
      p.title AS project_title,
      upt.id AS link_id,
      upt.team_estimated_hours,
      upt.actual_hours,
      upt.status
    FROM tasks t
    JOIN user_project_task upt ON t.id = upt.task_id
    JOIN projects p ON p.id = upt.project_id
    ORDER BY t.id DESC
";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    $tasksList[] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Tasks with Status=Done → Actual Hours Prompt</title>
    <style>
        .error { color: red; }
        .success { color: green; }
        table { border-collapse: collapse; }
        td, th { border:1px solid #ccc; padding: 8px; }

        /* Modals */
        .modal-backdrop {
            display: none;
            position: fixed;
            inset: 0; /* top,right,bottom,left=0 */
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
            top: 5px; 
            right: 8px; 
            cursor: pointer; 
            font-weight: bold;
        }
        .close-btn:hover { color: #999; }
        .form-group { margin-bottom: 10px; }
        label { font-weight: bold; display:block; margin-bottom:5px; }
    </style>
</head>
<body>
<h1>Tasks</h1>

<?php if ($errorMsg)   echo "<p class='error'>$errorMsg</p>"; ?>
<?php if ($successMsg) echo "<p class='success'>$successMsg</p>"; ?>

<!-- CREATE button -->
<button type="button" onclick="openCreateModal()">Create Task</button>
<br><br>

<!-- TASKS TABLE -->
<table>
  <thead>
    <tr>
      <th>ID</th>
      <th>Project</th>
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
        <td><?php echo htmlspecialchars($ts['project_title']); ?></td>
        <td><?php echo htmlspecialchars($ts['title']); ?></td>
        <td><?php echo nl2br(htmlspecialchars($ts['description'])); ?></td>
        <td><?php echo (int)$ts['team_estimated_hours']; ?></td>
        <td><?php echo (int)$ts['actual_hours']; ?></td>
        <td>
          <!-- Inline form to update status. If user chooses done => prompt for actual hours -->
          <form method="post" style="margin:0;" onsubmit="return handleStatusChange(<?php echo $ts['link_id']; ?>)">
            <input type="hidden" name="crud_action" value="update_status">
            <input type="hidden" name="link_id" value="<?php echo $ts['link_id']; ?>">
            <!-- Hidden field for new_actual_hours, set by JS if status=done -->
            <input type="hidden" name="new_actual_hours" id="actualHidden<?php echo $ts['link_id']; ?>" value="<?php echo (int)$ts['actual_hours']; ?>">

            <select name="new_status" id="statusSelect<?php echo $ts['link_id']; ?>">
              <option value="pending" 
                <?php if ($ts['status'] === 'pending') echo 'selected'; ?>>
                Pending
              </option>
              <option value="in progress" 
                <?php if ($ts['status'] === 'in progress') echo 'selected'; ?>>
                In Progress
              </option>
              <option value="done" 
                <?php if ($ts['status'] === 'done') echo 'selected'; ?>>
                Done
              </option>
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
            )">
            Edit
          </button>
          |
          <a href="?action=delete&id=<?php echo $ts['task_id']; ?>"
             onclick="return confirm('Delete Task #<?php echo $ts['task_id']; ?>?');">
             Delete
          </a>
        </td>
      </tr>
    <?php endforeach; ?>
  <?php else: ?>
    <tr><td colspan="8">No tasks found.</td></tr>
  <?php endif; ?>
  </tbody>
</table>

<p><a href="../dashboard.php">Back to Dashboard</a></p>

<!-- CREATE MODAL -->
<div class="modal-backdrop" id="createModalBackdrop">
  <div class="modal">
    <span class="close-btn" onclick="closeCreateModal()">x</span>
    <h2>Create Task</h2>
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
      <div class="form-group">
        <label>Project:</label>
        <select name="create_project_id" id="create_project_id" required>
          <option value="">--Choose--</option>
          <?php foreach ($projects as $pr): ?>
            <option value="<?php echo $pr['id']; ?>">
              <?php echo htmlspecialchars($pr['title']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- No status or hours here. They default in user_project_task. -->
      <button type="submit">Create</button>
    </form>
  </div>
</div>

<!-- EDIT MODAL (title/description only) -->
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

      <!-- No hours or status here. We only update tasks table. -->
      <button type="submit">Update</button>
    </form>
  </div>
</div>

<script>
// CREATE MODAL
function openCreateModal(){
  document.getElementById('create_title').value = '';
  document.getElementById('create_description').value = '';
  document.getElementById('create_project_id').value = '';
  document.getElementById('createModalBackdrop').classList.add('active');
}
function closeCreateModal(){
  document.getElementById('createModalBackdrop').classList.remove('active');
}

// EDIT MODAL
function openEditModal(taskId, title, description){
  document.getElementById('edit_task_id').value = taskId;
  document.getElementById('edit_title').value = title;
  document.getElementById('edit_description').value = description;
  document.getElementById('editModalBackdrop').classList.add('active');
}
function closeEditModal(){
  document.getElementById('editModalBackdrop').classList.remove('active');
}

// If user changes status to "done", prompt for actual hours
function handleStatusChange(linkId){
  var selectId      = "statusSelect" + linkId;       // <select> ID
  var hiddenActual  = "actualHidden" + linkId;       // hidden input ID

  // We'll actually just pass them from the function arguments:
  var sel = document.getElementById("statusSelect"+linkId);
  var hiddenField = document.getElementById("actualHidden"+linkId);

  if (sel.value === 'done') {
    var hours = prompt("Enter actual hours (number):", "0");
    if (hours === null) { 
      // user canceled => don't submit
      return false;
    }
    // parse it into integer or keep as is
    if (isNaN(hours) || hours < 0) {
      alert("Please enter a non-negative number for actual hours.");
      return false;
    }
    hiddenField.value = hours;
  } else {
    hiddenField.value = 0; // reset actual hours
  }
  return true; // proceed with form submission
}
</script>
</body>
</html>
