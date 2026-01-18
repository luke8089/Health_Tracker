<?php
/**
 * API Endpoint Handler
 * Health Tracker Application
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
require_once __DIR__ . '/../src/helpers/Utils.php';
require_once __DIR__ . '/../src/models/User.php';
require_once __DIR__ . '/../src/models/Assessment.php';
require_once __DIR__ . '/../src/models/Recommendation.php';
require_once __DIR__ . '/../src/models/Habit.php';
require_once __DIR__ . '/../src/models/Activity.php';
require_once __DIR__ . '/../src/models/Message.php';
require_once __DIR__ . '/../src/models/Doctor.php';

$auth = new Auth();

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['endpoint'] ?? '';

// Get JSON input for POST/PUT requests
$input = null;
if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
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

// Authentication check for protected endpoints
$protectedEndpoints = [
    'habits', 'activities', 'assessments', 'recommendations', 
    'messages', 'profile', 'doctor', 'admin'
];

foreach ($protectedEndpoints as $endpoint) {
    if (strpos($path, $endpoint) === 0 && !$auth->isLoggedIn()) {
        apiResponse(null, false, 'Authentication required', 401);
    }
}

// Route requests
try {
    switch ($path) {
        // Authentication endpoints
        case 'auth/login':
            if ($method !== 'POST') {
                apiResponse(null, false, 'Method not allowed', 405);
            }
            
            if (!$input['email'] || !$input['password']) {
                apiResponse(null, false, 'Email and password required', 400);
            }
            
            $result = $auth->login($input['email'], $input['password']);
            apiResponse($result['user'] ?? null, $result['success'], $result['message'], $result['success'] ? 200 : 400);
            break;

        case 'auth/register':
            if ($method !== 'POST') {
                apiResponse(null, false, 'Method not allowed', 405);
            }
            
            $required = ['email', 'password', 'name'];
            foreach ($required as $field) {
                if (!isset($input[$field]) || empty($input[$field])) {
                    apiResponse(null, false, "$field is required", 400);
                }
            }
            
            $result = $auth->register($input);
            apiResponse(null, $result['success'], $result['message'], $result['success'] ? 201 : 400);
            break;

        case 'auth/logout':
            $result = $auth->logout();
            apiResponse(null, $result['success'], 'Logged out successfully');
            break;

        case 'auth/user':
            $user = $auth->getCurrentUser();
            apiResponse($user, true, 'Current user retrieved');
            break;
            
        case 'check_session':
            // Check if user session is valid
            $isAuthenticated = $auth->isLoggedIn();
            apiResponse([
                'authenticated' => $isAuthenticated,
                'user' => $isAuthenticated ? $auth->getCurrentUser() : null
            ], true, $isAuthenticated ? 'Session valid' : 'Session invalid');
            break;

        // Habits endpoints
        case 'habits':
            $habitModel = new Habit();
            $userId = $auth->getCurrentUser()['id'];
            
            switch ($method) {
                case 'GET':
                    $habits = $habitModel->getUserHabits($userId);
                    apiResponse($habits, true, 'Habits retrieved');
                    break;
                    
                case 'POST':
                    if (!$input['name']) {
                        apiResponse(null, false, 'Habit name is required', 400);
                    }
                    
                    $result = $habitModel->create($userId, $input);
                    apiResponse(null, $result, $result ? 'Habit created' : 'Failed to create habit', $result ? 201 : 400);
                    break;
                    
                default:
                    apiResponse(null, false, 'Method not allowed', 405);
            }
            break;

        case 'habits/increment':
            if ($method !== 'POST' || !isset($_GET['id'])) {
                apiResponse(null, false, 'Invalid request', 400);
            }
            
            $habitModel = new Habit();
            $result = $habitModel->incrementStreak($_GET['id']);
            apiResponse(null, $result, $result ? 'Streak incremented' : 'Failed to increment streak');
            break;

        case 'habits/stats':
            if ($method !== 'GET') {
                apiResponse(null, false, 'Method not allowed', 405);
            }
            
            $habitModel = new Habit();
            $userId = $auth->getCurrentUser()['id'];
            $stats = $habitModel->getHabitStats($userId);
            apiResponse($stats, true, 'Habit stats retrieved');
            break;

        // Activities endpoints
        case 'activities':
            $activityModel = new Activity();
            $userId = $auth->getCurrentUser()['id'];
            
            switch ($method) {
                case 'GET':
                    $limit = $_GET['limit'] ?? null;
                    $activities = $activityModel->getUserActivities($userId, $limit);
                    apiResponse($activities, true, 'Activities retrieved');
                    break;
                    
                case 'POST':
                    $required = ['type', 'duration_minutes'];
                    foreach ($required as $field) {
                        if (!isset($input[$field])) {
                            apiResponse(null, false, "$field is required", 400);
                        }
                    }
                    
                    $result = $activityModel->create($userId, $input);
                    apiResponse(null, $result, $result ? 'Activity logged' : 'Failed to log activity', $result ? 201 : 400);
                    break;
                    
                default:
                    apiResponse(null, false, 'Method not allowed', 405);
            }
            break;

        case 'activities/stats':
            if ($method !== 'GET') {
                apiResponse(null, false, 'Method not allowed', 405);
            }
            
            $activityModel = new Activity();
            $userId = $auth->getCurrentUser()['id'];
            $stats = $activityModel->getActivityStats($userId);
            apiResponse($stats, true, 'Activity stats retrieved');
            break;

        // Assessments endpoints
        case 'assessments':
            $assessmentModel = new Assessment();
            $userId = $auth->getCurrentUser()['id'];
            
            switch ($method) {
                case 'GET':
                    $assessments = $assessmentModel->getUserAssessments($userId, 10);
                    apiResponse($assessments, true, 'Assessments retrieved');
                    break;
                    
                case 'POST':
                    if (!$input['responses'] || !is_array($input['responses'])) {
                        apiResponse(null, false, 'Assessment responses are required', 400);
                    }
                    
                    $result = $assessmentModel->create($userId, $input['responses']);
                    if ($result) {
                        // Generate recommendations
                        $recommendationModel = new Recommendation();
                        $recommendationModel->generateRecommendations(
                            $result['id'], 
                            $input['responses'], 
                            $result['score'], 
                            $result['severity']
                        );
                        
                        // Auto-connect to doctor if high severity
                        if ($result['severity'] === 'high') {
                            $doctorModel = new Doctor();
                            $doctor = $doctorModel->findAvailableDoctorForAssessment('high');
                            if ($doctor) {
                                $doctorModel->connectToUser($userId, $doctor['id'], $result['id']);
                                
                                // Send notification message
                                $messageModel = new Message();
                                $messageModel->sendAssessmentNotification($userId, $doctor['id'], $result['id']);
                            }
                        }
                        
                        apiResponse($result, true, 'Assessment completed', 201);
                    } else {
                        apiResponse(null, false, 'Failed to create assessment', 400);
                    }
                    break;
                    
                default:
                    apiResponse(null, false, 'Method not allowed', 405);
            }
            break;

        case 'assessments/questions':
            if ($method !== 'GET') {
                apiResponse(null, false, 'Method not allowed', 405);
            }
            
            $assessmentModel = new Assessment();
            $questions = $assessmentModel->getAssessmentQuestions();
            apiResponse($questions, true, 'Assessment questions retrieved');
            break;

        // Recommendations endpoints
        case 'recommendations':
            if ($method !== 'GET') {
                apiResponse(null, false, 'Method not allowed', 405);
            }
            
            $recommendationModel = new Recommendation();
            $userId = $auth->getCurrentUser()['id'];
            $recommendations = $recommendationModel->getUserRecommendations($userId, 10);
            apiResponse($recommendations, true, 'Recommendations retrieved');
            break;

        // Messages endpoints
        case 'messages':
            $messageModel = new Message();
            $userId = $auth->getCurrentUser()['id'];
            
            switch ($method) {
                case 'GET':
                    $type = $_GET['type'] ?? 'all';
                    $limit = $_GET['limit'] ?? 20;
                    $messages = $messageModel->getUserMessages($userId, $type, $limit);
                    apiResponse($messages, true, 'Messages retrieved');
                    break;
                    
                case 'POST':
                    $required = ['recipient_id', 'subject', 'body'];
                    foreach ($required as $field) {
                        if (!isset($input[$field]) || empty($input[$field])) {
                            apiResponse(null, false, "$field is required", 400);
                        }
                    }
                    
                    $result = $messageModel->create($userId, $input['recipient_id'], $input['subject'], $input['body']);
                    apiResponse(null, $result, $result ? 'Message sent' : 'Failed to send message', $result ? 201 : 400);
                    break;
                    
                default:
                    apiResponse(null, false, 'Method not allowed', 405);
            }
            break;

        case 'messages/unread':
            if ($method !== 'GET') {
                apiResponse(null, false, 'Method not allowed', 405);
            }
            
            $messageModel = new Message();
            $userId = $auth->getCurrentUser()['id'];
            $count = $messageModel->getUnreadCount($userId);
            apiResponse(['count' => $count], true, 'Unread count retrieved');
            break;

        case 'messages/mark-read':
            if ($method !== 'POST' || !isset($_GET['id'])) {
                apiResponse(null, false, 'Invalid request', 400);
            }
            
            $messageModel = new Message();
            $userId = $auth->getCurrentUser()['id'];
            $result = $messageModel->markAsRead($_GET['id'], $userId);
            apiResponse(null, $result, $result ? 'Message marked as read' : 'Failed to mark message as read');
            break;

        // Dashboard stats endpoint
        case 'dashboard/stats':
            if ($method !== 'GET') {
                apiResponse(null, false, 'Method not allowed', 405);
            }
            
            $userId = $auth->getCurrentUser()['id'];
            $userModel = new User();
            
            $stats = $userModel->getUserStats($userId);
            apiResponse($stats, true, 'Dashboard stats retrieved');
            break;

        // Profile endpoints
        case 'profile':
            $userModel = new User();
            $userId = $auth->getCurrentUser()['id'];
            
            switch ($method) {
                case 'GET':
                    $user = $userModel->findById($userId);
                    apiResponse($user, true, 'Profile retrieved');
                    break;
                    
                case 'PUT':
                    $result = $userModel->update($userId, $input);
                    apiResponse(null, $result, $result ? 'Profile updated' : 'Failed to update profile');
                    break;
                    
                default:
                    apiResponse(null, false, 'Method not allowed', 405);
            }
            break;

        // Search endpoints
        case 'search/users':
            if ($method !== 'GET' || !isset($_GET['q'])) {
                apiResponse(null, false, 'Search query required', 400);
            }
            
            if (!$auth->hasRole('admin') && !$auth->hasRole('doctor')) {
                apiResponse(null, false, 'Access denied', 403);
            }
            
            $userModel = new User();
            $results = $userModel->searchUsers($_GET['q'], $_GET['role'] ?? null);
            apiResponse($results, true, 'Search results retrieved');
            break;

        default:
            apiResponse(null, false, 'Endpoint not found', 404);
    }

} catch (Exception $e) {
    error_log('API Error: ' . $e->getMessage());
    apiResponse(null, false, 'Internal server error', 500);
}
?>