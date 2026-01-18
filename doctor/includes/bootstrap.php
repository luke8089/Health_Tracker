<?php
/**
 * Doctor Bootstrap
 * Optimized initialization for doctor directory
 */

// Load main bootstrap (correct path: doctor/includes -> root/src/helpers)
require_once __DIR__ . '/../../src/helpers/Bootstrap.php';
require_once __DIR__ . '/../../src/helpers/QueryOptimizer.php';

// Ensure user is logged in and is a doctor
$auth->requireLogin();
$auth->requireRole('doctor');

// Get current doctor user (cached)
$currentUser = $auth->getCurrentUser();

// Initialize Database connection for legacy $conn usage
require_once __DIR__ . '/../../src/helpers/Database.php';
$database = new Database();
$conn = $database->connect();

// Initialize Query Optimizer for database operations
$queryOptimizer = new QueryOptimizer();

// Doctor-specific helper functions

/**
 * Get doctor statistics with caching
 */
function getDoctorStats($doctorId, $optimizer) {
    $cacheKey = "doctor_stats_{$doctorId}";
    $stats = SimpleCache::get($cacheKey);
    
    if (!$stats) {
        $stats = [
            'total_patients' => $optimizer->cachedQueryOne(
                "SELECT COUNT(DISTINCT user_id) as total 
                 FROM user_doctor_connections 
                 WHERE doctor_id = ? AND status = 'active'",
                [$doctorId],
                300
            )['total'] ?? 0,
            
            'pending_verifications' => $optimizer->cachedQueryOne(
                "SELECT COUNT(*) as total 
                 FROM habit_completions hc
                 INNER JOIN habits h ON hc.habit_id = h.id
                 INNER JOIN user_doctor_connections udc ON h.user_id = udc.user_id
                 WHERE hc.verification_status = 'pending' 
                 AND udc.doctor_id = ? AND udc.status = 'active'",
                [$doctorId],
                60 // 1 minute cache for pending items
            )['total'] ?? 0,
            
            'total_assessments' => $optimizer->cachedQueryOne(
                "SELECT COUNT(DISTINCT a.id) as total 
                 FROM assessments a 
                 INNER JOIN user_doctor_connections udc ON a.user_id = udc.user_id 
                 WHERE udc.doctor_id = ? AND udc.status = 'active'",
                [$doctorId],
                300
            )['total'] ?? 0,
            
            'unread_messages' => $optimizer->cachedQueryOne(
                "SELECT COUNT(*) as total 
                 FROM messages 
                 WHERE recipient_id = ? AND is_read = 0",
                [$doctorId],
                30 // 30 seconds cache for messages
            )['total'] ?? 0
        ];
        
        SimpleCache::set($cacheKey, $stats, 180); // 3 minutes
    }
    
    return $stats;
}

/**
 * Get doctor's patients with caching
 */
function getDoctorPatients($doctorId, $optimizer, $limit = null) {
    $cacheKey = "doctor_patients_{$doctorId}_" . ($limit ?? 'all');
    $patients = SimpleCache::get($cacheKey);
    
    if (!$patients) {
        $sql = "
            SELECT DISTINCT u.id, u.name, u.email, u.avatar, u.phone,
                   udc.created_at as connected_at,
                   (SELECT COUNT(*) FROM assessments WHERE user_id = u.id) as assessment_count,
                   (SELECT severity FROM assessments WHERE user_id = u.id ORDER BY created_at DESC LIMIT 1) as latest_severity,
                   (SELECT score FROM assessments WHERE user_id = u.id ORDER BY created_at DESC LIMIT 1) as latest_score
            FROM users u
            INNER JOIN user_doctor_connections udc ON u.id = udc.user_id
            WHERE udc.doctor_id = ? AND udc.status = 'active'
            ORDER BY udc.created_at DESC
        ";
        
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        
        $patients = $optimizer->cachedQuery($sql, [$doctorId], 300);
        SimpleCache::set($cacheKey, $patients, 300);
    }
    
    return $patients;
}

/**
 * Get pending habit verifications for doctor
 */
function getPendingVerifications($doctorId, $optimizer, $limit = null) {
    $cacheKey = "doctor_pending_verifications_{$doctorId}_" . ($limit ?? 'all');
    $verifications = SimpleCache::get($cacheKey);
    
    if (!$verifications) {
        $sql = "
            SELECT hc.*, 
                   h.name as habit_name, h.frequency,
                   u.name as user_name, u.email as user_email, u.avatar as user_avatar
            FROM habit_completions hc
            INNER JOIN habits h ON hc.habit_id = h.id
            INNER JOIN users u ON hc.user_id = u.id
            INNER JOIN user_doctor_connections udc ON u.id = udc.user_id
            WHERE hc.verification_status = 'pending'
            AND udc.doctor_id = ? AND udc.status = 'active'
            ORDER BY hc.created_at DESC
        ";
        
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        
        $verifications = $optimizer->cachedQuery($sql, [$doctorId], 60);
        SimpleCache::set($cacheKey, $verifications, 60);
    }
    
    return $verifications;
}

/**
 * Get recent messages for doctor
 */
function getDoctorMessages($doctorId, $optimizer, $limit = null) {
    $cacheKey = "doctor_messages_{$doctorId}_" . ($limit ?? 'all');
    $messages = SimpleCache::get($cacheKey);
    
    if (!$messages) {
        $sql = "
            SELECT m.*, 
                   u.name as sender_name, u.avatar as sender_avatar, u.role as sender_role
            FROM messages m
            INNER JOIN users u ON m.sender_id = u.id
            WHERE m.recipient_id = ?
            ORDER BY m.created_at DESC
        ";
        
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        
        $messages = $optimizer->cachedQuery($sql, [$doctorId], 60);
        SimpleCache::set($cacheKey, $messages, 60);
    }
    
    return $messages;
}

/**
 * Clear doctor cache (call after data changes)
 */
function clearDoctorCache($doctorId, $type = 'all') {
    switch ($type) {
        case 'stats':
            SimpleCache::delete("doctor_stats_{$doctorId}");
            break;
        case 'patients':
            SimpleCache::delete("doctor_patients_{$doctorId}_all");
            SimpleCache::delete("doctor_patients_{$doctorId}_5");
            break;
        case 'verifications':
            SimpleCache::delete("doctor_pending_verifications_{$doctorId}_all");
            SimpleCache::delete("doctor_pending_verifications_{$doctorId}_5");
            SimpleCache::delete("doctor_stats_{$doctorId}"); // Also clear stats
            break;
        case 'messages':
            SimpleCache::delete("doctor_messages_{$doctorId}_all");
            SimpleCache::delete("doctor_messages_{$doctorId}_5");
            SimpleCache::delete("doctor_stats_{$doctorId}"); // Also clear stats
            break;
        case 'all':
        default:
            // Clear all doctor-related caches
            $patterns = ['stats', 'patients', 'verifications', 'messages'];
            foreach ($patterns as $pattern) {
                clearDoctorCache($doctorId, $pattern);
            }
            break;
    }
}

/**
 * Handle AJAX requests for doctor actions
 */
function handleDoctorAjaxRequest() {
    if (!isAjaxRequest() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        return false;
    }
    
    if (!isset($_POST['action'])) {
        return false;
    }
    
    // Validate CSRF for all POST requests
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'error' => 'Invalid request'], 403);
    }
    
    return true;
}

// Set page title helper for doctors
function setDoctorPageTitle($title) {
    global $pageTitle;
    $pageTitle = $title . ' - Doctor Portal - Health Tracker';
    return $pageTitle;
}
