<?php
/**
 * Bootstrap File
 * Loads common dependencies and configurations for all public pages
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set error reporting based on environment
$environment = $_ENV['ENVIRONMENT'] ?? 'production';
if ($environment === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../../logs/error.log');
}

// Set timezone
date_default_timezone_set('Africa/Nairobi');

// Determine base path dynamically for the entire application
function getAppBasePath() {
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $basePath = '';
    
    if (strpos($scriptName, '/public/') !== false) {
        $basePath = substr($scriptName, 0, strpos($scriptName, '/public/'));
    } elseif (strpos($scriptName, '/doctor/') !== false) {
        $basePath = substr($scriptName, 0, strpos($scriptName, '/doctor/'));
    } elseif (strpos($scriptName, '/admin/') !== false) {
        $basePath = substr($scriptName, 0, strpos($scriptName, '/admin/'));
    } elseif (strpos($scriptName, '/landing_page/') !== false) {
        $basePath = substr($scriptName, 0, strpos($scriptName, '/landing_page/'));
    }
    
    return $basePath;
}

// Make base path globally available
if (!defined('APP_BASE_PATH')) {
    define('APP_BASE_PATH', getAppBasePath());
}

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Load core helpers
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Utils.php';
require_once __DIR__ . '/Config.php';

// Auto-loader for models
spl_autoload_register(function ($class) {
    $modelFile = __DIR__ . '/../models/' . $class . '.php';
    if (file_exists($modelFile)) {
        require_once $modelFile;
    }
});

// Initialize auth globally
$auth = new Auth();

// CSRF Token Generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Simple caching mechanism
class SimpleCache {
    private static $cache = [];
    private static $ttl = 300; // 5 minutes default
    
    public static function get($key) {
        if (isset(self::$cache[$key])) {
            if (time() < self::$cache[$key]['expires']) {
                return self::$cache[$key]['data'];
            }
            unset(self::$cache[$key]);
        }
        return null;
    }
    
    public static function set($key, $data, $ttl = null) {
        $ttl = $ttl ?? self::$ttl;
        self::$cache[$key] = [
            'data' => $data,
            'expires' => time() + $ttl
        ];
    }
    
    public static function delete($key) {
        unset(self::$cache[$key]);
    }
    
    public static function clear() {
        self::$cache = [];
    }
}

// Helper function to validate CSRF token
function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Helper function to get CSRF token
function getCsrfToken() {
    return $_SESSION['csrf_token'] ?? '';
}

// Helper function to sanitize input
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Helper function for JSON responses
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Helper function for redirects
function redirect($url, $message = null, $type = 'info') {
    if ($message) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }
    header('Location: ' . $url);
    exit;
}

// Helper function to check if request is AJAX
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// Helper function for flash messages
function getFlashMessage() {
    $message = $_SESSION['flash_message'] ?? null;
    $type = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
    return $message ? ['message' => $message, 'type' => $type] : null;
}

// Performance monitoring
if ($environment === 'development') {
    define('APP_START_TIME', microtime(true));
    define('APP_START_MEMORY', memory_get_usage());
    
    register_shutdown_function(function() {
        $executionTime = round((microtime(true) - APP_START_TIME) * 1000, 2);
        $memoryUsage = round((memory_get_usage() - APP_START_MEMORY) / 1024 / 1024, 2);
        error_log("Page: {$_SERVER['PHP_SELF']} | Time: {$executionTime}ms | Memory: {$memoryUsage}MB");
    });
}
