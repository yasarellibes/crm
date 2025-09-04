<?php
/**
 * Logout functionality
 */

session_start();
require_once 'config/auth.php';

// Logout user
logoutUser();

// Redirect to login page
header('Location: login.php?message=logout');
exit;
?>