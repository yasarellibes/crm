<?php
/**
 * Index page - redirect to appropriate page
 */

session_start();
require_once 'config/auth.php';

// If logged in, go to dashboard
// If not logged in, go to login
if (isLoggedIn()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}

exit;
?>