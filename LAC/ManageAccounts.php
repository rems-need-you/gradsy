<?php 
include('../partials-front/constantsss.php'); 
if (session_status() === PHP_SESSION_NONE) session_start();

// ===== GET DEPARTMENT DYNAMICALLY =====
if (!isset($_SESSION['department'])) {
    $teacher_id = $_SESSION['teacher_id'] ?? null;
    if ($teacher_id) {
        $stmt = $conn->prepare("SELECT department FROM teacher_account WHERE id = ?");
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        $stmt->bind_result($dep);
        if ($stmt->fetch()) $_SESSION['department'] = $dep;
        $stmt->close();
    }
}
$Department = $_SESSION['department'] ?? ''; // fallback

// ========== EMAIL CHECK ==========
function email_exists($conn, $email) {
    // Check both lac_account and teacher_account
    $tables = ['lac_account', 'teacher_account'];
    foreach ($tables as $tbl) {
        $stmt = $conn->prepare("SELECT 1 FROM $tbl WHERE Email = ? LIMIT 1");
        if (!$stmt) continue;
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        if ($exists) return true;
    }
    return false;
}

// ========== REDIRECT WITH MESSAGE ==========
function redirect_with_message($page, $type, $msg) {
    $_SESSION['flash_message'] = ["type" => $type, "content" => $msg];
    header("Location: " . $page);
    exit();
}

