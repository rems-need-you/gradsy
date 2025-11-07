<?php
ob_start();
include ('partials/constants.php');
if (session_status() === PHP_SESSION_NONE) session_start();

// --- JSON RESPONSE FUNCTION (NEW) ---
// Gagamitin ito para magpadala ng sagot at mag-exit
function sendJsonResponse($success, $message, $profile_pic_path = null) {
    ob_clean(); // Linisin ang output buffer bago magpadala ng JSON
    header('Content-Type: application/json');
    // $profile_pic_path should be the web path (e.g., '../uploads/filename.jpg')
    echo json_encode(['success' => $success, 'message' => $message, 'profile_pic' => $profile_pic_path]);
    exit();
}

// ===== SESSION CHECK & ADMIN ID ACQUISITION (Kung failed, mag-JSON error) =====
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admins') {
    sendJsonResponse(false, "❌ Unauthorized access. Please login again."); // Changed to JSON response
}

$admin_id = $_SESSION['Id'] ?? 0; // Correct variable used
if (!$admin_id) {
    sendJsonResponse(false, "❌ Admin ID not found. Please login again."); // Changed to JSON response    
}

// ===== FETCH CURRENT PASSWORD & PROFILE PIC FILENAME (from DB) =====
$stmt = $conn->prepare("SELECT Password, profile_pic FROM admins WHERE Id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$stmt->bind_result($currentPassword, $currentProfilePicFilename);
$stmt->fetch();
$stmt->close();

// ===== HANDLE FORM SUBMISSION =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $current_password = trim($_POST['current_password'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    $errors = [];
    $update_successful = false;
    $new_profile_pic_filename = null; // Variable para sa bagong filename

    // --- 1. Current Password Validation ---
    // The current password must be correct to make any changes
    if ($current_password !== $currentPassword) {
         $errors[] = "❌ Your current password is incorrect.";
    } else {
        
        // --- 2. PASSWORD CHANGE ---
        if (!empty($new_password)) {
            if (strlen($new_password) < 8) {
                $errors[] = "⚠️ New password must be at least 8 characters long.";
            } else {
                $updatePwd = $conn->prepare("UPDATE admins SET Password = ? WHERE Id = ?");
                $updatePwd->bind_param("si", $new_password, $admin_id); 
                if ($updatePwd->execute()) {
                    $update_successful = true;
                    // Walang session set
                } else {
                    $errors[] = "❌ Failed to update password in the database.";
                }
                $updatePwd->close();
            }
        }

        // --- 3. PROFILE IMAGE UPLOAD ---
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
            $fileTmp = $_FILES['profile_image']['tmp_name'];
            $fileExt = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            $allowedExt = ['png', 'jpg', 'jpeg', 'gif'];
            $uploadDir = '../uploads/'; 
            
            if (!in_array($fileExt, $allowedExt)) {
                $errors[] = "⚠️ Invalid file type. Only PNG, JPG, JPEG, GIF allowed.";
            } else {
                // Create a unique filename based on ID and timestamp
                $fileName = 'admin_profile_'.$admin_id.'_'.time().'.'.$fileExt; 
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $filePath = $uploadDir.$fileName;
                
                if (move_uploaded_file($fileTmp, $filePath)) {
                    // Update DB with ONLY the filename
                    $updatePic = $conn->prepare("UPDATE admins SET profile_pic = ? WHERE Id = ?");
                    $updatePic->bind_param("si", $fileName, $admin_id); 
                    
                    if ($updatePic->execute()) {
                        $update_successful = true;
                        $new_profile_pic_filename = $fileName; // Capture filename for successful update
                        // Walang session set

                        // Delete the old file if it exists and is not the default
                        if (!empty($currentProfilePicFilename) && file_exists($uploadDir . $currentProfilePicFilename) && $currentProfilePicFilename !== $fileName) {
                             @unlink($uploadDir . $currentProfilePicFilename); 
                        }
                    } else {
                         $errors[] = "❌ Failed to update profile picture in the database.";
                         @unlink($filePath); // Delete uploaded file if DB update fails
                    }
                    $updatePic->close();
                } else {
                    $errors[] = "❌ Failed to upload image.";
                }
            }
        }
    }

    // ===== SEND JSON RESPONSE (REPLACING OLD REDIRECT LOGIC) =====
    if (!empty($errors)) {
        // May errors
        sendJsonResponse(false, implode("\n", $errors));
    } elseif ($update_successful) {
        // Success
        $path = $new_profile_pic_filename ? '../uploads/' . $new_profile_pic_filename : null;
        sendJsonResponse(true, "✅ Profile updated successfully!", $path);
    } else {
        // Walang ginawang changes
        sendJsonResponse(true, "ℹ️ No changes were requested or saved.", null);
    }

}
// Kung hindi POST request, mag-return ng error (dapat hindi na maabot ito)
sendJsonResponse(false, "❌ Invalid request method."); 
?>