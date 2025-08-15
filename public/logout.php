<?php
require_once __DIR__ . '/../config.php';
use inc\classes\Auth;

// Start output buffering to prevent unwanted output
ob_start();

// Clear session data
$_SESSION = [];

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}
// logout user
Auth::logout();

// Redirect to login page
header("Location: {$config->pUrl}/login.php");

// Clear output buffer
ob_end_flush();
exit;