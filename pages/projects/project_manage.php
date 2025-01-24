<?php
// File: pages/projects/project_manage.php
session_start();
require '../../includes/auth_check.php';  // or your login-check
include '../../includes/db.php';

$projectId = (int)$_GET['id'];
$userId   = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'user';  // could be 'admin', 'team_leader', etc.

// We'll get all teams where this user is either the leader or an admin user
if ($userRole === 'admin') {
  $canManage = true;
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
    $resT = $stmtT->get_result();
    $myTeams = [];
    while ($rowT = $resT->fetch_assoc()) {
        $myTeams[] = $rowT;
    }
    $stmtT->close();

    $projectTeams = [];
    $sqlPT = "SELECT team_id FROM project_team WHERE project_id = ?";
    $stmtT = $conn->prepare($sqlPT);
    $stmtT->bind_param("i", $projectId);
    $stmtT->execute();
    $resT = $stmtT->get_result();
    while ($rowPT = $resT->fetch_assoc()) {
        $projectTeams[] = $rowPT['team_id'];
    }
    $stmtT->close();

    $canManage = false;
    foreach ($myTeams as $mt) {
        if (in_array($mt['id'], $projectTeams)) {
            $canManage = true;
            break;
        }
    }
}
if (!$canManage) {
    die("Access denied");
}

// 1) Validate project in URL
if (!isset($_GET['id'])) {
    die("No project specified.");
}
$projectId = (int)$_GET['id'];

