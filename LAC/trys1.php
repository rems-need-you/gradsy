<?php
include('../partials-front/constantsss.php');
if (session_status() === PHP_SESSION_NONE) session_start();

// ✅ Check if teacher is logged in
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "teacher") {
    header('Location: ' . SITEURL . 'LAC/login.php');
    exit();
}

// ✅ Validate POST data
$subject = $_POST['subject'] ?? '';
$grade   = $_POST['grade'] ?? '';
$section = $_POST['section'] ?? '';

if (empty($subject) || empty($grade) || empty($section)) {
    echo "<script>alert('Invalid class selection.'); window.history.back();</script>";
    exit();
}

// ✅ Set session values for the selected class
$_SESSION['subject'] = $subject;
$_SESSION['grade']   = $grade;
$_SESSION['section'] = $section;

// ✅ Redirect to LACeditgrades.php (grade sheet)
header('Location: ' . SITEURL . 'LACeditgrades.php');
exit();
?>
