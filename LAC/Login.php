<?php 
include('../partials-front/constantsss.php'); 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// ================= LOGIN PROCESS =================
if (isset($_POST['submit'])) {
    $Email = mysqli_real_escape_string($conn, $_POST['Email']);
    $Password = mysqli_real_escape_string($conn, $_POST['password']);

    // ========== LAC ==========
    $sqlLAC = "SELECT * FROM lac_account WHERE Email='$Email' AND password='$Password'";
    $resLAC = mysqli_query($conn, $sqlLAC);
    if (mysqli_num_rows($resLAC) == 1) {
        $row = mysqli_fetch_assoc($resLAC);

    if ($row['Status'] === 'archived') {
        echo "<script>alert('Your account is archived and cannot log in.'); window.location='login.php';</script>";
        exit();
    }

    $_SESSION['id'] = $row['id'];          // ✅ Add this
    $_SESSION['user'] = $Email;
    $_SESSION['role'] = "lac";
    $_SESSION['department'] = $row['department'];
    $_SESSION['login'] = "<div class='success text-center'>Login Successful (Learning Area Chair)</div>";
    header('Location: ' . SITEURL . 'LACindex.php');
    exit();
    }
    // ========== TEACHER ==========
$sqlTeacher = "SELECT * FROM teacher_account WHERE Email='$Email' AND password='$Password'";
$resTeacher = mysqli_query($conn, $sqlTeacher);

if (mysqli_num_rows($resTeacher) == 1) {
    $row = mysqli_fetch_assoc($resTeacher);

    // ✅ Check if archived
    if ($row['Status'] === 'archived') {
        echo "<script>alert('Your account is archived and cannot log in.'); window.location='login.php';</script>";
        exit();
    }

    // ✅ Get assigned subject from assign_teacher
    $teacher_id = $row['id'];
    $assign_sql = "SELECT subject, grade, section, year FROM assign_teacher WHERE teacher_id = '$teacher_id' LIMIT 1";
    $assign_res = mysqli_query($conn, $assign_sql);

    $assigned_subject = '';
    $assigned_grade = '';
    $assigned_section = '';
    $assigned_year = '';

    if (mysqli_num_rows($assign_res) > 0) {
        $assign_row = mysqli_fetch_assoc($assign_res);
        $assigned_subject = $assign_row['subject'];
        $assigned_grade = $assign_row['grade'];
        $assigned_section = $assign_row['section'];
        $assigned_year = $assign_row['year'];
    }

    // ✅ Save teacher info in session
    $_SESSION['user'] = $Email;
    $_SESSION['role'] = "teacher";
    $_SESSION['teacher_id'] = $teacher_id;
    $_SESSION['teacher_name'] = $row['name'];
    $_SESSION['subject'] = $assigned_subject;
    $_SESSION['grade'] = $assigned_grade;
    $_SESSION['section'] = $assigned_section;
    $_SESSION['year'] = $assigned_year;
    $_SESSION['login'] = "<div class='success text-center'>Login Successful (Teacher)</div>";

    header('Location: ' . SITEURL . 'TeacherD.php');
    exit();

}
    // ========== PARENT ==========
    // ========== PARENT ==========
    $sqlParent = "SELECT * FROM parent_account WHERE Email='$Email' AND password='$Password'";
    $resParent = mysqli_query($conn, $sqlParent);
    if (mysqli_num_rows($resParent) == 1) {
        $row = mysqli_fetch_assoc($resParent);

        // ✅ Check if archived
        if (isset($row['Status']) && $row['Status'] === 'archived') {
            echo "<script>alert('Your account is archived and cannot log in.'); window.location='login.php';</script>";
            exit();
        }

        $_SESSION['user'] = $Email;
        $_SESSION['role'] = "parent";
        // 🚨 CRITICAL ADDITION: I-save ang Parent ID!
        $_SESSION['parent_id'] = $row['id']; 
        
        $_SESSION['StudentName'] = $row['StudentName'];
        $_SESSION['login'] = "<div class='success text-center'>Login Successful (Parent)</div>";
        header('Location: ' . SITEURL . 'ParentD.php');
        exit();
    }

    // ========== STUDENT AFFAIRS ==========
    $sqlSA = "SELECT * FROM student_affairs_account WHERE Email='$Email' AND password='$Password'";
    $resSA = mysqli_query($conn, $sqlSA);
    if (mysqli_num_rows($resSA) == 1) {
        $row = mysqli_fetch_assoc($resSA);

        // ✅ Check if archived
        if ($row['Status'] === 'archived') {
            echo "<script>alert('Your account is archived and cannot log in.'); window.location='login.php';</script>";
            exit();
        }

        $_SESSION['user'] = $Email;
        $_SESSION['role'] = "student_affairs";
        $_SESSION['department'] = $row['department'];
        $_SESSION['login'] = "<div class='success text-center'>Login Successful (Student Affairs)</div>";
        header('Location: ' . SITEURL . 'StudentAffairsD.php');
        exit();
    }

    // ========== INVALID LOGIN ==========
    $_SESSION['login'] = "<div class='error text-center'>Incorrect Email or Password</div>";
    header('Location: ' . SITEURL . 'login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Grading System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/login.css">
</head>
<body>

<div class="bg-shape shape1"></div>
<div class="bg-shape shape2"></div>
<div class="bg-shape shape3"></div>

<div class="left-panel">
    <h1>INTEGRATED GRADING, EXTRACURRICULAR, AND DISCIPLINE TRACKING</h1>
    <h2>Welcome Back Users!</h2>
</div>

<div class="right-panel">
    <div class="login-box">
        <h2>Login</h2>

        <?php
        if (isset($_SESSION['login'])) {
            echo $_SESSION['login'];
            unset($_SESSION['login']);
        }
        ?>

        <form action="" method="POST">
            <div class="input-field">
                <input type="text" name="Email" id="Email" placeholder="Enter Email" required>
                <i class="fa-solid fa-envelope"></i>
                <div id="emailWarning" class="email-warning">⚠️ Email must contain '@'</div>
            </div>

            <div class="input-field">
                <input type="password" name="password" id="lacLoginPassword" placeholder="Enter Password" required>
                <i class="fa-solid fa-eye" id="toggleLacLoginPassword"></i>
            </div>

            <input type="submit" name="submit" value="Login">
        </form>
    </div>
</div>

<script>
    const toggle = document.getElementById('toggleLacLoginPassword');
    const password = document.getElementById('lacLoginPassword');
    const emailInput = document.getElementById('Email');
    const emailWarning = document.getElementById('emailWarning');

    // Password toggle
    toggle.addEventListener('click', function () {
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });

    // Real-time email validation reminder
    emailInput.addEventListener('input', function() {
        const value = emailInput.value.trim();
        if (value !== "" && !value.includes('@')) {
            emailWarning.style.display = 'block';
        } else {
            emailWarning.style.display = 'none';
        }
    });

    // Also check when user leaves the field
    emailInput.addEventListener('blur', function() {
        const value = emailInput.value.trim();
        emailWarning.style.display = (value !== "" && !value.includes('@')) ? 'block' : 'none';
    });
</script>

</body>
</html>