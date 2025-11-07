<?php 
include('../partials-front/constantsss.php'); 
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// ---------- UNIVERSAL EMAIL CHECK ----------
function email_exists($conn, $email, $excludeEmail = '') {
    $tables = [
        "lac_account",
        "teacher_account",
        "parent_account",
        "student_affairs_account"
    ];
    foreach ($tables as $tbl) {
        if ($excludeEmail) {
            $stmt = $conn->prepare("SELECT 1 FROM $tbl WHERE Email = ? AND Email != ?");
            $stmt->bind_param("ss", $email, $excludeEmail);
        } else {
            $stmt = $conn->prepare("SELECT 1 FROM $tbl WHERE Email = ?");
            $stmt->bind_param("s", $email);
        }
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) return true;
        $stmt->close();
    }
    return false;
}

// ---------- FLASH REDIRECT ----------
function redirect_with_message($page, $message_type, $message_content, $openModal = false, $modalType = "", $oldEmail = "", $department = "") {
    $_SESSION['flash_message'] = [
        "type" => $message_type,
        "content" => $message_content,
        "openModal" => $openModal,
        "modalType" => $modalType,
        "oldEmail" => $oldEmail,
        "department" => $department
    ];
    header("Location: " . $page);
    exit();
}

if (isset($_POST['submit'])) {
    $Password    = $_POST['Password'];
    $action      = $_POST['action'];
    $oldEmail    = $_POST['oldEmail'] ?? '';
    $type        = $_POST['type']; 
    $Email       = $_POST['Email'];
    $Department  = $_POST['Department'] ?? null;

    $tables = [
        "lac"             => "lac_account",
        "teacher"         => "teacher_account",
        "parent"          => "parent_account",
        "student_affairs" => "student_affairs_account"
    ];
    if (!isset($tables[$type])) redirect_with_message("ManageAccounts.php","error","Invalid account type.");
    $table = $tables[$type];

    if (!filter_var($Email, FILTER_VALIDATE_EMAIL)) {
        redirect_with_message("ManageAccounts.php","error","Invalid email format.", true, $type, $oldEmail, $Department);
    }
    if (!empty($Password) && strlen($Password) < 10) {
        redirect_with_message("ManageAccounts.php","error","Password must be at least 10 characters long.", true, $type, $oldEmail, $Department);
    }
    if ($type !== "parent" && empty($Department)) {
        redirect_with_message("ManageAccounts.php","error","Department is required.", true, $type, $oldEmail, $Department);
    }

    // ---------- ADD ----------
    if ($action === 'add') {
        if (empty($Password)) redirect_with_message("ManageAccounts.php","error","Password is required for new account.", true, $type);
        if (email_exists($conn, $Email)) {
            redirect_with_message("ManageAccounts.php","error","This email already exists in another account.", true, $type);
        }

        if ($type === "parent") {
            $stmt = $conn->prepare("INSERT INTO $table (Email, Password) VALUES (?, ?)");
            $stmt->bind_param("ss", $Email, $Password);
        } else {
            $stmt = $conn->prepare("INSERT INTO $table (Email, Password, Department) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $Email, $Password, $Department);
        }
        if ($stmt->execute()) {
            redirect_with_message("ManageAccounts.php","success",ucfirst(str_replace("_"," ",$type))." account added successfully.");
        } else {
            redirect_with_message("ManageAccounts.php","error","Failed to add account: ".$stmt->error, true, $type);
        }
    }

    // ---------- UPDATE ----------
    elseif ($action === 'update' && !empty($oldEmail)) {
        if (email_exists($conn, $Email, $oldEmail)) {
            redirect_with_message("ManageAccounts.php","error","This email already exists in another account.", true, $type, $oldEmail, $Department);
        }

        if ($type === "parent") {
            if (!empty($Password)) {
                $stmt = $conn->prepare("UPDATE $table SET Email = ?, Password = ? WHERE Email = ?");
                $stmt->bind_param("sss",$Email,$Password,$oldEmail);
            } else {
                $stmt = $conn->prepare("UPDATE $table SET Email = ? WHERE Email = ?");
                $stmt->bind_param("ss",$Email,$oldEmail);
            }
        } else {
            if (!empty($Password)) {
                $stmt = $conn->prepare("UPDATE $table SET Email = ?, Password = ?, Department = ? WHERE Email = ?");
                $stmt->bind_param("ssss",$Email,$Password,$Department,$oldEmail);
            } else {
                $stmt = $conn->prepare("UPDATE $table SET Email = ?, Department = ? WHERE Email = ?");
                $stmt->bind_param("sss",$Email,$Department,$oldEmail);
            }
        }
        if ($stmt->execute()) {
            redirect_with_message("ManageAccounts.php","success",ucfirst(str_replace("_"," ",$type))." account updated successfully.");
        } else {
            redirect_with_message("ManageAccounts.php","error","Failed to update account: ".$stmt->error, true, $type, $oldEmail, $Department);
        }
    }
}

// ---------- DELETE ----------
if (isset($_GET['delete']) && isset($_GET['type'])) {
    $email = $_GET['delete'];
    $type  = $_GET['type'];

    $tables = [
        "lac"             => "lac_account",
        "teacher"         => "teacher_account",
        "parent"          => "parent_account",
        "student_affairs" => "student_affairs_account"
    ];
    if (!isset($tables[$type])) redirect_with_message("ManageAccounts.php","error","Invalid account type.");
    $table = $tables[$type];

    $stmt = $conn->prepare("DELETE FROM $table WHERE Email = ?");
    $stmt->bind_param("s",$email);
    if ($stmt->execute()) {
        redirect_with_message("ManageAccounts.php","success",ucfirst(str_replace("_"," ",$type))." account deleted successfully.");
    } else {
        redirect_with_message("ManageAccounts.php","error","Failed to delete account: ".$stmt->error);
    }
    exit();
}

// ---------- FETCH ----------
$userType = $_SESSION['type'] ?? '';
$userDept = $_SESSION['department'] ?? '';

$where = ($userType === 'teacher') ? "WHERE Department = '".$conn->real_escape_string($userDept)."'" : "";

$lac_account             = $conn->query("SELECT Email, Department FROM lac_account $where");
$teacher_account         = $conn->query("SELECT Email, Department FROM teacher_account $where");

// Parent may extra info (halimbawa StudentID para ma-link sa anak)
// adjust mo depende sa totoong column names mo
$parent_account          = $conn->query("SELECT Email, StudentName, Section, Grade FROM parent_account");

// Student Affairs wala nang Department
$student_affairs_account = $conn->query("SELECT Email FROM student_affairs_account");

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Accounts</title>
<link rel="stylesheet" href="../css/lmanage.css">
</head>
<body>

<div class="buttons">
    <button onclick="openAddUserModal('lac')">Add Learning Area Chair</button>
    <button onclick="openAddUserModal('teacher')">Add Teacher</button>
    <button onclick="openAddUserModal('parent')">Add Parent</button>
    <button onclick="openAddUserModal('student_affairs')">Add Student Affairs</button>
</div>

<div id="userModal" class="modal">
    <span onclick="closeModal()" class="close-btn">[X]</span>
    <h2 id="modalTitle">Add User</h2>
    <form method="POST" id="userForm">
        <input type="hidden" name="action" id="formAction" value="add">
        <input type="hidden" name="oldEmail" id="oldEmail" value="">
        <input type="hidden" name="type" id="userType" value="">

        <label>Email:</label>
        <input type="email" name="Email" id="Email" required>

        <label>Password:</label>
        <input type="password" name="Password" id="Password">
        <small>(At least 10+ chars)</small>

        <!-- Teacher/LAC field -->
        <div id="deptField" style="display:none;">
            <label>Department:</label>
            <select name="Department" id="Department">
                <option value="">-- Select Department --</option>
                <option value="English">English</option>
                <option value="Filipino">Filipino</option>
                <option value="Mathematics">Mathematics</option>
                <option value="Science">Science</option>
                <option value="Araling Panlipunan (Social Studies)">Araling Panlipunan (Social Studies)</option>
                <option value="Edukasyon sa Pagpapakatao (EsP)">Edukasyon sa Pagpapakatao (EsP)</option>
                <option value="Christian Living Education">Christian Living Education</option>
                <option value="Music">Music</option>
                <option value="Arts">Arts</option>
                <option value="Physical Education">Physical Education</option>
                <option value="Health">Health</option>
                <option value="Edukasyong Pantahanan at Pangkabuhayan (EPP)">Edukasyong Pantahanan at Pangkabuhayan (EPP)</option>
            </select>
        </div>

        <!-- Parent-only fields -->
        <div id="parentFields" style="display:none;">
            <label>Student Name:</label>
            <input type="text" name="StudentName" id="StudentName">

            <label>Section:</label>
            <input type="text" name="Section" id="Section">

            <label>Grade:</label>
            <input type="text" name="Grade" id="Grade">
        </div>

        <div class="form-actions">
            <button type="submit" name="submit" id="submitBtn">Add</button>
            <button type="button" onclick="closeModal()">Cancel</button>
        </div>
    </form>
</div>


<h1>Manage Accounts</h1>
<div class="account-container">

    <div class="account-card">
        <h2>Learning Area Chair Accounts</h2>
        <table>
            <tr><th>Email</th><th>Password</th><th>Department</th><th>Actions</th></tr>
            <?php while ($user=$lac_account->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($user['Email']) ?></td>
                <td>************</td>
                <td><?= htmlspecialchars($user['Department']) ?></td>
                <td>
                    <button onclick="openEditUserModal('lac','<?= addslashes($user['Email']) ?>','<?= addslashes($user['Department']) ?>')">Update</button>
                    <button onclick="if(confirm('Are you sure?')) window.location.href='?delete=<?= urlencode($user['Email']) ?>&type=lac'">Delete</button>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>

    <div class="account-card">
        <h2>Teacher Accounts</h2>
        <table>
            <tr><th>Email</th><th>Password</th><th>Department</th><th>Actions</th></tr>
            <?php while ($user=$teacher_account->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($user['Email']) ?></td>
                <td>************</td>
                <td><?= htmlspecialchars($user['Department']) ?></td>
                <td>
                    <button onclick="openEditUserModal('teacher','<?= addslashes($user['Email']) ?>','<?= addslashes($user['Department']) ?>')">Update</button>
                    <button onclick="if(confirm('Are you sure?')) window.location.href='?delete=<?= urlencode($user['Email']) ?>&type=teacher'">Delete</button>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>

    <div class="account-card">
    <h2>Parent Accounts</h2>
    <table>
        <tr><th>Email</th><th>Password</th><th>Student Name</th><th>Section</th><th>Grade</th><th>Actions</th></tr>
        <?php while ($user=$parent_account->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($user['Email']) ?></td>
            <td>************</td>
            <td><?= htmlspecialchars($user['StudentName']) ?></td>
            <td><?= htmlspecialchars($user['Section']) ?></td>
            <td><?= htmlspecialchars($user['Grade']) ?></td>
            <td>
                <button onclick="openEditUserModal('parent','<?= addslashes($user['Email']) ?>','')">Update</button>
                <button onclick="if(confirm('Are you sure?')) window.location.href='?delete=<?= urlencode($user['Email']) ?>&type=parent'">Delete</button>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>

    <div class="account-card">
    <h2>Student Affairs Accounts</h2>
    <table>
        <tr><th>Email</th><th>Password</th><th>Actions</th></tr>
        <?php while ($user=$student_affairs_account->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($user['Email']) ?></td>
            <td>************</td>
            <td>
                <button onclick="openEditUserModal('student_affairs','<?= addslashes($user['Email']) ?>','')">Update</button>
                <button onclick="if(confirm('Are you sure?')) window.location.href='?delete=<?= urlencode($user['Email']) ?>&type=student_affairs'">Delete</button>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>


</div>

<script>
function closeModal() {
    document.getElementById('userModal').style.display = 'none';
    document.getElementById('userForm').reset();
}

function openAddUserModal(type) {
    document.getElementById('userModal').style.display = 'block';
    document.getElementById('modalTitle').textContent = 'Add ' + type.replace('_',' ').replace(/\b\w/g, l => l.toUpperCase());
    document.getElementById('submitBtn').textContent = 'Add';
    document.getElementById('formAction').value = 'add';
    document.getElementById('oldEmail').value = '';
    document.getElementById('userType').value = type;
    document.getElementById('Email').value = '';
    document.getElementById('Password').required = true;
    document.getElementById('Password').value = '';
    document.getElementById('Department').selectedIndex = 0;

    // toggle fields
    if (type === "parent") {
        document.getElementById('deptField').style.display = "none";
        document.getElementById('parentFields').style.display = "block";
    } else if (type === "student_affairs") {
        document.getElementById('deptField').style.display = "none";
        document.getElementById('parentFields').style.display = "none";
    } else {
        document.getElementById('deptField').style.display = "block";
        document.getElementById('parentFields').style.display = "none";
    }
}

function openEditUserModal(type,email,extra1="",extra2="",extra3="") {
    document.getElementById('userModal').style.display = 'block';
    document.getElementById('modalTitle').textContent = 'Update ' + type.replace('_',' ').replace(/\b\w/g, l => l.toUpperCase());
    document.getElementById('submitBtn').textContent = 'Update';
    document.getElementById('formAction').value = 'update';
    document.getElementById('oldEmail').value = email;
    document.getElementById('userType').value = type;
    document.getElementById('Email').value = email;
    document.getElementById('Password').value = '';
    document.getElementById('Password').required = false;

    // toggle fields + fill values
    if (type === "parent") {
        document.getElementById('deptField').style.display = "none";
        document.getElementById('parentFields').style.display = "block";
        document.getElementById('StudentName').value = extra1;
        document.getElementById('Section').value = extra2;
        document.getElementById('Grade').value = extra3;
    } else if (type === "student_affairs") {
        document.getElementById('deptField').style.display = "none";
        document.getElementById('parentFields').style.display = "none";
    } else {
        document.getElementById('deptField').style.display = "block";
        document.getElementById('parentFields').style.display = "none";
        let deptSelect = document.getElementById('Department');
        for (let i = 0; i < deptSelect.options.length; i++) {
            if (deptSelect.options[i].value === extra1) {
                deptSelect.selectedIndex = i;
                break;
            }
        }
    }
}
</script>


</body>
</html>