// ========== ADD TEACHER ==========
if (isset($_POST['submit'])) {
    $Email = trim($_POST['Email']);
    $Name  = trim($_POST['Name'] ?? ''); // read explicit name input
    $Password = $_POST['Password'];
    $AddDepartment = $_SESSION['department'] ?? ''; 
    $Status = "active";

    if (empty($AddDepartment)) {
        redirect_with_message("ManageAccounts.php", "error", "Department not set for the current user.");
    }
    if (empty($Name)) {
        redirect_with_message("ManageAccounts.php", "error", "Name is required.");
    }
    if (!filter_var($Email, FILTER_VALIDATE_EMAIL)) {
        redirect_with_message("ManageAccounts.php", "error", "Invalid email format.");
    }
    if (strlen($Password) < 10) {
        redirect_with_message("ManageAccounts.php", "error", "Password must be at least 10 characters long.");
    }
    if (email_exists($conn, $Email)) {
        redirect_with_message("ManageAccounts.php", "error", "This email already exists.");
    }

    // ⚠️ You can change this to password_hash($Password, PASSWORD_DEFAULT) for security
    $stmt = $conn->prepare("INSERT INTO teacher_account (name, Email, Password, Department, Status) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $Name, $Email, $Password, $AddDepartment, $Status);
    if ($stmt->execute()) {
        redirect_with_message("ManageAccounts.php", "success", "$AddDepartment teacher added successfully.");
    } else {
        redirect_with_message("ManageAccounts.php", "error", "Failed to add account: ".$stmt->error);
    }
}

// ========== ARCHIVE TEACHER ==========
if (isset($_GET['archive'])) {
    $email = $_GET['archive'];
    $stmt = $conn->prepare("UPDATE teacher_account SET Status = 'archived' WHERE Email = ?");
    $stmt->bind_param("s", $email);
    if ($stmt->execute()) {
        redirect_with_message("ManageAccounts.php", "success", "Teacher archived successfully.");
    } else {
        redirect_with_message("ManageAccounts.php", "error", "Failed to archive: ".$stmt->error);
    }
}

// ========== UNARCHIVE TEACHER ==========
if (isset($_GET['unarchive'])) {
    $email = $_GET['unarchive'];
    $stmt = $conn->prepare("UPDATE teacher_account SET Status = 'active' WHERE Email = ?");
    $stmt->bind_param("s", $email);
    if ($stmt->execute()) {
        redirect_with_message("ManageAccounts.php?view=archived", "success", "Teacher unarchived successfully.");
    } else {
        redirect_with_message("ManageAccounts.php?view=archived", "error", "Failed to unarchive: ".$stmt->error);
    }
}

// ========== FETCH TEACHERS BY DEPARTMENT ==========
$showArchived = isset($_GET['view']) && $_GET['view'] === 'archived';
$departments_to_fetch = [];

if ($Department === 'MAPEH') {
    $departments_to_fetch = ['Music', 'Art', 'P.E.', 'Health'];
} else {
    $departments_to_fetch = [$Department];
}

$dep_list = "'" . implode("', '", $departments_to_fetch) . "'";
$status_filter = $showArchived ? 'archived' : 'active';
// 3. Use IN clause to fetch teachers belonging to any of the specified departments
$query = "SELECT name, Email, Department, Status FROM teacher_account WHERE Department IN ($dep_list) AND Status = '$status_filter'";

// Execute query and check for failure
$teacher_account = $conn->query($query);
if ($teacher_account === false) {
    die("Query Failed: " . $conn->error . " | SQL: " . $query);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage <?= htmlspecialchars($Department) ?> Teachers</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

<style>
/* ======== BASE STYLES ======== */
body {
    font-family: 'Poppins', sans-serif;
    background: #f5f5f5;
    padding: 20px;
    color: #333;
    transition: filter 0.3s ease-in-out;
}
h1 {
    text-align: center;
    color: #000000ff;
}
.buttons {
    text-align: center;
    margin-bottom: 20px;
}
button, a.btn {
    background: #0468ebff;
    color: white;
    padding: 10px 16px;
    border: none;   
    border-radius: 8px;
    text-decoration: none;
    cursor: pointer;
    transition: 0.2s ease-in-out;
    font-weight: 500;
}
button:hover, a.btn:hover { background: #0c6b97ff; }
.btn-green { background: #2e698bff; }
.btn-green:hover { background: #086bddff; }

.account-container {
    display: flex;
    justify-content: center;
}
.account-card {
    background: white;
    padding: 20px;
    border-radius: 16px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    width: 90%;
    max-width: 900px;
}
table {
    width: 100%;
    border-collapse: collapse;
}
th, td {
    border-bottom: 1px solid #ddd;
    padding: 12px;
    text-align: center;
}
th { background: #fbff02ff; color: white; }
.status-active { color: green; font-weight: 600; }
.status-archived { color: red; font-weight: 600; }

/* ======= MODAL ======= */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    inset: 0;
    background-color: rgba(0,0,0,0.45);
    animation: fadeIn 0.3s ease-in-out;
}
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
.modal-content {
    background: #fff;
    margin: 8% auto;
    padding: 25px 30px;
    border-radius: 14px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.25);
    width: 90%;
    max-width: 450px;
    animation: slideIn 0.3s ease-out;
    position: relative;
}
@keyframes slideIn {
    from { transform: translateY(-40px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
.modal-content h2 {
    text-align: center;
    color: #b60303;
    margin-bottom: 20px;
}
.modal-content label {
    display: block;
    font-weight: 600;
    margin-top: 10px;
}
.modal-content input[type="email"] {
    width: 95%;
    padding: 10px 12px;
    margin-top: 6px;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 14px;
    transition: 0.2s;
}
.modal-content input:focus {
    border-color: #b60303;
    outline: none;
    box-shadow: 0 0 3px rgba(182,3,3,0.3);
}

/* PASSWORD EYE ICON */
.password-container {
    position: relative;
    width: 100%;
}

.password-container input {
    width: 100%;
    padding: 10px 42px 10px 12px;
    border: 1px solid #ccc;
    border-radius: 8px;
    font-size: 15px;
    transition: border 0.3s, box-shadow 0.3s;
    box-sizing: border-box;
}

.password-container input:focus {
    border-color: #b60303;
    box-shadow: 0 0 6px rgba(182,3,3,0.3);
    outline: none;
}

/* Eye Icon - stays fixed, no layout shift */
.toggle-password {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #999;
    font-size: 1.2rem;
    cursor: pointer;
    transition: color 0.2s;
    user-select: none;
    background: transparent;
}

.toggle-password i {
    pointer-events: none; /* Prevent flickering */
}

.toggle-password:hover {
    color: #b60303;
}

/* CLOSE BUTTON */
.close-btn {
    position: absolute;
    top: 10px;
    right: 15px;
    color: #b60303;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    transition: color 0.2s;
}
.close-btn:hover { color: #ff4d4d; }

.form-actions {
    margin-top: 18px;
    display: flex;
    justify-content: space-between;
    gap: 10px;
}
.form-actions button {
    flex: 1;
    border-radius: 6px;
    font-size: 15px;
}

/* Flash messages */
.flash {
    margin: 15px auto;
    padding: 10px 16px;
    width: fit-content;
    border-radius: 6px;
    font-weight: 500;
}
.flash.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}
.flash.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
</style>
</head>
<body>

<?php
if (!empty($_SESSION['flash_message'])) {
    $fm = $_SESSION['flash_message'];
    $type = ($fm['type'] === 'success') ? 'success' : 'error';
    echo "<div class='flash {$type}'>" . htmlspecialchars($fm['content']) . "</div>";
    unset($_SESSION['flash_message']);
}
?>

<div class="buttons">
    <button onclick="openAddUserModal()">Add <?= htmlspecialchars($Department) ?> Teacher</button>
    <?php if ($showArchived): ?>
        <a href="ManageAccounts.php" class="btn">View Active Teachers</a>
    <?php else: ?>
        <a href="ManageAccounts.php?view=archived" class="btn">View Archived Teachers</a>
    <?php endif; ?>
</div>

<!-- ADD TEACHER MODAL -->
<div id="userModal" class="modal">
  <div class="modal-content">
    <span onclick="closeModal()" class="close-btn">&times;</span>
    <h2>Add <?= htmlspecialchars($Department) ?> Teacher</h2>

    <form method="POST" id="userForm">
      <label>Full Name:</label>
      <input type="text" name="Name" id="Name" required style="width:95%; padding:10px 12px; margin-top:6px; border:1px solid #ccc; border-radius:6px;" />

      <label>Email:</label>
      <input type="email" name="Email" id="Email" required>

      <label>Password:</label>
      <div class="password-container">
        <input type="password" name="Password" id="Password" required>
        <span class="toggle-password" data-target="Password" title="Show/Hide Password">
          <i class="fa-solid fa-eye"></i>
        </span>
      </div>
      <small style="color:#555;">(At least 10+ characters)</small>

      <div class="form-actions">
        <button type="submit" name="submit">Add</button>
        <button type="button" onclick="closeModal()" style="background:#999;">Cancel</button>
      </div>
    </form>
  </div>
</div>

<h1><?= $showArchived ? "Archived {$Department} Teachers" : "Active {$Department} Teachers" ?></h1>
<div class="account-container">
    <div class="account-card">
        <table>
            <tr><th>Name</th><th>Email</th><th>Password</th><th>Department</th><th>Status</th><th>Action</th></tr>
            <?php while ($user = $teacher_account->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($user['name'] ?? '') ?></td>
                <td><?= htmlspecialchars($user['Email']) ?></td>
                <td>************</td>
                <td><?= htmlspecialchars($user['Department']) ?></td>
                <td class="status-<?= $user['Status'] ?>"><?= ucfirst($user['Status']) ?></td>
                <td>
                    <?php if ($user['Status'] === 'active'): ?>
                        <a href="?archive=<?= urlencode($user['Email']) ?>" class="btn" 
                            onclick="return confirm('Archive this teacher?')">Archive</a>
                    <?php else: ?>
                        <a href="?unarchive=<?= urlencode($user['Email']) ?>" class="btn btn-green" 
                            onclick="return confirm('Unarchive this teacher?')">Unarchive</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>

<script>
function closeModal() {
    document.getElementById('userModal').style.display = 'none';
    document.body.classList.remove('blurred');
    document.getElementById('userForm').reset();
}
function openAddUserModal() {
    document.getElementById('userModal').style.display = 'block';
    document.body.classList.add('blurred');
    document.getElementById('Email').focus();
}
(function() {
    function toggleButtonActivate(btn) {
        const targetId = btn.dataset.target;
        const input = document.getElementById(targetId);
        const icon = btn.querySelector('i');
        if (!input || !icon) return;

        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.toggle-password');
        if (!btn) return;
        toggleButtonActivate(btn);
    });

    window.onclick = function(event) {
        const modal = document.getElementById('userModal');
        if (event.target === modal) closeModal();
    };

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });
})();
</script>

</body>
</html>
