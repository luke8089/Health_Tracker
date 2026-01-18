<?php
/**
 * Logout Handler
 * Health Tracker Application
 */

require_once __DIR__ . '/../src/helpers/Auth.php';

// Auth class will handle session start
$auth = new Auth();

// Perform logout (this clears session and deletes cookies)
$result = $auth->logout();

// Prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

// Redirect to landing page
header('Location: ../landing_page/index.php');
exit;
?>