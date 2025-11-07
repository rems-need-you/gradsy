<?php 
include('../partials-front/constantsss.php'); 

if (session_status() === PHP_SESSION_NONE) session_start();

// Redirect if not teacher
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "teacher") {
    header('Location: ' . SITEURL . 'login.php');
    exit();
}

// ===== Teacher Basic Info =====
$teacher_id = $_SESSION['teacher_id'] ?? 0;
$teacher_name = $_SESSION['teacher_name'] ?? 'Teacher';
$teacher_email = $_SESSION['user'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '../images/teacher.png';

// ===== Assigned Info =====
$assigned_subject = $_SESSION['subject'] ?? 'N/A';
$assigned_grade = $_SESSION['grade'] ?? 'N/A';
$assigned_section = $_SESSION['section'] ?? 'N/A';

// ===== Load Profile if needed =====
$stmt = $conn->prepare("SELECT profile_pic FROM teacher_account WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $profile_pic = !empty($row['profile_pic']) ? '../uploads/' . $row['profile_pic'] : '../images/teacher.png';
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Teacher Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../css/box.css">
<style>
/* ===== MODAL STYLES ===== */
.modal {
  display: none;
  position: fixed;
  z-index: 1000;
  inset: 0;
  background-color: rgba(0, 0, 0, 0.4);
  backdrop-filter: blur(6px);
  animation: fadeIn 0.3s ease forwards;
}
@keyframes fadeIn {
  from {opacity: 0;}
  to {opacity: 1;}
}
.modal-content {
  background: rgba(255, 255, 255, 0.9);
  margin: 7% auto;
  padding: 25px;
  border-radius: 15px;
  width: 400px;
  box-shadow: 0 4px 25px rgba(0,0,0,0.3);
  text-align: center;
  position: relative;
  backdrop-filter: blur(10px);
  animation: slideUp 0.35s ease;
}
@keyframes slideUp {
  from {transform: translateY(50px); opacity: 0;}
  to {transform: translateY(0); opacity: 1;}
}
.modal-content h2 {
  margin-bottom: 15px;
  font-family: 'Segoe UI', sans-serif;
  color: #333;
}
.modal-content input {
  width: 90%;
  padding: 10px;
  margin: 10px 0;
  border: 1px solid #ccc;
  border-radius: 8px;
  font-size: 14px;
  transition: border-color 0.2s;
}
.modal-content input:focus {
  border-color: #007bff;
  outline: none;
}
.save-btn {
  background-color: #4CAF50;
  color: white;
  padding: 10px 25px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: background 0.3s;
}
.save-btn:hover {
  background-color: #43a047;
}
.close {
  position: absolute;
  right: 20px;
  top: 15px;
  font-size: 24px;
  cursor: pointer;
  color: #333;
  transition: color 0.3s;
}
.close:hover {
  color: #ff4d4d;
}
.profile-pic-section {
  margin: 10px 0;
  display: flex;
  flex-direction: column;
  align-items: center;
}
.profile-pic-section img {
  width: 110px;
  height: 110px;
  border-radius: 50%;
  object-fit: cover;
  margin-bottom: 12px;
  border: 3px solid #ddd;
  box-shadow: 0 2px 10px rgba(0,0,0,0.2);
  transition: transform 0.3s;
}
.profile-pic-section img:hover {
  transform: scale(1.05);
}
.profile-pic-section input {
  width: auto;
  margin-top: 5px;
}
</style>
</head>
<body>

<!-- 🔴 Top bar -->
<div class="topbar">
  <div class="logo">
    <strong>📘 Teacher Dashboard</strong>
  </div>
   <p>Subject: <?php echo htmlspecialchars($assigned_subject); ?>
  <div class="teacher-info">
    <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile" id="teacherProfilePic">
    <div>
      <span><?php echo htmlspecialchars($teacher_name); ?></span><br>
      <small><?php echo htmlspecialchars($teacher_email); ?></small>

    </div>
  </div>
</div>

<!-- Sidebar -->
<div class="sidebar">
  <ul>
    <li><a href="<?php echo SITEURL; ?>LACeditgrades.php" target="mainFrame">📚 My Classes</a></li>
    <li><a href="<?php echo SITEURL; ?>recordT.php" target="mainFrame">📂 Records</a></li>
    <li><a href="<?php echo SITEURL; ?>LACabouts.php" target="mainFrame">ℹ️ About</a></li>
    <li><a href="<?php echo SITEURL; ?>LACcontact.php" target="mainFrame">📧 Contact</a></li>
    <li class="logout"><a href="<?php echo SITEURL; ?>LAClogout.php">Logout</a></li>
  </ul>
</div>

<!-- 🧩 Profile Edit Modal -->
<div id="profileModal" class="modal">
  <div class="modal-content">
    <span class="close">&times;</span>
    <h2>Edit Profile</h2>

    <form id="editProfileForm" method="POST" enctype="multipart/form-data" action="update_profileT.php">
      <!-- Profile Picture -->
      <div class="profile-pic-section">
        <img id="profilePreview" src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Current Profile">
        <input type="file" name="profile_image" accept="image/*" id="profileImageInput">
      </div>

      <!-- Email (readonly) -->
      <label>Email</label>
      <input type="email" name="email" value="<?php echo htmlspecialchars($teacher_email); ?>" readonly>

      <!-- Current Password (required) -->
      <label>Current Password</label>
      <input type="password" name="current_password" required placeholder="Enter current password">

      <!-- New Password (optional) -->
      <label>New Password <small style="font-weight:normal;">(leave blank if you don’t want to change)</small></label>
      <input type="password" name="new_password" placeholder="Enter new password">

      <!-- Forgot Password link -->
      <p style="margin:5px 0; text-align:right;">
        <a href="fpassT.php" style="color:#007bff; text-decoration:underline;">Forgot Password?</a>
      </p>

      <!-- Save Button -->
      <button type="submit" class="save-btn">Save Changes</button>
    </form>
  </div>
</div>



<!-- Main Frame -->
<iframe id="mainFrame" name="mainFrame" class="content"></iframe>

<script>
  // Iframe navigation
  const iframe = document.getElementById("mainFrame");
  const links = document.querySelectorAll(".sidebar ul li a:not(.logout a)");
  const lastPage = localStorage.getItem("lastPage") || "<?php echo SITEURL; ?>LACeditgrades.php";
  iframe.src = lastPage;

  function setActiveLink(url) {
    links.forEach(link => {
      link.classList.toggle("active", link.href === url);
    });
  }
  setActiveLink(lastPage);
  links.forEach(link => {
    link.addEventListener("click", () => {
      const url = link.href;
      localStorage.setItem("lastPage", url);
      setActiveLink(url);
    });
  });

  // Modal logic
  const modal = document.getElementById("profileModal");
  const closeBtn = document.querySelector(".close");
  const profilePic = document.getElementById("teacherProfilePic");
  const imageInput = document.getElementById("profileImageInput");
  const preview = document.getElementById("profilePreview");

  profilePic.addEventListener("click", () => { modal.style.display = "block"; });
  closeBtn.onclick = () => { modal.style.display = "none"; };
  window.onclick = (event) => { if (event.target === modal) modal.style.display = "none"; };

  // Image preview
  imageInput.addEventListener("change", function(){
    const file = this.files[0];
    if (file) preview.src = URL.createObjectURL(file);
  });
  closeBtn.onclick = () => {
    modal.style.display = "none";
  };
</script>

</body>
</html>