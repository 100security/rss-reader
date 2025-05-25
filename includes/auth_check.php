<?php
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    
    $_SESSION['error_message'] = "Please log in to access this page.";
    header("Location: login.php");
    exit();
}
?>