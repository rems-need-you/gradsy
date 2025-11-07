<?php
if (!isset($_SESSION['user'])) {
    $_SESSION['no-login-message'] = "<div class='error text-center'>PLEASE LOGIN IN TO ACCESS!!</div>";
    header('location: ' . SITEURL . 'admin-page.php'); // Redirect to admin page
    exit(); // Stop further script execution after the redirect
}
?>
