<?php
/**
 * Health Tracker Application Entry Point
 */

// Set up error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include necessary files (Auth will handle session start)
require_once __DIR__ . '/../src/helpers/Bootstrap.php';
require_once __DIR__ . '/../src/helpers/Auth.php';
require_once __DIR__ . '/../src/helpers/Utils.php';

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$auth = new Auth();

// Check if user is logged in
if ($auth->isLoggedIn()) {
    $currentUser = $auth->getCurrentUser();
    
    // Redirect to appropriate dashboard based on role
    switch ($currentUser['role']) {
        case 'admin':
            header('Location: ' . APP_BASE_PATH . '/admin/dashboard.php');
            break;
        case 'doctor':
            header('Location: ' . APP_BASE_PATH . '/doctor/dashboard.php');
            break;
        case 'user':
        default:
            header('Location: ' . APP_BASE_PATH . '/public/dashboard.php');
            break;
    }
    exit;
}

// If not logged in, redirect to landing page
header('Location: ../landing_page/index.php');
exit;
?>