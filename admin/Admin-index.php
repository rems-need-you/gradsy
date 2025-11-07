<?php 
include ('partials/constants.php');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "admins") {
    header('Location: ' . SITEURL . 'Admin-login.php');
    exit();
}

// Get session variables with defaults
$admin_id = $_SESSION['Id'] ?? 0;
$admin_email = $_SESSION['user'] ?? '';
$admin_name = $_SESSION['teacher_name'] ?? 'admins';
$admin_profile_pic = '../images/teacher.png';

// Only query profile pic if we have an admin ID
if ($admin_id) {
    $stmt = $conn->prepare("SELECT profile_pic FROM admins WHERE Id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            if (!empty($row['profile_pic'])) {
                // Use '../uploads/' prefix here based on updatep.php logic
                $admin_profile_pic = '../uploads/' . htmlspecialchars($row['profile_pic']); 
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> Registrar / Administrator Dashboard</title>
    <link rel="stylesheet" href="../css/adminbox.css">
</head>
<body>
    <div class="topbar">
        <div class="logo"><strong>📘 Registrar / Administrator Dashboard</strong></div>
        <div class="teacher-info">
            <img src="<?php echo htmlspecialchars($admin_profile_pic); ?>" alt="Profile" id="teacherProfilePic">
            <div><small><?php echo htmlspecialchars($admin_email); ?></small></div>
        </div>
    </div>
    
    <div class="sidebar">
        <ul>
            <li><a href="<?php echo SITEURL; ?>Admin-manage.php" target="mainFrame">👥 Manage Accounts</a></li>
            <li><a href="<?php echo SITEURL; ?>Admin-record.php" target="mainFrame">📂 Records</a></li>
            <li><a href="<?php echo SITEURL; ?>Admin-adds.php" target="mainFrame">➕ Add Student</a></li>
            <li><a href="<?php echo SITEURL; ?>Admin-asst.php" target="mainFrame"> ➕ Assign Teacher</a></li>
            <li><a href="<?php echo SITEURL; ?>Admin-about.php" target="mainFrame">ℹ️ About</a></li>
            <li><a href="<?php echo SITEURL; ?>Admin-contact.php" target="mainFrame">📧 Contact</a></li>
            <li class="logout"><a href="<?php echo SITEURL; ?>Admin-logout.php"> Logout</a></li>
        </ul>
    </div>

    <div id="profileModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Edit Profile</h2>
            
            <div id="profileMsg" style="margin-bottom:15px; white-space: pre-wrap; text-align: left;"></div> 
            
            <form id="editProfileForm" method="POST" enctype="multipart/form-data" action="updatep.php">
                <div class="profile-pic-section">
                    <img id="profilePreview" src="<?php echo htmlspecialchars($admin_profile_pic); ?>" alt="Current Profile">
                    <input type="file" name="profile_image" accept="image/*" id="profileImageInput">
                </div>
                <label>Email</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($admin_email); ?>" readonly>
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
    
    <iframe id="mainFrame" name="mainFrame" class="content"></iframe>

    <script>
    // Get iframe and menu links
    const iframe = document.getElementById("mainFrame");
    // Select sidebar links but exclude the logout item. The original selector used
    // an invalid :not() expression which can throw and halt script execution.
    const links = document.querySelectorAll(".sidebar ul li:not(.logout) a");

        // Load last visited page from localStorage or default to Admin-manage.php
        const lastPage = localStorage.getItem("lastPage") || "<?php echo SITEURL; ?>Admin-manage.php";
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

        // --- Profile Modal Logic ---
        const modal = document.getElementById("profileModal");
        const closeBtn = document.querySelector(".close");
        const profilePic = document.getElementById("teacherProfilePic");
        const imageInput = document.getElementById("profileImageInput");
        const preview = document.getElementById("profilePreview");
        const profileMsg = document.getElementById('profileMsg'); // Message container

        profilePic.addEventListener("click", () => { 
            modal.style.display = "block";
            profileMsg.innerHTML = ''; // Clear message on open
        });
        closeBtn.onclick = () => { modal.style.display = "none"; };
        window.onclick = (event) => { if (event.target === modal) modal.style.display = "none"; };

        // Image preview
        imageInput.addEventListener("change", function(){
            const file = this.files[0];
            if (file) preview.src = URL.createObjectURL(file);
        });
        
        // --- AJAX SUBMISSION PARA SA PROFILE UPDATE (Walang Redirect) ---
        const editProfileForm = document.getElementById('editProfileForm');
        
        // Function para mag-display ng message sa loob ng modal
        function displayModalMessage(type, message) {
            // Gumagamit ng <br> replacement para ma-format ang multi-line messages
            const formattedMessage = message.replace(/\n/g, '<br>');
            profileMsg.innerHTML = `<div class="alert-${type}">${formattedMessage}</div>`;
        }

        editProfileForm.addEventListener('submit', async function(e){
            e.preventDefault(); 

            profileMsg.innerHTML = `<div style="color:#007bff; text-align:center;">⏳ Processing...</div>`;

            const btn = editProfileForm.querySelector('button[type="submit"]');
            btn.disabled = true;
            const formData = new FormData(editProfileForm);
            
            try {
                const response = await fetch(editProfileForm.action, { 
                    method: 'POST', 
                    body: formData 
                });
                
                if (!response.ok) {
                    throw new Error('Server returned an error.');
                }

                // Asahan na JSON ang ibabalik
                const result = await response.json(); 

                if (result.success) {
                    displayModalMessage('success', result.message); 
                    
                    // I-update ang profile picture
                    if (result.profile_pic) {
                        const pic = document.getElementById('teacherProfilePic');
                        // Magdagdag ng timestamp para mag-refresh ang image sa browser cache
                        pic.src = result.profile_pic + '?' + new Date().getTime();
                        preview.src = result.profile_pic + '?' + new Date().getTime();
                    }
                    
                    // I-clear ang password fields
                    editProfileForm.querySelector('input[name="current_password"]').value = '';
                    editProfileForm.querySelector('input[name="new_password"]').value = '';

                } else {
                    displayModalMessage('error', result.message);
                }

            } catch (error) {
                // Catch network or JSON parsing errors
                displayModalMessage('error', '⚠️ An unexpected error occurred. Please check your connection or server logs.');
                console.error('AJAX Error:', error);
            } finally {
                btn.disabled = false;
            }
        });
        
        // --- Forgot Password Modal Logic (No Change) ---
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

        // Handle forgot password form via AJAX (No Change)
        forgotForm.addEventListener("submit", async (e) => {
            e.preventDefault();
            forgotMsg.innerHTML = "<div class='alert'>⏳ Processing...</div>";
            const formData = new FormData(forgotForm);
            try {
                // Ensure fpassd.php returns a simple text message
                const response = await fetch("fpassd.php", { method: "POST", body: formData });
                const result = await response.text();
                forgotMsg.innerHTML = result;
            } catch (err) {
                forgotMsg.innerHTML = "<div class='alert error'>⚠️ Something went wrong. Try again later.</div>";
            }
        });

    </script>
</body>
</html>