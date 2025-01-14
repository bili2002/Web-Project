<?php
// File: pages/tasks/tasks.php
session_start();
require '../../includes/auth_check.php'; // or your login-check
include '../../includes/db.php';

$errorMsg   = '';
$successMsg = '';

// 1) Fetch all projects (for creating a task only)
$projects = [];
$pRes = $conn->query("SELECT id, title FROM projects ORDER BY title ASC");
while ($pr = $pRes->fetch_assoc()) {
    $projects[] = $pr;
}

/**
 * 2) Handle CREATE / EDIT / STATUS / DELETE
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $crudAction = $_POST['crud_action'] ?? '';

    // A) CREATE (task or subtask)
    if ($crudAction === 'create') {
        $title         = $_POST['create_title']        ?? '';
        $description   = $_POST['create_description']  ?? '';
        $parentId      = (int)($_POST['parent_id']      ?? 0);  
        $estHours      = (int)($_POST['create_estimated_hours'] ?? 0);

        // If parentId = 0 => user selected a project from dropdown
        // If parentId > 0 => this is a subtask, we skip the project field and use parent's project
        $projectId = 0;
        if ($parentId === 0) {
            // top-level => need project from form
            $projectId = (int)($_POST['create_project_id'] ?? 0);
            if ($projectId < 1) {
                $errorMsg = "Please choose a project for the task.";
            }
        } else {
            // subtask => find parent's project
            $projSql = "
                SELECT upt.project_id
                FROM user_project_task upt
                JOIN tasks t ON upt.task_id = t.id
                WHERE t.id = ?
                LIMIT 1
            ";
            $stmtP = $conn->prepare($projSql);
            $stmtP->bind_param("i", $parentId);
            $stmtP->execute();
            $rP = $stmtP->get_result();
            $rowP = $rP->fetch_assoc();
            $stmtP->close();

            if (!$rowP) {
                $errorMsg = "Parent task not found, or no project link.";
            } else {
                $projectId = (int)$rowP['project_id'];
            }
        }

        if (empty($title)) {
            $errorMsg = "Task title is required.";
        } elseif (!$errorMsg) {
            if (empty($parentId)) {
                $parentId = null;
            }

            // 1) Insert into tasks
            $stmt = $conn->prepare("
                INSERT INTO tasks (title, description, parent_id)
                VALUES (?, ?, ?)
            ");
            $stmt->bind_param("ssi", $title, $description, $parentId);

            if ($stmt->execute()) {
                $newTaskId = $stmt->insert_id;
                $stmt->close();

                // 2) Insert link row in user_project_task with estimated hours
                $currentUserId = $_SESSION['user_id'];
                $linkSql = "
                    INSERT INTO user_project_task
                    (user_id, project_id, task_id, team_estimated_hours, actual_hours, status)
                    VALUES (?, ?, ?, ?, 0, 'pending')
                ";
                $stmt2 = $conn->prepare($linkSql);
                $stmt2->bind_param("iiii", $currentUserId, $projectId, $newTaskId, $estHours);

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
    // B) EDIT (title/desc) + update estimate if pending
    elseif ($crudAction === 'update_task') {
        $taskId    = (int)($_POST['edit_task_id'] ?? 0);
        $title     = $_POST['edit_title']         ?? '';
        $desc      = $_POST['edit_description']   ?? '';
        $linkId    = (int)($_POST['edit_link_id'] ?? 0);
        $newEst    = (int)($_POST['edit_estimated_hours'] ?? 0);

        if ($taskId < 1) {
            $errorMsg = "Invalid task ID.";
        } elseif (empty($title)) {
            $errorMsg = "Task title cannot be empty.";
        } else {
            // 1) Update tasks table
            $upd = $conn->prepare("
                UPDATE tasks
                SET title=?, description=?
                WHERE id=?
            ");
            $upd->bind_param("ssi", $title, $desc, $taskId);
            if (!$upd->execute()) {
                $errorMsg = "Error updating task: " . $upd->error;
            }
            $upd->close();

            // 2) If user_project_task status is 'pending', we allow editing the estimate
            $stmtSt = $conn->prepare("
                SELECT status 
                FROM user_project_task
                WHERE id=? LIMIT 1
            ");
            $stmtSt->bind_param("i", $linkId);
            $stmtSt->execute();
            $resSt = $stmtSt->get_result();
            $rowSt = $resSt->fetch_assoc();
            $stmtSt->close();

            if ($rowSt && $rowSt['status'] === 'pending') {
                // update the estimate
                $updEst = $conn->prepare("
                    UPDATE user_project_task
                    SET team_estimated_hours=?
                    WHERE id=?
                ");
                $updEst->bind_param("ii", $newEst, $linkId);
                if (!$updEst->execute()) {
                    $errorMsg = "Error updating estimate: " . $updEst->error;
                } else {
                    $successMsg = "Task #$taskId updated.";
                }
                $updEst->close();
            } else {
                $successMsg = "Task #$taskId updated (title/desc). Estimate not changed (status not pending).";
            }
        }
    }
    // C) UPDATE STATUS
    elseif ($crudAction === 'update_status') {
        $linkId    = (int)($_POST['link_id'] ?? 0);
        $newStatus = $_POST['new_status']         ?? 'pending';
        $actual    = (int)($_POST['new_actual_hours'] ?? 0);

        if ($linkId < 1) {
            $errorMsg = "Invalid link ID for status update.";
        } else {
            if ($newStatus !== 'done') {
                $actual = 0;
            }
            $updSt = $conn->prepare("
                UPDATE user_project_task
                SET status=?, actual_hours=?
                WHERE id=?
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

// 3) DELETE
$action   = $_GET['action'] ?? '';
$delTaskId= (int)($_GET['id'] ?? 0);
if ($action === 'delete' && $delTaskId > 0) {
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
 * 4) Build the DFS data
 */
