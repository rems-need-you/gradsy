<?php 
ob_start();
include('../partials-front/constantsss.php'); 
if (session_status() === PHP_SESSION_NONE) session_start();

// ✅ Make sure the user is LAC
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'lac') {
    header('Location: ' . SITEURL . 'login.php');
    exit();
}

// ✅ Get LAC info from session
$lac_id = $_SESSION['id'] ?? 0;
$lac_email = $_SESSION['user'] ?? '';
$lac_name = $_SESSION['teacher_name'] ?? 'Learning Area Chair';
$lac_profile_pic = $_SESSION['profile_pic'] ?? '../images/teacher.png';
$lac_department = $_SESSION['department'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Learning Area Chair Dashboard</title>
<link rel="stylesheet" href="../css/box.css">
<style>
/* ===== MODAL STYLES ===== */
.modal { display: none; position: fixed; z-index: 1000; inset: 0; background-color: rgba(0,0,0,0.4); backdrop-filter: blur(6px); animation: fadeIn 0.3s ease forwards; }
@keyframes fadeIn { from {opacity: 0;} to {opacity: 1;} }
.modal-content { background: rgba(255,255,255,0.9); margin: 7% auto; padding: 25px; border-radius: 15px; width: 400px; box-shadow: 0 4px 25px rgba(0,0,0,0.3); text-align: center; position: relative; backdrop-filter: blur(10px); animation: slideUp 0.35s ease; }
@keyframes slideUp { from {transform: translateY(50px); opacity:0;} to {transform: translateY(0); opacity:1;} }
.modal-content h2 { margin-bottom:15px; font-family:'Segoe UI',sans-serif; color:#333; }
.modal-content input { width:90%; padding:10px; margin:10px 0; border:1px solid #ccc; border-radius:8px; font-size:14px; transition:border-color 0.2s; }
.modal-content input:focus { border-color:#007bff; outline:none; }
.save-btn { background-color:#4CAF50; color:white; padding:10px 25px; border:none; border-radius:8px; cursor:pointer; transition: background 0.3s; }
.save-btn:hover { background-color:#43a047; }
.close { position:absolute; right:20px; top:15px; font-size:24px; cursor:pointer; color:#333; transition: color 0.3s; }
.close:hover { color:#ff4d4d; }
.profile-pic-section { margin:10px 0; display:flex; flex-direction:column; align-items:center; }
.profile-pic-section img { width:50px; height:50px; border-radius:50%; object-fit:cover; margin-bottom:12px; border:3px solid #ddd; box-shadow:0 2px 10px rgba(0,0,0,0.2); transition: transform 0.3s; }
.profile-pic-section img:hover { transform:scale(1.05); }
.profile-pic-section input { width:auto; margin-top:5px; }
.alert { padding:10px; border-radius:8px; margin-top:10px; font-size:14px; }
.alert.success { background:#e8f9f1; color:#2e7d32; border-left:4px solid #4CAF50; }
.alert.error { background:#ffeaea; color:#d32f2f; border-left:4px solid #f44336; }
</style>
</head>
<body>

<!-- 🔴 Top bar -->
<div class="topbar">
  <div class="logo"><strong>📘 Learning Area Chair Dashboard</strong></div>
  <p>Department: <?php echo htmlspecialchars($lac_department); ?></p>
  <div class="teacher-info">
    <img src="<?php echo htmlspecialchars($lac_profile_pic); ?>" alt="Profile" id="teacherProfilePic">
    <div><small><?php echo htmlspecialchars($lac_email); ?></small></div>
  </div>
</div>

<!-- Sidebar -->
<div class="sidebar">
    <ul>
        <li><a href="<?php echo SITEURL; ?>LACgraph.php" target="mainFrame">📊 Student Ranking</a></li>
        <li><a href="<?php echo SITEURL; ?>LACextracurricular.php" target="mainFrame">📘 Extracurricular</a></li>            
        <li><a href="<?php echo SITEURL; ?>LACrecords.php" target="mainFrame">📂 Records</a></li>
        <li><a href="<?php echo SITEURL; ?>ManageAccounts.php" target="mainFrame">👥 Manage Teacher Accounts</a></li>
        <li><a href="<?php echo SITEURL; ?>LACabouts.php" target="mainFrame">ℹ️ About</a></li>
        <li><a href="<?php echo SITEURL; ?>LACcontact.php" target="mainFrame">📧 Contact</a></li>
        <li class="logout"><a href="<?php echo SITEURL; ?>LAClogout.php"> Logout</a></li>
    </ul>
</div>

<!-- 🧩 Profile Edit Modal -->
<div id="profileModal" class="modal">
  <div class="modal-content">
    <span class="close">&times;</span>
    <h2>Edit Profile</h2>
    <form id="editProfileForm" method="POST" enctype="multipart/form-data" action="update_profileL.php">
      <div class="profile-pic-section">
        <img id="profilePreview" src="<?php echo htmlspecialchars($lac_profile_pic); ?>" alt="Current Profile">
        <input type="file" name="profile_image" accept="image/*" id="profileImageInput">
      </div>
      <label>Email</label>
      <input type="email" name="email" value="<?php echo htmlspecialchars($lac_email); ?>" readonly>
      <label>Current Password</label>
      <input type="password" name="current_password" required placeholder="Enter current password">
      <label>New Password <small style="font-weight:normal;">(leave blank if you don’t want to change)</small></label>
      <input type="password" name="new_password" placeholder="Enter new password">
      <p style="margin:5px 0; text-align:right;">
        <a href="#" id="openForgotPass" style="color:#007bff; text-decoration:underline;">Forgot Password?</a>
      </p>
      <button type="submit" class="save-btn">Save Changes</button>
    </form>
  </div>
</div>

<!-- 🧩 Forgot Password Modal -->
<div id="forgotPassModal" class="modal">
  <div class="modal-content" style="width:380px;">
    <span class="close" id="closeForgotPass">&times;</span>
    <h2>Forgot Password</h2>
    <p style="font-size:14px;color:#555;">Enter your registered email to generate a temporary password.</p>
    <form id="forgotPassForm">
      <input type="email" name="Email" required placeholder="Enter your email">
      <button type="submit" class="save-btn" style="margin-top:10px;">Reset Password</button>
    </form>
    <div id="forgotMsg" style="margin-top:10px;"></div>
  </div>
</div>

<!-- Main Content -->
<iframe id="mainFrame" name="mainFrame" class="content"></iframe>

<script>
// ===== Iframe Navigation =====
const iframe = document.getElementById("mainFrame");
const links = document.querySelectorAll(".sidebar ul li a:not(.logout a)");
const lastPage = localStorage.getItem("lastPage") || "<?php echo SITEURL; ?>LACgraph.php";
iframe.src = lastPage;

function setActiveLink(url) {
  links.forEach(link => link.classList.toggle("active", link.href === url));
}
setActiveLink(lastPage);
links.forEach(link => {
  link.addEventListener("click", () => {
    const url = link.href;
    localStorage.setItem("lastPage", url);
    setActiveLink(url);
  });
});

// ===== Profile Modal =====
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

// ===== Forgot Password Modal =====
const forgotModal = document.getElementById("forgotPassModal");
const openForgotPass = document.getElementById("openForgotPass");
const closeForgotPass = document.getElementById("closeForgotPass");
const forgotForm = document.getElementById("forgotPassForm");
const forgotMsg = document.getElementById("forgotMsg");

openForgotPass.addEventListener("click", (e) => {
  e.preventDefault();
  modal.style.display = "none";
  forgotModal.style.display = "block";
});

closeForgotPass.onclick = () => { forgotModal.style.display = "none"; };
window.addEventListener("click", (e) => {
  if (e.target === forgotModal) forgotModal.style.display = "none";
});

// Handle form via AJAX
forgotForm.addEventListener("submit", async (e) => {
  e.preventDefault();
  forgotMsg.innerHTML = "<div class='alert'>⏳ Processing...</div>";
  const formData = new FormData(forgotForm);
  try {
    const response = await fetch("fpass.php", { method: "POST", body: formData });
    const result = await response.text();
    forgotMsg.innerHTML = result;
  } catch (err) {
    forgotMsg.innerHTML = "<div class='alert error'>⚠️ Something went wrong. Try again later.</div>";
  }
});
</script>
</body>
</html>
