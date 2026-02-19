<?php
// admin/logout.php
// Admin Logout

require_once '../includes/auth.php';
Auth::logout();
header('Location: login.php');
exit;
?>