$allTasks = [];
$sql = "
    SELECT
      t.id AS task_id,
      t.parent_id,
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
    ORDER BY t.id ASC
";
$res = $conn->query($sql);

$tasksByParent = [];
while ($row = $res->fetch_assoc()) {
    $pId = $row['parent_id'] ?? 0;
    $tasksByParent[$pId][] = $row;
}

// (Optional) sort each parent's children by task_id
foreach ($tasksByParent as &$grp) {
    usort($grp, function($a, $b){
        return $a['task_id'] - $b['task_id'];
    });
}
unset($grp);

/**
 * 5) Print DFS
 */
function printTasksDFS($parentId, $level, $tasksByParent) {
    if (empty($tasksByParent[$parentId])) return;

    foreach ($tasksByParent[$parentId] as $ts) {
        $indent = str_repeat('— ', $level);

        echo "<tr>\n";
        echo "  <td>{$ts['task_id']}</td>\n";
        echo "  <td>" . htmlspecialchars($ts['project_title']) . "</td>\n";
        echo "  <td>" . $indent . htmlspecialchars($ts['title']) . "</td>\n";
        echo "  <td>" . nl2br(htmlspecialchars($ts['description'])) . "</td>\n";
        echo "  <td>" . (int)$ts['team_estimated_hours'] . "</td>\n";
        echo "  <td>" . (int)$ts['actual_hours'] . "</td>\n";

        // Status cell
        echo "  <td>\n";
        echo "    <form method='post' style='margin:0;' onsubmit='return handleStatusChange({$ts['link_id']})'>\n";
        echo "      <input type='hidden' name='crud_action' value='update_status'>\n";
        echo "      <input type='hidden' name='link_id' value='{$ts['link_id']}'>\n";
        echo "      <input type='hidden' name='new_actual_hours' id='actualHidden{$ts['link_id']}' value='" 
             . (int)$ts['actual_hours'] . "'>\n";
        echo "      <select name='new_status' id='statusSelect{$ts['link_id']}'>\n";
        echo "        <option value='pending' " 
             . ($ts['status'] === 'pending' ? 'selected' : '') . ">Pending</option>\n";
        echo "        <option value='in progress' " 
             . ($ts['status'] === 'in progress' ? 'selected' : '') . ">In Progress</option>\n";
        echo "        <option value='done' " 
             . ($ts['status'] === 'done' ? 'selected' : '') . ">Done</option>\n";
        echo "      </select>\n";
        echo "      <button type='submit'>Update</button>\n";
        echo "    </form>\n";
        echo "  </td>\n";

        // Actions
        echo "  <td>\n";
        // Edit (pass linkId, current status, currentEst if you want to show/hide in JS)
        echo "    <button type='button' onclick=\"openEditModal("
             . $ts['task_id'] . ", " 
             . $ts['link_id'] . ", '"
             . addslashes($ts['title']) . "', '"
             . addslashes($ts['description']) . "', '"
             . addslashes($ts['status']) . "', "
             . (int)$ts['team_estimated_hours']
             . ")\">Edit</button> |\n";

        // Subtask
        echo "    <button type='button' onclick=\"openSubtaskModal({$ts['task_id']})\">Subtask</button> |\n";

        // Delete
        echo "    <a href='?action=delete&id={$ts['task_id']}' onclick=\"return confirm('Delete Task #{$ts['task_id']}?');\">Delete</a>\n";
        echo "  </td>\n";

        echo "</tr>\n";

        printTasksDFS($ts['task_id'], $level+1, $tasksByParent);
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Tasks</title>
    <style>
        .error { color: red; }
        .success { color: green; }
        table { border-collapse: collapse; width:100%; }
        td, th { border:1px solid #ccc; padding: 8px; }
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
            width: 420px;
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
        label { font-weight: bold; display:block; margin-bottom:5px; }
    </style>
</head>
<body>
<h1>All Tasks</h1>

<?php
if ($errorMsg)   echo "<p class='error'>$errorMsg</p>";
if ($successMsg) echo "<p class='success'>$successMsg</p>";
?>

<button type="button" onclick="openCreateModal()">Create Task</button>
<br><br>

<table>
  <thead>
    <tr>
      <th>ID</th>
      <th>Project</th>
      <th>Title</th>
      <th>Description</th>
      <th>Est.</th>
      <th>Actual</th>
      <th>Status</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php printTasksDFS(0, 0, $tasksByParent); ?>
  </tbody>
</table>

<p><a href="../dashboard.php">Back to Dashboard</a></p>

<!-- CREATE TASK MODAL -->
<div class="modal-backdrop" id="createModalBackdrop">
  <div class="modal">
    <span class="close-btn" onclick="closeCreateModal()">x</span>
    <h2>Create Task</h2>
    <form method="post">
      <input type="hidden" name="crud_action" value="create">
      <input type="hidden" name="parent_id" value="0">

      <div class="form-group">
        <label for="create_title">Title:</label>
        <input type="text" name="create_title" id="create_title" required>
      </div>
      <div class="form-group">
        <label for="create_description">Description:</label>
        <textarea name="create_description" id="create_description"></textarea>
      </div>
      <!-- let user pick project -->
      <div class="form-group">
        <label for="create_project_id">Project:</label>
        <select name="create_project_id" id="create_project_id">
          <option value="0">-- Choose --</option>
          <?php foreach ($projects as $pr): ?>
            <option value="<?php echo $pr['id']; ?>">
              <?php echo htmlspecialchars($pr['title']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="create_estimated_hours">Estimated Hours:</label>
        <input type="number" name="create_estimated_hours" id="create_estimated_hours" min="0" value="0">
      </div>

      <button type="submit">Create Task</button>
    </form>
  </div>
</div>

<!-- CREATE SUBTASK MODAL -->
<div class="modal-backdrop" id="subtaskModalBackdrop">
  <div class="modal">
    <span class="close-btn" onclick="closeSubtaskModal()">x</span>
    <h2>Create Subtask</h2>
    <form method="post">
      <input type="hidden" name="crud_action" value="create">
      <!-- We'll store the parent's ID here -->
      <input type="hidden" name="parent_id" id="subtask_parent_id" value="0">

      <div class="form-group">
        <label>Title:</label>
        <input type="text" name="create_title" required>
      </div>
      <div class="form-group">
        <label>Description:</label>
        <textarea name="create_description"></textarea>
      </div>
      <!-- no project field, because same as parent's project -->
      <div class="form-group">
        <label for="create_estimated_hours_sub">Estimated Hours:</label>
        <input type="number" name="create_estimated_hours" id="create_estimated_hours_sub" min="0" value="0">
      </div>

      <button type="submit">Create Subtask</button>
    </form>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-backdrop" id="editModalBackdrop">
  <div class="modal">
    <span class="close-btn" onclick="closeEditModal()">x</span>
    <h2>Edit Task</h2>
    <form method="post">
      <input type="hidden" name="crud_action" value="update_task">
      <input type="hidden" name="edit_task_id" id="edit_task_id">
      <input type="hidden" name="edit_link_id" id="edit_link_id">

      <div class="form-group">
        <label for="edit_title">Title:</label>
        <input type="text" name="edit_title" id="edit_title" required>
      </div>
      <div class="form-group">
        <label for="edit_description">Description:</label>
        <textarea name="edit_description" id="edit_description"></textarea>
      </div>

      <!-- Show if status is pending -->
      <div class="form-group" id="editEstContainer" style="display:none;">
        <label for="edit_estimated_hours">Estimated Hours:</label>
        <input type="number" name="edit_estimated_hours" id="edit_estimated_hours" min="0" value="0">
      </div>

      <button type="submit">Update</button>
    </form>
  </div>
</div>

<script>
// CREATE (task)
function openCreateModal(){
  document.getElementById('create_title').value = '';
  document.getElementById('create_description').value = '';
  document.getElementById('create_project_id').value = 0;
  document.getElementById('create_estimated_hours').value = 0;

  document.getElementById('createModalBackdrop').classList.add('active');
}
function closeCreateModal(){
  document.getElementById('createModalBackdrop').classList.remove('active');
}

// SUBTASK
function openSubtaskModal(parentTaskId) {
  document.getElementById('subtask_parent_id').value = parentTaskId;
  document.getElementById('create_estimated_hours_sub').value = 0;
  document.getElementById('subtaskModalBackdrop').classList.add('active');
}
function closeSubtaskModal(){
  document.getElementById('subtaskModalBackdrop').classList.remove('active');
}

// EDIT
function openEditModal(taskId, linkId, title, description, status, est){
  document.getElementById('edit_task_id').value = taskId;
  document.getElementById('edit_link_id').value = linkId;
  document.getElementById('edit_title').value = title;
  document.getElementById('edit_description').value = description;
  document.getElementById('edit_estimated_hours').value = est || 0;

  // If pending => show the estimate field
  if (status === 'pending') {
    document.getElementById('editEstContainer').style.display = 'block';
  } else {
    document.getElementById('editEstContainer').style.display = 'none';
  }

  document.getElementById('editModalBackdrop').classList.add('active');
}
function closeEditModal(){
  document.getElementById('editModalBackdrop').classList.remove('active');
}

// STATUS
function handleStatusChange(linkId){
  const sel = document.getElementById("statusSelect"+linkId);
  const hiddenField = document.getElementById("actualHidden"+linkId);

  if (sel.value === 'done') {
    const hours = prompt("Enter actual hours (number):","0");
    if (hours === null) return false; 
    if (isNaN(hours) || hours < 0) {
      alert("Please enter a non-negative number.");
      return false;
    }
    hiddenField.value = hours;
  } else {
    hiddenField.value = 0;
  }
  return true;
}
</script>
</body>
</html>
