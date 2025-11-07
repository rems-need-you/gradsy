<?php 
include('../partials-front/constantsss.php'); 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== "student_affairs") {
    header('Location: ' . SITEURL . 'LAC/login.php');
    exit();
}
$lac_id = $_SESSION['id'] ?? 0;
$lac_email = $_SESSION['user'] ?? '';
$lac_name = $_SESSION['teacher_name'] ?? 'student affairs';
$lac_profile_pic = $_SESSION['profile_pic'] ?? '../images/teacher.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Student Affairs Dashboard</title>
    <link rel="stylesheet" href="../css/box.css">
</head>
<body>
<div class="topbar">
  <div class="logo"><strong>📘 Student Affairs Dashboard </strong></div>
  <div class="teacher-info">
    <img src="<?php echo htmlspecialchars($lac_profile_pic); ?>" alt="Profile" id="teacherProfilePic">
    <div><small><?php echo htmlspecialchars($lac_email); ?></small></div>
  </div>
</div>

    <div class="sidebar">
        <ul>        
            <li><a href="<?php echo SITEURL; ?>LACdisciplinary.php" target="mainFrame">⚖️ Disciplinary</a></li>
            <li><a href="<?php echo SITEURL; ?>LACabouts.php" target="mainFrame">ℹ️ About</a></li>
            <li><a href="<?php echo SITEURL; ?>LACcontact.php" target="mainFrame">📧 Contact</a></li>
            <li class="logout"><a href="<?php echo SITEURL; ?>LAClogout.php"> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <iframe id="mainFrame" name="mainFrame" class="content"></iframe>

    <script>
        // Get iframe and menu links
        const iframe = document.getElementById("mainFrame");
        const links = document.querySelectorAll(".sidebar ul li a:not(.logout a)");

        // Load last visited page from localStorage or default to LACdisciplinary.php
        const lastPage = localStorage.getItem("lastPage") || "<?php echo SITEURL; ?>LACdisciplinary.php";
        iframe.src = lastPage;

        // Mark active link
        function setActiveLink(url) {
            links.forEach(link => {
                link.classList.toggle("active", link.href === url);
            });
        }

        // Initial active state
        setActiveLink(lastPage);

        // When clicking links, update iframe + save last page + update active
        links.forEach(link => {
            link.addEventListener("click", (e) => {
                const url = link.href;
                localStorage.setItem("lastPage", url);
                setActiveLink(url);
            });
        });
    </script>
</body>
</html>
