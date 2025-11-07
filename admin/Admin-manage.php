<?php 
include ('partials/constants.php');

if (session_status() === PHP_SESSION_NONE) session_start();

// ---------- UNIVERSAL EMAIL CHECK (Walang Pagbabago) ----------
function email_exists($conn, $email, $excludeEmail = '') {
    $tables = [
        "lac_account",
        "parent_account",
        "student_affairs_account",
        "admins" // changed from admin_account to admins
    ];

    foreach ($tables as $tbl) {
        $sql = "SELECT 1 FROM $tbl WHERE Email = ?";
        $params = ["s", $email];
        
        if ($excludeEmail) {
            $sql .= " AND Email != ?";
            $params[0] .= "s";
            $params[] = $excludeEmail;
        }
        
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            continue;
        }
        
        call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt], $params));

        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            mysqli_stmt_close($stmt);
            return true;
        }
        mysqli_stmt_close($stmt);
    }
    return false;
}

// ---------- FLASH REDIRECT (Inayos ang Parent Parameters) ----------
function redirect_with_message($page, $type, $content, $openModal = false, $modalType = "", $oldEmail = "", $department = "", $studentName = "", $section = "", $grade = "") {
    $_SESSION['flash_message'] = [
        "type" => $type,
        "content" => $content,
        "openModal" => $openModal ? 1 : 0,
        "modalType" => $modalType,
        "oldEmail" => $oldEmail,
        "department" => $department,
        "studentName" => $studentName, // Dinagdag ito
        "section" => $section,          // Dinagdag ito
        "grade" => $grade,              // Dinagdag ito
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
    $StudentName = $_POST['StudentName'] ?? null;
    $Section     = $_POST['Section'] ?? null;
    $Grade       = $_POST['Grade'] ?? null;
    
    // 🔥 PAGBABAGO DITO: Ginawang plain text ulit.
    $hashedPassword = !empty($Password) ? $Password : null; 

    $tables = [
        "lac"             => "lac_account",
        "parent"          => "parent_account",
        "student_affairs" => "student_affairs_account",
        "admin"           => "admins"  // changed from admin_account to admins
    ];
    
    $redirectPage = "Admin-manage.php";

    // --- Input Validation (Inayos ang Redirect Parameters) ---
    if (!isset($tables[$type])) {
        redirect_with_message($redirectPage, "error", "Invalid account type.");
    }
    $table = $tables[$type];

    // Ginawang reusable ang redirect, pinapasa ang lahat ng field para bumalik sa modal
    $redirect_params = [$redirectPage, "error", "", true, $type, $oldEmail, $Department, $StudentName, $Section, $Grade];

    if (!filter_var($Email, FILTER_VALIDATE_EMAIL)) {
        $redirect_params[2] = "Invalid email format.";
        redirect_with_message(...$redirect_params);
    }
    if (!empty($Password) && strlen($Password) < 10) { 
        $redirect_params[2] = "Password must be at least 10 characters long.";
        redirect_with_message(...$redirect_params);
    }
    if ($type === "lac" && empty($Department)) {
        $redirect_params[2] = "Department is required for LAC account.";
        redirect_with_message(...$redirect_params);
    }
    if ($type === "parent" && (empty($StudentName) || empty($Section) || empty($Grade))) {
        $redirect_params[2] = "Student Name, Section, and Grade are required for Parent account.";
        redirect_with_message(...$redirect_params);
    }


    // ---------- ADD ----------
    if ($action === 'add') {
        if (empty($Password)) { 
             $redirect_params[2] = "Password is required for new account.";
             redirect_with_message(...$redirect_params);
        }
        
        if (email_exists($conn, $Email)) {
            $redirect_params[2] = "This email already exists in another account.";
            redirect_with_message(...$redirect_params);
        }

        if ($type === "admin") {
            $sql = "INSERT INTO $table (Email, Password) VALUES (?, ?)";
            $params = ["ss", $Email, $hashedPassword];
        } elseif ($type === "parent") {
            $student_id = (int)($_POST['student_id'] ?? 0); // Get student_id from form
            $sql = "INSERT INTO $table (Email, Password, StudentName, Section, Grade, student_id) VALUES (?, ?, ?, ?, ?, ?)";
            $params = ["sssssi", $Email, $hashedPassword, $StudentName, $Section, $Grade, $student_id];
        } elseif ($type === "student_affairs") {
            $sql = "INSERT INTO $table (Email, Password) VALUES (?, ?)";
            $params = ["ss", $Email, $hashedPassword]; // Gamit ang $hashedPassword (na ngayon ay plain text)
        } else { // LAC
            $sql = "INSERT INTO $table (Email, Password, Department) VALUES (?, ?, ?)";
            $params = ["sss", $Email, $hashedPassword, $Department]; // Gamit ang $hashedPassword (na ngayon ay plain text)
        }

        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt], $params));

            if (mysqli_stmt_execute($stmt)) {
                redirect_with_message($redirectPage,"success",ucfirst(str_replace("_"," ",$type))." account added successfully.");
            } else {
                $redirect_params[2] = "Failed to add account: ".mysqli_stmt_error($stmt);
                redirect_with_message(...$redirect_params);
            }
            mysqli_stmt_close($stmt);
        } else {
            $redirect_params[2] = "Failed to prepare statement for add.";
            redirect_with_message(...$redirect_params);
        }
    }


    // ---------- UPDATE (Walang Pagbabago sa logic, kasama lang ang bagong $hashedPassword) ----------
    elseif ($action === 'update' && !empty($oldEmail)) {
        if (email_exists($conn, $Email, $oldEmail)) {
            $redirect_params[2] = "This email already exists in another account.";
            redirect_with_message(...$redirect_params);
        }

        $passwordSet = !empty($Password);
        
        if ($type === "admin") {
            if ($passwordSet) {
                $sql = "UPDATE $table SET Email=?, Password=? WHERE Email=?";
                $params = ["sss", $Email, $hashedPassword, $oldEmail];
            } else {
                $sql = "UPDATE $table SET Email=? WHERE Email=?";
                $params = ["ss", $Email, $oldEmail];
            }
        } elseif ($type === "parent") {
            $student_id = (int)($_POST['student_id'] ?? 0); // Get student_id from form
            if ($passwordSet) {
                $sql = "UPDATE $table SET Email=?, Password=?, StudentName=?, Section=?, Grade=?, student_id=? WHERE Email=?";
                $params = ["ssssssi", $Email, $hashedPassword, $StudentName, $Section, $Grade, $student_id, $oldEmail];
            } else {
                $sql = "UPDATE $table SET Email=?, StudentName=?, Section=?, Grade=?, student_id=? WHERE Email=?";
                $params = ["ssssss", $Email, $StudentName, $Section, $Grade, $student_id, $oldEmail];
            }
        } elseif ($type === "student_affairs") {
            if ($passwordSet) {
                $sql = "UPDATE $table SET Email=?, Password=? WHERE Email=?";
                $params = ["sss", $Email, $hashedPassword, $oldEmail];
            } else {
                $sql = "UPDATE $table SET Email=? WHERE Email=?";
                $params = ["ss", $Email, $oldEmail];
            }
        } else { // LAC
            if ($passwordSet) {
                $sql = "UPDATE $table SET Email=?, Password=?, Department=? WHERE Email=?";
                $params = ["ssss", $Email, $hashedPassword, $Department, $oldEmail];
            } else {
                $sql = "UPDATE $table SET Email=?, Department=? WHERE Email=?";
                $params = ["sss", $Email, $Department, $oldEmail];
            }
        }

        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt], $params));
            
            if (mysqli_stmt_execute($stmt)) {
                redirect_with_message($redirectPage,"success",ucfirst(str_replace("_"," ",$type))." account updated successfully.");
            } else {
                $redirect_params[2] = "Failed to update account: ".mysqli_stmt_error($stmt);
                redirect_with_message(...$redirect_params);
            }
            mysqli_stmt_close($stmt);
        } else {
            $redirect_params[2] = "Failed to prepare statement for update.";
            redirect_with_message(...$redirect_params);
        }
    }
}

    // ---------- DELETE ----------
    if (isset($_GET['delete']) && isset($_GET['type'])) {
        $email = $_GET['delete'];
        $type  = $_GET['type'];

        $tables = [
            "lac"             => "lac_account",
            "parent"          => "parent_account",
            "student_affairs" => "student_affairs_account",
            "admin"           => "admins"  // changed from admin_account to admins
        ];
        if (!isset($tables[$type])) redirect_with_message("Admin-manage.php","error","Invalid account type.");
        $table = $tables[$type];

        $stmt = mysqli_prepare($conn, "DELETE FROM $table WHERE Email=?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        if (mysqli_stmt_execute($stmt)) {
            redirect_with_message("Admin-manage.php","success",ucfirst(str_replace("_"," ",$type))." account deleted successfully.");
        } else {
            redirect_with_message("Admin-manage.php","error","Failed to delete account: ".mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
        exit();
    }

    // ---------- FETCH ACCOUNTS & STUDENT DATA ----------
    $lac_account = @mysqli_query($conn, "SELECT Email, Department FROM lac_account") ?: [];
$parent_account = @mysqli_query($conn, "SELECT Email, StudentName, Section, Grade, student_id FROM parent_account") ?: [];
$student_affairs_account = @mysqli_query($conn, "SELECT Email FROM student_affairs_account") ?: [];
$admin_account = @mysqli_query($conn, "SELECT Id, Email FROM admins") ?: []; // already using admins

// Modify the student fetch query to include ID
$students_result = @$conn->query("
    SELECT 
        id,
        CONCAT(surname, ', ', name, ' ', middle_name) AS full_name,
        section,
        grade
    FROM student
") ?: [];

    $student_data = [];
    if ($students_result && $students_result->num_rows > 0) {
        while ($s = $students_result->fetch_assoc()) {
            $student_data[$s['full_name']] = [
                'id' => $s['id'],          // Add student ID
                'section' => $s['section'],
                'grade' => $s['grade']
            ];
        }
    }

    $students = $students_result ?: new ArrayIterator([]); 

    $flash = $_SESSION['flash_message'] ?? null;
    if ($flash) {
        // expose to JS and render small on-page container (if not already)
        $flash_text = $flash['content'] ?? '';
        $flash_type = $flash['type'] ?? 'info';
        echo "<script>window.__manageFlash = " . json_encode(['text'=>$flash_text,'type'=>$flash_type,'meta'=>$flash]) . ";</script>";
        unset($_SESSION['flash_message']);
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Accounts</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5jXN2S9W38s1jC7K+v25T1z/w8Nf1ZtNl0YnKx1e1eX9rWf/6o5W7aB41o20fF3z2+6oB4j2V2eA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="stylesheet" href="../css/adminm.css">
</head>
<body>

<?php if ($flash): ?>
<div id="manageFlash" class="message <?= htmlspecialchars($flash['type']) ?>" style="position:fixed;right:20px;top:20px;z-index:9999;">
    <?= htmlspecialchars($flash['content']) ?>
    <span onclick="this.parentElement.style.display='none';" class="close-btn" style="cursor:pointer;margin-left:10px;">&times;</span>
</div>
<?php endif; ?>

<div class="buttons">
    <button onclick="openAddUserModal('admin')">Add Admin</button>
    <button onclick="openAddUserModal('lac')">Add Learning Area Chair</button>
    <button onclick="openAddUserModal('parent')">Add Parent</button>
    <button onclick="openAddUserModal('student_affairs')">Add Student Affairs</button>
</div>

<div id="userModal" class="modal">
    <div class="modal-content">
        <span onclick="closeModal()" class="close-btn">&times;</span>
        <h2 id="modalTitle">Add User</h2>
        <form method="POST" id="userForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="oldEmail" id="oldEmail" value="">
            <input type="hidden" name="type" id="userType" value="">
            
            <label>Email:</label>
            <input type="email" name="Email" id="Email" required>
            
            <label>Password:</label>
            <div class="password-container">
                <input type="password" name="Password" id="Password" autocomplete="new-password" title="Enter password">
                <!-- accessible toggle: add title for tooltip; include emoji fallback so icon is visible even if FontAwesome fails -->
                <span class="toggle-password" data-target="Password" role="button" tabindex="0" aria-label="Toggle password visibility" aria-pressed="false" title="Show / hide password">
                    <i class="fa-solid fa-eye" aria-hidden="true"></i>
                    <span class="fallback-eye" aria-hidden="true">👁</span>
                </span>
            </div>
            <small>(At least 10+ chars)</small>

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
                    <option value="Mapeh">Mapeh</option>
                    <option value="Edukasyong Pantahanan at Pangkabuhayan (EPP)">Edukasyong Pantahanan at Pangkabuhayan (EPP)</option>
                </select>
            </div>

            <div id="parentFields" style="display:none;">
                <input type="hidden" name="student_id" id="student_id">  <!-- Add hidden student ID field -->
                
                <label>Student Name:</label>
                <input type="text" list="studentList" name="StudentName" id="StudentNameSelect" placeholder="Search or select student" required oninput="updateStudentDetails()">
                <datalist id="studentList">
                    <?php 
                    if(method_exists($students, 'data_seek')) $students->data_seek(0); 
                    foreach ($students as $s):
                    ?>
                        <option value="<?= htmlspecialchars($s['full_name']) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <small>Start typing to search (Auto-fills Section & Grade)</small>
                
                <label>Section:</label>
                <input type="text" name="Section" id="Section" required readonly>
                
                <label>Grade:</label>
                <input type="text" name="Grade" id="Grade" required readonly>
                
            </div>

            <div class="form-actions">
                <button type="submit" name="submit" id="submitBtn">Add</button>
                <button type="button" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<h1>Manage Accounts</h1>
<div class="account-container">
    <!-- Add Admin Account Card First -->
    <div class="account-card">
        <h2>Admin Accounts</h2>
        <table>
            <tr><th>Email</th><th>Password</th><th>Actions</th></tr>
            <?php 
            if(is_object($admin_account) && method_exists($admin_account, 'data_seek')) $admin_account->data_seek(0);
            if(is_object($admin_account)):
                while($user = $admin_account->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($user['Email']) ?></td>
                    <td>************</td>
                    <td>
                        <button class="update-btn" onclick="openEditUserModal('admin','<?= addslashes($user['Email']) ?>')">Update</button>
                        <button class="delete-btn" onclick="if(confirm('Are you sure?')) window.location.href='?delete=<?= urlencode($user['Email']) ?>&type=admin'">Delete</button>
                    </td>
                </tr>
                <?php endwhile;
            endif; ?>
        </table>
    </div>

    <div class="account-card">
        <h2>Learning Area Chair Accounts</h2>
        <table>
            <tr><th>Email</th><th>Password</th><th>Department</th><th>Actions</th></tr>
            <?php 
            if(method_exists($lac_account, 'data_seek')) $lac_account->data_seek(0);
            foreach ($lac_account as $user): ?>
            <tr>
                <td><?= htmlspecialchars($user['Email']) ?></td>
                <td>************</td>
                <td><?= htmlspecialchars($user['Department']) ?></td>
                <td>
                    <button class="update-btn" onclick="openEditUserModal('lac','<?= addslashes($user['Email']) ?>','<?= addslashes($user['Department']) ?>')">Update</button>
                    <button class="delete-btn" onclick="if(confirm('Are you sure?')) window.location.href='?delete=<?= urlencode($user['Email']) ?>&type=lac'">Delete</button>
            </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="account-card">
    <h2>Parent Accounts</h2>
    <table>
        <tr><th>Email</th><th>Password</th><th>Student Name</th><th>Section</th><th>Grade</th><th>Actions</th></tr>
        <?php 
        if(method_exists($parent_account, 'data_seek')) $parent_account->data_seek(0);
        foreach ($parent_account as $user): ?>
        <tr>
            <td><?= htmlspecialchars($user['Email']) ?></td>
            <td>************</td>
            <td><?= htmlspecialchars($user['StudentName']) ?></td>
            <td><?= htmlspecialchars($user['Section']) ?></td>
            <td><?= htmlspecialchars($user['Grade']) ?></td>
            <td>
                <button class="update-btn" onclick="openEditUserModal('parent',
                    '<?= addslashes($user['Email']) ?>',
                    '<?= addslashes($user['StudentName'] ?? '') ?>', 
                    '<?= addslashes($user['Section'] ?? '') ?>',
                    '<?= addslashes($user['Grade'] ?? '') ?>',
                    '<?= (int)($user['student_id'] ?? 0) ?>'
                )">Update</button>
                <button class="delete-btn" onclick="if(confirm('Are you sure?')) window.location.href='?delete=<?= urlencode($user['Email']) ?>&type=parent'">Delete</button>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
    <div class="account-card">
    <h2>Student Affairs Accounts</h2>
    <table>
        <tr><th>Email</th><th>Password</th><th>Actions</th></tr>
        <?php 
        if(method_exists($student_affairs_account, 'data_seek')) $student_affairs_account->data_seek(0);
        foreach ($student_affairs_account as $user): ?>
        <tr>
            <td><?= htmlspecialchars($user['Email']) ?></td>
            <td>************</td>
            <td>
                <button class="update-btn" onclick="openEditUserModal('student_affairs','<?= addslashes($user['Email']) ?>')">Update</button>
                <button class="delete-btn" onclick="if(confirm('Are you sure?')) window.location.href='?delete=<?= urlencode($user['Email']) ?>&type=student_affairs'">Delete</button>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>


</div>

<script>
// Global object to map student name to their details
const studentData = <?= json_encode($student_data); ?>;

// New accessible toggle for any .toggle-password element (click or keyboard)
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
            btn.setAttribute('aria-pressed', 'true');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
            btn.setAttribute('aria-pressed', 'false');
        }
        // return focus to the input (better UX for typing)
        input.focus();
    }

    // click handler
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.toggle-password');
        if (!btn) return;
        toggleButtonActivate(btn);
    });

    // keyboard handler (Space / Enter)
    document.addEventListener('keydown', function(e) {
        const active = document.activeElement;
        if (!active || !active.classList) return;
        if (!active.classList.contains('toggle-password')) return;
        if (e.key === ' ' || e.key === 'Enter') {
            e.preventDefault();
            toggleButtonActivate(active);
        }
    });
})();

