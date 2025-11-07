<?php
session_start();
include('partials/constants.php');
session_destroy();
header('Location: ' . SITEURL . 'Admin-login.php');
exit;
?>
