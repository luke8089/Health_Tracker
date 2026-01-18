<?php
/**
 * Admin API Endpoint Handler
 * Health Tracker Application - Admin Panel
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Start session and include dependencies
session_start();
require_once __DIR__ . '/../src/helpers/Auth.php';
require_once __DIR__ . '/../src/helpers/Database.php';

$auth = new Auth();

// Check if user is admin
if (!$auth->isLoggedIn() || !$auth->hasRole('admin')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Access denied. Admin privileges required.'
    ]);
    exit;
}

// Response helper
function apiResponse($data = null, $success = true, $message = '', $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Get request parameters
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = (new Database())->connect();
    
    // Route requests based on action
    switch ($action) {
        case 'get_user_details':
            if ($method !== 'GET') {
                apiResponse(null, false, 'Method not allowed', 405);
            }
            
            if (!isset($_GET['user_id'])) {
                apiResponse(null, false, 'User ID required', 400);
            }
            
            $userId = intval($_GET['user_id']);
            
            // Get user basic info
            $stmt = $db->prepare("
                SELECT id, name, email, role, avatar, phone, bio, created_at, updated_at 
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Set default values for fields that might not exist
            $user['age'] = $user['age'] ?? 'N/A';
            $user['gender'] = $user['gender'] ?? 'N/A';
            
            if (!$user) {
                apiResponse(null, false, 'User not found', 404);
            }
            
            // Initialize stats array
            $user['stats'] = [];
            
            // Get assessment count
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM assessments WHERE user_id = ?");
            $stmt->execute([$userId]);
            $user['stats']['assessment_count'] = intval($stmt->fetchColumn());
            
            // Get habit count
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM habits WHERE user_id = ?");
            $stmt->execute([$userId]);
            $user['stats']['habit_count'] = intval($stmt->fetchColumn());
            
            // Get message count
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM messages WHERE sender_id = ? OR recipient_id = ?");
            $stmt->execute([$userId, $userId]);
            $user['stats']['message_count'] = intval($stmt->fetchColumn());
            
            // Get recent assessments (last 5)
            $stmt = $db->prepare("
                SELECT id, type, score, max_score, severity, created_at 
                FROM assessments 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT 5
            ");
            $stmt->execute([$userId]);
            $user['recent_assessments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get recent habits (last 5)
            $stmt = $db->prepare("
                SELECT id, name, frequency, status, streak, target_days, created_at
                FROM habits 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT 5
            ");
            $stmt->execute([$userId]);
            $user['recent_habits'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get doctor info if user is a doctor
            if ($user['role'] === 'doctor') {
                $stmt = $db->prepare("
                    SELECT specialty, license_number, availability 
                    FROM doctors 
                    WHERE id = ?
                ");
                $stmt->execute([$userId]);
                $doctorInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($doctorInfo) {
                    $user['doctor_info'] = $doctorInfo;
                    
                    // Get patient count for doctor
                    $stmt = $db->prepare("
                        SELECT COUNT(DISTINCT user_id) as count 
                        FROM user_doctor_connections 
                        WHERE doctor_id = ? AND status = 'active'
                    ");
                    $stmt->execute([$userId]);
                    $user['stats']['patient_count'] = intval($stmt->fetchColumn());
                    
                    // Get recommendation count
                    $stmt = $db->prepare("
                        SELECT COUNT(*) as count 
                        FROM doctor_recommendations 
                        WHERE doctor_id = ?
                    ");
                    $stmt->execute([$userId]);
                    $user['stats']['recommendation_count'] = intval($stmt->fetchColumn());
                }
            }
            
            apiResponse(['user' => $user], true, 'User details retrieved successfully');
            break;
            
        case 'get_user_activities':
            if ($method !== 'GET') {
                apiResponse(null, false, 'Method not allowed', 405);
            }
            
            if (!isset($_GET['user_id'])) {
                apiResponse(null, false, 'User ID required', 400);
            }
            
            $userId = intval($_GET['user_id']);
            
            // Get recent activities
            $stmt = $db->prepare("
                SELECT id, type, duration_minutes, calories_burned, created_at 
                FROM activities 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT 10
            ");
            $stmt->execute([$userId]);
            $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            apiResponse(['activities' => $activities], true, 'Activities retrieved successfully');
            break;
            
        case 'get_statistics':
            if ($method !== 'GET') {
                apiResponse(null, false, 'Method not allowed', 405);
            }
            
            $stats = [];
            
            // Total users
            $stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'user'");
            $stats['total_users'] = intval($stmt->fetchColumn());
            
            // Total doctors
            $stmt = $db->query("SELECT COUNT(*) FROM doctors");
            $stats['total_doctors'] = intval($stmt->fetchColumn());
            
            // Total assessments
            $stmt = $db->query("SELECT COUNT(*) FROM assessments");
            $stats['total_assessments'] = intval($stmt->fetchColumn());
            
            // Total habits
            $stmt = $db->query("SELECT COUNT(*) FROM habits WHERE status = 'active'");
            $stats['total_habits'] = intval($stmt->fetchColumn());
            
            // Pending habit requests
            $stmt = $db->query("SELECT COUNT(*) FROM habit_completions WHERE verification_status = 'pending'");
            $stats['pending_requests'] = intval($stmt->fetchColumn());
            
            apiResponse($stats, true, 'Statistics retrieved successfully');
            break;
            
        case 'update_doctor':
            if ($method !== 'POST') {
                apiResponse(null, false, 'Method not allowed', 405);
            }
            
            $doctorId = intval($_POST['doctor_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $specialty = trim($_POST['specialty'] ?? '');
            $licenseNumber = trim($_POST['license_number'] ?? '');
            $availability = $_POST['availability'] ?? 'offline';
            
            if (!$doctorId || !$name || !$email || !$specialty || !$licenseNumber) {
                apiResponse(null, false, 'Missing required fields', 400);
            }
            
            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                apiResponse(null, false, 'Invalid email address', 400);
            }
            
            // Validate availability
            $validAvailability = ['available', 'busy', 'offline'];
            if (!in_array($availability, $validAvailability)) {
                apiResponse(null, false, 'Invalid availability status', 400);
            }
            
            // Check if email is already used by another user
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $doctorId]);
            if ($stmt->fetch()) {
                apiResponse(null, false, 'Email address is already in use', 400);
            }
            
            // Begin transaction
            $db->beginTransaction();
            
            try {
                // Update users table
                $stmt = $db->prepare("
                    UPDATE users 
                    SET name = ?, email = ?, phone = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ? AND role = 'doctor'
                ");
                $stmt->execute([$name, $email, $phone, $doctorId]);
                
                // Update doctors table
                $stmt = $db->prepare("
                    UPDATE doctors 
                    SET specialty = ?, license_number = ?, availability = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$specialty, $licenseNumber, $availability, $doctorId]);
                
                // Commit transaction
                $db->commit();
                
                apiResponse([
                    'doctor_id' => $doctorId,
                    'name' => $name,
                    'email' => $email
                ], true, 'Doctor profile updated successfully');
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $db->rollBack();
                throw $e;
            }
            break;
            
        default:
            apiResponse(null, false, 'Invalid action', 400);
    }
    
} catch (Exception $e) {
    error_log('Admin API Error: ' . $e->getMessage());
    apiResponse(null, false, 'Internal server error: ' . $e->getMessage(), 500);
}
