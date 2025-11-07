<?php include ('partials/constants.php');
if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_POST['submit'])) {
    $Email = mysqli_real_escape_string($conn, $_POST['Email']);
    // *** FIX: Changed from $_POST['Password'] to $_POST['password'] ***
    $Password = $_POST['password']; 

    $sql = "SELECT * FROM admins WHERE Email=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $Email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $admin = $result->fetch_assoc();
        // WARNING: Using plain text password check. 
        // Use password_verify() with hashed passwords in a production environment.
        if ($Password === $admin['Password']) { // Using $admin['Password'] to match DB case
            $_SESSION['login'] = "<div class='success'>Login Successful.</div>";
            $_SESSION['user'] = $Email;
            $_SESSION['role'] = 'admins';
            
            // Set session variables matching Admin-index.php
            $_SESSION['Id'] = $admin['Id']; // Use 'Id' to match Admin-index.php
            $_SESSION['teacher_name'] = 'Admin User';
            
            header('Location: ' . SITEURL . 'Admin-index.php');
            exit();
        }
    }
    
    $_SESSION['login'] = "<div class='error text-center'>Incorrect Username or Password</div>";
    header('Location: ' . SITEURL . 'Admin-login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Grading System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
<style></style>
<div class="bg-shape shape1"></div>
<div class="bg-shape shape2"></div>
<div class="bg-shape shape3"></div>

<div class="left-panel">
    <h1>INTEGRATED GRADING, EXTRACURRICULAR, AND DISCIPLINE TRACKING</h1>
    <h2>Welcome Back Registrar/Administrator</h2>
</div>

<div class="right-panel">
    <div class="login-box">
        <h2>Login</h2>

        <?php
        // Render the session login flash into a stable container and expose its raw text to JS
        if (isset($_SESSION['login'])) {
            // $_SESSION['login'] contains HTML like "<div class='error'>...</div>"
            $loginHtml = $_SESSION['login'];
            // Extract a plain-text message for alert() fallback
            $plain = strip_tags($loginHtml);
            echo "<div id='flashMessageContainer'>{$loginHtml}</div>";
            // expose plain message and whether it's error/success
            echo "<script>window.__flash = { text: " . json_encode($plain) . ", html: " . json_encode($loginHtml) . " };</script>";
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

    // Global flash handler: auto-hide and show browser alert for important messages
    (function(){
        const container = document.getElementById('flashMessageContainer');
        if (!container && !window.__flash) return;

        // If server provided plain text flash, show browser alert for visibility (only once)
        if (window.__flash && window.__flash.text) {
            // prefer using visible container; still also pop alert for errors/info
            const txt = window.__flash.text;
            if (txt) {
                // Only pop native alert if message likely to be important (contains typical keywords)
                const lower = txt.toLowerCase();
                if (lower.includes('error') || lower.includes('incorrect') || lower.includes('failed') || lower.includes('success')) {
                    try { alert(txt); } catch(e) { /* ignore */ }
                }
            }
        }

        if (container) {
            // show container if hidden and auto-hide after 5s
            container.style.display = 'block';
            setTimeout(()=> {
                container.style.transition = 'opacity 0.5s';
                container.style.opacity = '0';
                setTimeout(()=> container.remove(), 600);
            }, 5000);
        }
    })();
</script>

</body>
</html>