// --- AUTO-FILL FUNCTION (Parent Side) ---
function updateStudentDetails() {
    const studentNameInput = document.getElementById('StudentNameSelect');
    const sectionInput = document.getElementById('Section');
    const gradeInput = document.getElementById('Grade');
    const studentIdInput = document.getElementById('student_id');
    const selectedName = studentNameInput.value;

    if (studentData[selectedName]) {
        sectionInput.value = studentData[selectedName].section;
        gradeInput.value = studentData[selectedName].grade;
        studentIdInput.value = studentData[selectedName].id;  // Set student ID
    } else {
        // Clear if the input doesn't match a student (only for 'add' mode)
        if (document.getElementById('formAction').value === 'add') {
            sectionInput.value = '';
            gradeInput.value = '';
            studentIdInput.value = '';  // Clear student ID
        }
    }
}

// --- GENERAL MODAL FUNCTIONS ---
function closeModal() {
    document.getElementById('userModal').style.display = 'none';
    document.getElementById('userForm').reset();
    document.getElementById('Department').value = "";
    document.getElementById('Section').value = "";
    document.getElementById('Grade').value = "";

    // ensure Email is editable again
    const emailEl = document.getElementById('Email');
    if (emailEl) emailEl.readOnly = false;
    
    // Reset all password inputs and toggle buttons in the modal
    const pwdInputs = document.querySelectorAll('#userModal input[type="password"], #userModal input[type="text"]');
    pwdInputs.forEach(inp => {
        if (inp.id === 'Password') inp.type = 'password';
    });
    const toggles = document.querySelectorAll('.toggle-password');
    toggles.forEach(btn => {
        const icon = btn.querySelector('i');
        if (icon) {
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
        btn.setAttribute('aria-pressed', 'false');
    });
}

// --- ADD MODAL FUNCTION ---
function openAddUserModal(type) {
    closeModal(); 
    
    document.getElementById('userModal').style.display = 'block';
    document.getElementById('modalTitle').textContent = 'Add ' + type.replace('_',' ').replace(/\b\w/g, l => l.toUpperCase());
    document.getElementById('submitBtn').textContent = 'Add';
    document.getElementById('formAction').value = 'add';
    document.getElementById('userType').value = type;
    document.getElementById('Password').required = true;

    // ensure Email can be edited when adding
    const emailEl = document.getElementById('Email');
    if (emailEl) emailEl.readOnly = false;
    
    document.getElementById('Department').required = false;
    document.getElementById('StudentNameSelect').required = false;
    document.getElementById('Section').required = false;
    document.getElementById('Grade').required = false;

    if (type === "parent") {
        document.getElementById('deptField').style.display = "none";
        document.getElementById('parentFields').style.display = "block";
        document.getElementById('StudentNameSelect').required = true;
        document.getElementById('Section').required = true;
        document.getElementById('Grade').required = true;

    } else if (type === "student_affairs") {
        document.getElementById('deptField').style.display = "none";
        document.getElementById('parentFields').style.display = "none";
    } else if (type === "lac") { // LAC shows Department
        document.getElementById('deptField').style.display = "block";
        document.getElementById('parentFields').style.display = "none";
        document.getElementById('Department').required = true;
    } else if (type === "admin") { // Admin has no department
        document.getElementById('deptField').style.display = "none";
        document.getElementById('parentFields').style.display = "none";
        document.getElementById('Department').required = false;
    }
}

// --- EDIT MODAL FUNCTION ---
function openEditUserModal(type, email, extra1 = "", extra2 = "", extra3 = "", studentId = "") {
    closeModal();

    document.getElementById('userModal').style.display = 'block';
    document.getElementById('modalTitle').textContent = 'Update ' + type.replace('_',' ').replace(/\b\w/g, l => l.toUpperCase());
    document.getElementById('submitBtn').textContent = 'Update';
    document.getElementById('formAction').value = 'update';
    document.getElementById('oldEmail').value = email;
    document.getElementById('userType').value = type;
    document.getElementById('Email').value = email;
    document.getElementById('Password').required = false;

    // make Email readonly during edit so it won't be changed
    const emailEl = document.getElementById('Email');
    if (emailEl) emailEl.readOnly = true;

    if (type === "parent") {
        document.getElementById('deptField').style.display = "none";
        document.getElementById('parentFields').style.display = "block";
        
        document.getElementById('StudentNameSelect').value = extra1;
        document.getElementById('Section').value = extra2;
        document.getElementById('Grade').value = extra3;
        document.getElementById('student_id').value = studentId; // Set student_id
        
        document.getElementById('StudentNameSelect').required = true;
        document.getElementById('Section').required = true;
        document.getElementById('Grade').required = true;
        

    } else if (type === "student_affairs") {
        document.getElementById('deptField').style.display = "none";
        document.getElementById('parentFields').style.display = "none";
    } else if (type === "lac") { // LAC edit: show department
        document.getElementById('deptField').style.display = "block";
        document.getElementById('parentFields').style.display = "none";

        document.getElementById('Department').value = extra1;
        document.getElementById('Department').required = true;
    } else if (type === "admin") { // Admin edit: hide dept & parent
        document.getElementById('deptField').style.display = "none";
        document.getElementById('parentFields').style.display = "none";
        document.getElementById('Department').required = false;
    }
}

// --- Flash Message Modal Display Logic (For error state re-opening) ---
document.addEventListener('DOMContentLoaded', () => {
    const modalType = "<?= $flash['modalType'] ?? '' ?>";
    const openModal = "<?= $flash['openModal'] ?? 0 ?>";
    const oldEmail = "<?= addslashes($flash['oldEmail'] ?? '') ?>";
    const department = "<?= addslashes($flash['department'] ?? '') ?>";

    if (openModal == 1 && modalType) {
        
        openAddUserModal(modalType);
        
        document.getElementById('Email').value = "<?= addslashes($_POST['Email'] ?? '') ?>";
        if (modalType === 'lac') {
            document.getElementById('Department').value = department;
        } else if (modalType === 'parent') {
            document.getElementById('StudentNameSelect').value = "<?= addslashes($_POST['StudentName'] ?? '') ?>";
            document.getElementById('Section').value = "<?= addslashes($_POST['Section'] ?? '') ?>";
            document.getElementById('Grade').value = "<?= addslashes($_POST['Grade'] ?? '') ?>";
        }

        if (oldEmail) {
            document.getElementById('formAction').value = 'update';
            document.getElementById('oldEmail').value = oldEmail;
            document.getElementById('modalTitle').textContent = 'Update ' + modalType.replace('_',' ').replace(/\b\w/g, l => l.toUpperCase());
            document.getElementById('submitBtn').textContent = 'Update';
            document.getElementById('Password').required = false;

            // ensure Email is readonly when re-opening in update state
            const emailEl = document.getElementById('Email');
            if (emailEl) emailEl.readOnly = true;
        }
        
        document.getElementById('userModal').style.display = 'block';
    }
});

// Flash handling: show native alert and auto-hide the in-page message
(function(){
    if (window.__manageFlash && window.__manageFlash.text) {
        // show native alert for visibility
        try { alert(window.__manageFlash.text); } catch(e){}
        const el = document.getElementById('manageFlash');
        if (el) {
            setTimeout(()=> {
                el.style.transition = 'opacity 0.5s';
                el.style.opacity = '0';
                setTimeout(()=> el.remove(), 600);
            }, 5000);
        }
    }
})();
</script>

</body>
</html>