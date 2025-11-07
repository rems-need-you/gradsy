<?php
include('../partials-front/constantsss.php');
if (session_status() === PHP_SESSION_NONE) session_start();

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $Email = $_POST['Email'] ?? '';

    $stmt = $conn->prepare("SELECT id FROM lac_account WHERE Email = ?");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("s", $Email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $msg = "<div class='alert error'>❌ No LAC account found with this email.</div>";
    } else {
        // ✅ Generate temporary password
        $tempPass = substr(str_shuffle("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 10);
        $stmt->bind_result($id);
        $stmt->fetch();
        $stmt->close();

        $update = $conn->prepare("UPDATE lac_account SET Password = ? WHERE id = ?");
        $update->bind_param("si", $tempPass, $id);
        $update->execute();
        $update->close();

        // ⚠️ Optional: send email in future
        $msg = "<div class='alert success'>✅ Temporary password generated: <strong>$tempPass</strong></div>";
    }
}
echo $msg;
?>