// Check project existence
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
 * 2) Handle CREATE / EDIT / UPDATE STATUS / DELETE
 *    for tasks belonging to THIS single project
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $crudAction = $_POST['crud_action'] ?? '';

    // A) CREATE (sub)task
    if ($crudAction === 'create') {
        $title       = $_POST['create_title']        ?? '';
        $description = $_POST['create_description']  ?? '';
        $parentId    = (int)($_POST['parent_id']      ?? 0);
        // new: user sets initial estimated hours
        $estHours    = (int)($_POST['create_estimated_hours'] ?? 0);

        if (empty($title)) {
            $errorMsg = "Task title is required.";
        } else {
            if (empty($parentId)) {
                $parentId = null;
            }

            // 1) Insert into tasks
            $stmtT = $conn->prepare("
                INSERT INTO tasks (title, description, parent_id)
                VALUES (?, ?, ?)
            ");
            $stmtT->bind_param("ssi", $title, $description, $parentId);
            if ($stmtT->execute()) {
                $newTaskId = $stmtT->insert_id;
                $stmtT->close();

                // 2) Insert into user_project_task => link to this project
                $linkSql = "
                    INSERT INTO user_project_task
                    (project_id, task_id, team_estimated_hours, actual_hours, status)
                    VALUES (?, ?, ?, 0, 'version 1')
                ";
                $stmtLink = $conn->prepare($linkSql);
                $stmtLink->bind_param("iii", $projectId, $newTaskId, $estHours);
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
    // B) EDIT an existing task (title/description, and maybe est hours if pending)
    elseif ($crudAction === 'update_task') {
        $taskId    = (int)($_POST['edit_task_id']         ?? 0);
        $title     = $_POST['edit_title']                 ?? '';
        $desc      = $_POST['edit_description']           ?? '';
        $linkId    = (int)($_POST['edit_link_id']         ?? 0);
        $newEst    = (int)($_POST['edit_estimated_hours'] ?? 0);

        if ($taskId < 1) {
            $errorMsg = "Invalid task ID.";
        } elseif (empty($title)) {
            $errorMsg = "Task title cannot be empty.";
        } else {
            // 1) Update tasks table for title/description
            $updT = $conn->prepare("
                UPDATE tasks
                SET title = ?, description = ?
                WHERE id = ?
            ");
            $updT->bind_param("ssi", $title, $desc, $taskId);
            if (!$updT->execute()) {
                $errorMsg = "Error updating task: " . $updT->error;
            }
            $updT->close();

            // 2) If status = 'pending', allow changing the estimate
            //    So we fetch the current status from user_project_task:
            $sqlSt = "SELECT status FROM user_project_task WHERE id=? LIMIT 1";
            $stmtSt = $conn->prepare($sqlSt);
            $stmtSt->bind_param("i", $linkId);
            $stmtSt->execute();
            $resSt = $stmtSt->get_result();
            $rowSt = $resSt->fetch_assoc();
            $stmtSt->close();

            if ($rowSt && $rowSt['status'] === 'version 1') {
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
                // either no row or status != pending
                $successMsg = "Task #$taskId updated (title/desc). Estimate not changed (task not pending).";
            }
        }
    }
    // C) UPDATE STATUS inline (prompt for actual hours if done)
    elseif ($crudAction === 'update_status') {
        $linkId    = (int)($_POST['link_id']          ?? 0);
        $newStatus = $_POST['new_status']            ?? 'version 1';
        $actual    = (int)($_POST['new_actual_hours'] ?? 0);

        if ($linkId < 1) {
            $errorMsg = "Invalid link ID for status update.";
        } else {
            if ($newStatus !== 'version 3') {
                $actual = 0;  // reset actual if not done
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

// 3) DELETE
$action = $_GET['action'] ?? '';
$delTaskId = (int)($_GET['task_id'] ?? 0);
if ($action === 'delete' && $delTaskId > 0) {
    // If ON DELETE CASCADE is set, link row is removed automatically
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
 * 4) Fetch tasks for this project (including parent_id) 
 *    We'll build $tasksByParent and do DFS
 */
$tasksList = [];
$sql = "
    SELECT
      t.id AS task_id,
      t.parent_id,
      t.title,
      t.description,
      upt.id AS link_id,
      upt.team_estimated_hours,
      upt.actual_hours,
      upt.status
    FROM user_project_task upt
    JOIN tasks t ON upt.task_id = t.id
    WHERE upt.project_id = ?
    ORDER BY t.id ASC
";
$stmtList = $conn->prepare($sql);
$stmtList->bind_param("i", $projectId);
$stmtList->execute();
$resList = $stmtList->get_result();
while ($row = $resList->fetch_assoc()) {
    $tasksList[] = $row;
}
$stmtList->close();

// Build $tasksByParent array
$tasksByParent = [];
foreach ($tasksList as $row) {
    $p = $row['parent_id'] ?? 0; // if NULL => 0
    $tasksByParent[$p][] = $row;
}
// Sort each group if needed
foreach ($tasksByParent as &$grp) {
    usort($grp, function($a, $b) {
        return $a['task_id'] - $b['task_id'];
    });
}
unset($grp);

/**
 * 5) Recursive print function
 */
function printTasksDFS($parentId, $level, $tasksByParent) {
    if (empty($tasksByParent[$parentId])) return;
    foreach ($tasksByParent[$parentId] as $ts) {
        $indent = str_repeat('— ', $level);

        echo "<tr>\n";
        // ID
        echo "  <td>{$ts['task_id']}</td>\n";
        // Title
        echo "  <td>" . $indent . htmlspecialchars($ts['title']) . "</td>\n";
        // Description
        echo "  <td>" . nl2br(htmlspecialchars($ts['description'])) . "</td>\n";
        // Team Est
        echo "  <td>" . (int)$ts['team_estimated_hours'] . "</td>\n";
        // Actual
        echo "  <td>" . (int)$ts['actual_hours'] . "</td>\n";

        // Status cell
        echo "  <td>\n";
        echo "    <form method='post' style='margin:0;' onsubmit='return handleStatusChange({$ts['link_id']})'>\n";
        echo "      <input type='hidden' name='crud_action' value='update_status'>\n";
        echo "      <input type='hidden' name='link_id' value='{$ts['link_id']}'>\n";
        echo "      <input type='hidden' name='new_actual_hours' id='actualHidden{$ts['link_id']}' value='" . (int)$ts['actual_hours'] . "'>\n";
        echo "      <select name='new_status' id='statusSelect{$ts['link_id']}'>\n";
        echo "        <option value='version 1' " 
             . ($ts['status'] === 'version 1' ? 'selected' : '') . ">Version 1</option>\n";
        echo "        <option value='version 2' " 
             . ($ts['status'] === 'version 2' ? 'selected' : '') . ">Version 2</option>\n";
        echo "        <option value='version 3' " 
             . ($ts['status'] === 'version 3' ? 'selected' : '') . ">Version 3</option>\n";
        echo "      </select>\n";
        echo "      <button type='submit'>Update</button>\n";
        echo "    </form>\n";
        echo "  </td>\n";

        // Actions
        echo "  <td>\n";
        // Edit => pass linkId, status, and currentEst so we can handle in JS
        echo "    <button type='button' onclick=\"openEditModal("
             . $ts['task_id'] . ", "
             . $ts['link_id'] . ", '"
             . addslashes($ts['title']) . "', '"
             . addslashes($ts['description']) . "', '"
             . addslashes($ts['status']) . "', "
             . (int)$ts['team_estimated_hours']
             . ")\">Edit</button> \n";

        // Subtask
        echo "    <button type='button' onclick=\"openSubtaskModal({$ts['task_id']})\">Subtask</button> \n";

        // Delete
        echo "    <a href='?action=delete&task_id={$ts['task_id']}&id={$_GET['id']}'"
             . " onclick=\"return confirm('Delete Task #{$ts['task_id']}?');\">"
             . "Delete</a>\n";
        echo "  </td>\n";

        echo "</tr>\n";

        // Recurse
        printTasksDFS($ts['task_id'], $level+1, $tasksByParent);
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Tasks for "<?php echo htmlspecialchars($project['title']); ?>"</title>
    <link rel="stylesheet" href="../../css/projects/project_manage.css">
</head>
<body>
<h1>Manage Tasks for Project: <?php echo htmlspecialchars($project['title']); ?></h1>

<?php
if ($errorMsg)   echo "<p class='error'>$errorMsg</p>";
if ($successMsg) echo "<p class='success'>$successMsg</p>";
?>

<div class="create-task-container">
    <button id="create-task" type="button" onclick="openCreateModal()">Create Task</button>
</div>

<table>
  <thead>
    <tr>
      <th>ID</th>
      <th>Title</th>
      <th>Description</th>
      <th>Est</th>
      <th>Actual</th>
      <th>Status</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php printTasksDFS(0, 0, $tasksByParent); ?>
  </tbody>
</table>

<br>
<p><a href="projects.php" class="back-link"> < Back to Projects</a></p>

<!-- CREATE / SUBTASK MODAL -->
<div class="modal-backdrop" id="createModalBackdrop">
  <div class="modal">
    <span class="close-btn" onclick="closeCreateModal()">x</span>
    <h2>Create Task (or Subtask)</h2>
    <form method="post">
      <input type="hidden" name="crud_action" value="create">
      <input type="hidden" name="parent_id" id="create_parent_id" value="0">

      <div class="form-group">
        <label for="create_title">Title:</label>
        <input type="text" name="create_title" id="create_title" required>
      </div>
      <div class="form-group">
        <label for="create_description">Description:</label>
        <textarea name="create_description" id="create_description"></textarea>
      </div>
      <!-- Estimated Hours -->
      <div class="form-group">
        <label for="create_estimated_hours">Estimated Hours:</label>
        <input type="number" name="create_estimated_hours" id="create_estimated_hours" min="0" value="0">
      </div>
      <button id = "create-task" type="submit">Create</button>
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
      <!-- Link ID to update the estimate if pending -->
      <input type="hidden" name="edit_link_id" id="edit_link_id">

      <div class="form-group">
        <label for="edit_title">Title:</label>
        <input type="text" name="edit_title" id="edit_title" required>
      </div>
      <div class="form-group">
        <label for="edit_description">Description:</label>
        <textarea name="edit_description" id="edit_description"></textarea>
      </div>
      <!-- Only show if status == 'pending' (JS toggles it) -->
      <div class="form-group" id="editEstContainer" style="display:none;">
        <label for="edit_estimated_hours">Estimated Hours:</label>
        <input type="number" name="edit_estimated_hours" id="edit_estimated_hours" min="0" value="0">
      </div>

      <button type="submit">Update</button>
    </form>
  </div>
</div>

<script>
// CREATE
function openCreateModal(){
  document.getElementById('create_parent_id').value = 0;
  document.getElementById('create_title').value = '';
  document.getElementById('create_description').value = '';
  document.getElementById('create_estimated_hours').value = 0;
  document.getElementById('createModalBackdrop').classList.add('active');
}
function closeCreateModal(){
  document.getElementById('createModalBackdrop').classList.remove('active');
}
// SUBTASK
function openSubtaskModal(parentId) {
  openCreateModal();
  document.getElementById('create_parent_id').value = parentId;
}

// EDIT
function openEditModal(taskId, linkId, title, desc, status, est) {
  document.getElementById('edit_task_id').value = taskId;
  document.getElementById('edit_link_id').value = linkId;
  document.getElementById('edit_title').value = title;
  document.getElementById('edit_description').value = desc;
  document.getElementById('edit_estimated_hours').value = est || 0;

  // If status == 'pending', show the estimated hours field
  if (status === 'version 1') {
    document.getElementById('editEstContainer').style.display = 'block';
  } else {
    document.getElementById('editEstContainer').style.display = 'none';
  }

  document.getElementById('editModalBackdrop').classList.add('active');
}
function closeEditModal(){
  document.getElementById('editModalBackdrop').classList.remove('active');
}

// If user sets status=done => prompt for actual hours
function handleStatusChange(linkId){
  var sel       = document.getElementById("statusSelect"+linkId);
  var hiddenFld = document.getElementById("actualHidden"+linkId);

  if (sel.value === 'version 3') {
    var hours = prompt("Enter actual hours:", "0");
    if (hours === null) { 
      return false; // user canceled
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
