<?php
/**
 * Habits Tracking Page
 * Health Tracker Application
 */

require_once __DIR__ . '/../src/helpers/Auth.php';
require_once __DIR__ . '/../src/models/Habit.php';
require_once __DIR__ . '/../src/models/HabitCompletion.php';
require_once __DIR__ . '/../src/helpers/Utils.php';
require_once __DIR__ . '/../mail/Mailer.php';

// Check authentication
$auth = new Auth();
$auth->requireLogin();
$currentUser = $auth->getCurrentUser();
$userId = $currentUser['id'];

$habitModel = new Habit();
$completionModel = new HabitCompletion();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'check_edit_permission':
            // Just check if user has approval without creating a request
            $db = new Database();
            $conn = $db->connect();
            
            // Check if user has an approved request
            $approvedStmt = $conn->prepare("
                SELECT id FROM habit_edit_requests 
                WHERE habit_id = ? AND user_id = ? AND status = 'approved'
                ORDER BY reviewed_at DESC LIMIT 1
            ");
            $approvedStmt->execute([$_POST['habit_id'], $userId]);
            $hasApproval = $approvedStmt->fetch();
            
            if ($hasApproval) {
                echo json_encode([
                    'success' => true,
                    'has_approval' => true,
                    'message' => 'You have approval to edit this habit.'
                ]);
            } else {
                // Check if there's a pending request
                $pendingStmt = $conn->prepare("
                    SELECT id FROM habit_edit_requests 
                    WHERE habit_id = ? AND user_id = ? AND status = 'pending'
                    LIMIT 1
                ");
                $pendingStmt->execute([$_POST['habit_id'], $userId]);
                $hasPending = $pendingStmt->fetch();
                
                echo json_encode([
                    'success' => true,
                    'has_approval' => false,
                    'has_pending' => (bool)$hasPending,
                    'message' => $hasPending ? 'You have a pending edit request for this habit.' : 'No approval found. Please request edit permission.'
                ]);
            }
            exit;
            
        case 'request_edit':
            // Request edit permission from admin
            $db = new Database();
            $conn = $db->connect();
            
            // Check if there's already a pending request for this habit
            $checkStmt = $conn->prepare("
                SELECT id, status FROM habit_edit_requests 
                WHERE habit_id = ? AND user_id = ? AND status = 'pending'
            ");
            $checkStmt->execute([$_POST['habit_id'], $userId]);
            $existingRequest = $checkStmt->fetch();
            
            if ($existingRequest) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'You already have a pending edit request for this habit.'
                ]);
                exit;
            }
            
            // Check if user has an approved request that hasn't been used yet
            $approvedStmt = $conn->prepare("
                SELECT id FROM habit_edit_requests 
                WHERE habit_id = ? AND user_id = ? AND status = 'approved'
                ORDER BY reviewed_at DESC LIMIT 1
            ");
            $approvedStmt->execute([$_POST['habit_id'], $userId]);
            $approvedRequest = $approvedStmt->fetch();
            
            if ($approvedRequest) {
                echo json_encode([
                    'success' => true,
                    'has_approval' => true,
                    'message' => 'You already have approval to edit this habit. You can edit it directly.'
                ]);
                exit;
            }
            
            // Create new edit request only if no reason provided means real request
            if (empty($_POST['reason'])) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Please provide a reason for your edit request.'
                ]);
                exit;
            }
            
            // Create new edit request
            $insertStmt = $conn->prepare("
                INSERT INTO habit_edit_requests (habit_id, user_id, reason) 
                VALUES (?, ?, ?)
            ");
            $result = $insertStmt->execute([
                $_POST['habit_id'],
                $userId,
                $_POST['reason']
            ]);
            
            if ($result) {
                // Notify admin (you can implement email notification here)
                echo json_encode([
                    'success' => true, 
                    'message' => 'Edit request sent to admin for approval. You will be notified once approved.'
                ]);
            } else {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Failed to submit edit request.'
                ]);
            }
            exit;
            
        case 'create':
            $result = $habitModel->create($userId, [
                'name' => $_POST['name'],
                'frequency' => $_POST['frequency'],
                'target_days' => $_POST['target_days'] ?? 30
            ]);
            
            // Send email notification on successful habit creation
            if ($result) {
                try {
                    $targetDays = $_POST['target_days'] ?? 30;
                    $startDate = date('Y-m-d');
                    $endDate = date('Y-m-d', strtotime("+{$targetDays} days"));
                    
                    $mailer = new Mailer();
                    $emailResult = $mailer->sendHabitCreatedEmail(
                        [
                            'name' => $currentUser['name'],
                            'email' => $currentUser['email']
                        ],
                        [
                            'name' => $_POST['name'],
                            'frequency' => $_POST['frequency'],
                            'target_days' => $targetDays,
                            'start_date' => date('F j, Y', strtotime($startDate)),
                            'end_date' => date('F j, Y', strtotime($endDate))
                        ]
                    );
                    
                    if (!$emailResult['success']) {
                        error_log("Failed to send habit creation email: " . $emailResult['message']);
                    }
                } catch (Exception $e) {
                    error_log("Habit creation email error: " . $e->getMessage());
                }
            }
            
            echo json_encode(['success' => $result]);
            exit;
            
        case 'update':
            // Check if user has approval to edit
            $db = new Database();
            $conn = $db->connect();
            
            $approvalStmt = $conn->prepare("
                SELECT id FROM habit_edit_requests 
                WHERE habit_id = ? AND user_id = ? AND status = 'approved'
                ORDER BY reviewed_at DESC LIMIT 1
            ");
            $approvalStmt->execute([$_POST['id'], $userId]);
            $hasApproval = $approvalStmt->fetch();
            
            if (!$hasApproval) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'You need admin approval to edit this habit. Please request edit permission first.'
                ]);
                exit;
            }
            
            // Proceed with update
            $result = $habitModel->update($_POST['id'], [
                'name' => $_POST['name'],
                'frequency' => $_POST['frequency'],
                'target_days' => $_POST['target_days'] ?? 30
            ]);
            
            if ($result) {
                // Mark the approval as used by deleting it
                $deleteApprovalStmt = $conn->prepare("
                    DELETE FROM habit_edit_requests 
                    WHERE habit_id = ? AND user_id = ? AND status = 'approved'
                ");
                $deleteApprovalStmt->execute([$_POST['id'], $userId]);
                
                echo json_encode(['success' => true, 'message' => 'Habit updated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update habit.']);
            }
            exit;
            
        case 'delete':
            $result = $habitModel->delete($_POST['id']);
            echo json_encode(['success' => $result]);
            exit;
            
        case 'submit_proof':
            // Handle file upload
            if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/uploads/habits/';
                $fileExt = strtolower(pathinfo($_FILES['proof_file']['name'], PATHINFO_EXTENSION));
                $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov', 'avi'];
                
                if (!in_array($fileExt, $allowedTypes)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid file type']);
                    exit;
                }
                
                // Check file size (max 50MB)
                if ($_FILES['proof_file']['size'] > 50 * 1024 * 1024) {
                    echo json_encode(['success' => false, 'message' => 'File too large (max 50MB)']);
                    exit;
                }
                
                $fileName = uniqid() . '_' . time() . '.' . $fileExt;
                $filePath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['proof_file']['tmp_name'], $filePath)) {
                    $proofType = in_array($fileExt, ['mp4', 'mov', 'avi']) ? 'video' : 'image';
                    
                    $result = $completionModel->submitProof([
                        'habit_id' => $_POST['habit_id'],
                        'user_id' => $userId,
                        'completion_date' => date('Y-m-d'),
                        'proof_type' => $proofType,
                        'proof_path' => 'uploads/habits/' . $fileName,
                        'description' => $_POST['description'] ?? null
                    ]);
                    
                    echo json_encode($result);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'No file uploaded']);
            }
            exit;
            
        case 'increment':
            // Deprecated - redirect to proof submission
            echo json_encode([
                'success' => false, 
                'message' => 'Please submit proof of completion',
                'require_proof' => true
            ]);
            exit;
            if (is_array($result)) {
                echo json_encode($result);
            } else {
                echo json_encode(['success' => $result]);
            }
            exit;
            
        case 'reset':
            $result = $habitModel->resetStreak($_POST['id']);
            echo json_encode(['success' => $result]);
            exit;
    }
}

// Get user habits and stats
$habits = $habitModel->getUserHabits($userId);
$stats = $habitModel->getHabitStats($userId);
$topHabits = $habitModel->getTopHabits($userId, 3);
$userCompletions = $completionModel->getUserCompletions($userId);
$completionStats = $completionModel->getCompletionStats($userId);

$pageTitle = 'Habit Tracker';
require_once __DIR__ . '/../src/views/partials/header.php';
?>

<style>
    /* Keyframe Animations */
    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes scaleIn {
        from {
            opacity: 0;
            transform: scale(0.9);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }
    
    @keyframes fadeInRight {
        from {
            opacity: 0;
            transform: translateX(-20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @keyframes pulse-ring {
        0% {
            box-shadow: 0 0 0 0 rgba(161, 209, 177, 0.7);
        }
        70% {
            box-shadow: 0 0 0 15px rgba(161, 209, 177, 0);
        }
        100% {
            box-shadow: 0 0 0 0 rgba(161, 209, 177, 0);
        }
    }
    
    @keyframes check-bounce {
        0%, 100% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.2);
        }
    }
    
    @keyframes flame {
        0%, 100% {
            transform: translateY(0) scale(1);
            filter: hue-rotate(0deg);
        }
        50% {
            transform: translateY(-5px) scale(1.1);
            filter: hue-rotate(15deg);
        }
    }
    
    @keyframes float {
        0%, 100% {
            transform: translateY(0);
        }
        50% {
            transform: translateY(-10px);
        }
    }
    
    @keyframes shimmer {
        0% {
            background-position: -1000px 0;
        }
        100% {
            background-position: 1000px 0;
        }
    }
    
    @keyframes rotate-gentle {
        0% {
            transform: rotate(0deg);
        }
        100% {
            transform: rotate(360deg);
        }
    }
    
    @keyframes progress-fill {
        from {
            width: 0;
        }
    }
    
    /* Utility Classes */
    .animate-slide-up {
        animation: slideInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    
    .animate-scale-in {
        animation: scaleIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    
    .animate-fade-right {
        animation: fadeInRight 0.5s ease-out forwards;
    }
    
    .hover-lift {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .hover-lift:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: 0 20px 40px rgba(28, 37, 41, 0.15);
    }
    
    .pulse-ring {
        animation: pulse-ring 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
    
    .check-bounce {
        animation: check-bounce 0.3s ease-in-out;
    }
    
    .flame-icon {
        animation: flame 1.5s ease-in-out infinite;
    }
    
    .float-gentle {
        animation: float 3s ease-in-out infinite;
    }
    
    .shimmer-bg {
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
        background-size: 1000px 100%;
        animation: shimmer 2s infinite;
    }
    
    .rotate-slow {
        animation: rotate-gentle 20s linear infinite;
    }
    
    /* Glassmorphism Cards */
    .glass-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px) saturate(180%);
        border: 1px solid rgba(255, 255, 255, 0.8);
        box-shadow: 0 8px 32px rgba(28, 37, 41, 0.08);
    }
    
    .glass-card:hover {
        background: rgba(255, 255, 255, 0.98);
        border-color: rgba(161, 209, 177, 0.3);
    }
    
    /* Gradient Text */
    .gradient-text-custom {
        background: linear-gradient(135deg, #1f2937 0%, #34d399 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    /* Progress Bar Animation */
    .progress-animated {
        animation: progress-fill 1s ease-out forwards;
    }
    
    /* Modal Animations */
    .modal-backdrop {
        backdrop-filter: blur(8px);
        background: rgba(28, 37, 41, 0.5);
    }
    
    .modal-content {
        animation: scaleIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }
    
    /* Custom Scrollbar */
    ::-webkit-scrollbar {
        width: 10px;
    }
    
    ::-webkit-scrollbar-track {
        background: rgba(161, 209, 177, 0.1);
        border-radius: 10px;
    }
    
    ::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, #34d399, #1f2937);
        border-radius: 10px;
    }
    
    ::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(135deg, #8cc4a1, #1f2937);
    }
    
    /* Stagger Animation Delays */
    .stagger-1 { animation-delay: 0.1s; }
    .stagger-2 { animation-delay: 0.2s; }
    .stagger-3 { animation-delay: 0.3s; }
    .stagger-4 { animation-delay: 0.4s; }
    .stagger-5 { animation-delay: 0.5s; }
    .stagger-6 { animation-delay: 0.6s; }
    
    /* Additional Styles */
    .habit-card.border-2 {
        position: relative;
    }
    
    .habit-card .absolute {
        position: absolute;
    }
</style>

<div class="min-h-screen" style="background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Page Header with Animation -->
        <div class="mb-8 animate-slide-up">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h1 class="text-4xl font-bold gradient-text-custom mb-2">
                        🎯 Habit Tracker
                    </h1>
                    <p class="text-gray-600 text-lg">Build better habits, one day at a time</p>
                </div>
                <button onclick="openAddHabitModal()" class="px-6 py-3 rounded-xl font-semibold text-white shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105 pulse-ring" style="background: linear-gradient(135deg, #1f2937 0%, #34d399 100%);">
                    <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Add New Habit
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <!-- Total Habits -->
            <div class="glass-card rounded-2xl p-6 hover-lift animate-scale-in stagger-1">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium mb-1">Total Habits</p>
                        <h3 class="text-3xl font-bold" style="color: #1f2937;"><?php echo $stats['total_habits'] ?? 0; ?></h3>
                    </div>
                    <div class="w-14 h-14 rounded-2xl flex items-center justify-center rotate-slow" style="background: linear-gradient(135deg, #34d399 0%, #8cc4a1 100%);">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Active Streaks -->
            <div class="glass-card rounded-2xl p-6 hover-lift animate-scale-in stagger-2">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium mb-1">Active Streaks</p>
                        <h3 class="text-3xl font-bold text-orange-500"><?php echo $stats['active_streaks'] ?? 0; ?></h3>
                    </div>
                    <div class="w-14 h-14 rounded-2xl flex items-center justify-center" style="background: linear-gradient(135deg, #FFA500 0%, #FF6B35 100%);">
                        <svg class="w-7 h-7 text-white flame-icon" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.214.33-.403.713-.57 1.116-.334.804-.614 1.768-.84 2.734a31.365 31.365 0 00-.613 3.58 2.64 2.64 0 01-.945-1.067c-.328-.68-.398-1.534-.398-2.654A1 1 0 005.05 6.05 6.981 6.981 0 003 11a7 7 0 1011.95-4.95c-.592-.591-.98-.985-1.348-1.467-.363-.476-.724-1.063-1.207-2.03zM12.12 15.12A3 3 0 017 13s.879.5 2.5.5c0-1 .5-4 1.25-4.5.5 1 .786 1.293 1.371 1.879A2.99 2.99 0 0113 13a2.99 2.99 0 01-.879 2.121z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Total Points -->
            <div class="glass-card rounded-2xl p-6 hover-lift animate-scale-in stagger-3">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium mb-1">Total Points</p>
                        <h3 class="text-3xl font-bold text-purple-600"><?php echo $stats['total_points'] ?? 0; ?></h3>
                    </div>
                    <div class="w-14 h-14 rounded-2xl flex items-center justify-center float-gentle" style="background: linear-gradient(135deg, #9D50BB 0%, #6E48AA 100%);">
                        <svg class="w-7 h-7 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Longest Streak -->
            <div class="glass-card rounded-2xl p-6 hover-lift animate-scale-in stagger-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium mb-1">Longest Streak</p>
                        <h3 class="text-3xl font-bold text-green-600"><?php echo $stats['longest_streak'] ?? 0; ?></h3>
                    </div>
                    <div class="w-14 h-14 rounded-2xl flex items-center justify-center" style="background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Habits List -->
            <div class="lg:col-span-2">
                <div class="glass-card rounded-2xl p-6 animate-slide-up stagger-5">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-bold" style="color: #1f2937;">
                            <svg class="w-6 h-6 inline-block mr-2 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            Your Habits
                        </h2>
                        <div class="flex gap-2">
                            <button onclick="filterHabits('all')" class="filter-btn px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300" data-filter="all">All</button>
                            <button onclick="filterHabits('daily')" class="filter-btn px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300" data-filter="daily">Daily</button>
                            <button onclick="filterHabits('weekly')" class="filter-btn px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300" data-filter="weekly">Weekly</button>
                            <button onclick="filterHabits('monthly')" class="filter-btn px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300" data-filter="monthly">Monthly</button>
                        </div>
                    </div>

                    <div id="habits-container" class="space-y-4">
                        <?php if (empty($habits)): ?>
                            <div class="text-center py-12 animate-fade-right">
                                <div class="float-gentle inline-block">
                                    <svg class="mx-auto h-20 w-20 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <h3 class="mt-4 text-xl font-medium text-gray-900">No habits yet</h3>
                                <p class="mt-2 text-gray-500">Start building better habits today!</p>
                                <button onclick="openAddHabitModal()" class="mt-6 px-6 py-3 rounded-xl font-semibold text-white shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105" style="background: linear-gradient(135deg, #1f2937 0%, #34d399 100%);">
                                    Create Your First Habit
                                </button>
                            </div>
                        <?php else: ?>
                            <?php foreach ($habits as $index => $habit): ?>
                                <div class="habit-card glass-card rounded-xl p-5 hover-lift animate-fade-right <?php echo $habit['is_overdue'] ? 'border-2 border-red-300' : ''; ?>" data-frequency="<?php echo $habit['frequency']; ?>" style="animation-delay: <?php echo ($index * 0.1); ?>s;">
                                    <!-- Status Badge -->
                                    <?php if ($habit['status'] === 'completed'): ?>
                                        <div class="absolute top-3 right-3">
                                            <span class="px-3 py-1 rounded-full text-xs font-bold text-white" style="background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);">
                                                ✓ Completed
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="flex items-start gap-4 flex-1">
                                            <!-- Upload Proof Button -->
                                            <button 
                                                onclick="openProofModal(<?php echo $habit['id']; ?>, '<?php echo htmlspecialchars($habit['name']); ?>')" 
                                                class="habit-check w-12 h-12 rounded-xl flex items-center justify-center transition-all duration-300 hover:scale-110 flex-shrink-0 <?php echo $habit['completed_today'] ? 'opacity-50 cursor-not-allowed' : ''; ?>" 
                                                style="background: linear-gradient(135deg, #34d399 0%, #8cc4a1 100%);"
                                                <?php echo $habit['completed_today'] || $habit['status'] === 'completed' ? 'disabled' : ''; ?>>
                                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                                </svg>
                                            </button>

                                            <!-- Habit Info -->
                                            <div class="flex-1">
                                                <div class="flex items-start justify-between mb-2">
                                                    <h3 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($habit['name']); ?></h3>
                                                </div>
                                                
                                                <div class="flex flex-wrap items-center gap-2 text-sm text-gray-600 mb-3">
                                                    <span class="px-3 py-1 rounded-full text-xs font-medium" style="background: rgba(161, 209, 177, 0.2); color: #1f2937;">
                                                        <?php echo ucfirst($habit['frequency']); ?>
                                                    </span>
                                                    
                                                    <?php if ($habit['completed_today']): ?>
                                                        <span class="px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                                            ✓ Done Today
                                                        </span>
                                                    <?php elseif ($habit['is_overdue']): ?>
                                                        <span class="px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700 animate-pulse">
                                                            ⚠ Overdue
                                                        </span>
                                                    <?php endif; ?>
                                                    
                                                    <span class="flex items-center">
                                                        <svg class="w-4 h-4 mr-1 text-orange-500 flame-icon" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.214.33-.403.713-.57 1.116-.334.804-.614 1.768-.84 2.734a31.365 31.365 0 00-.613 3.58 2.64 2.64 0 01-.945-1.067c-.328-.68-.398-1.534-.398-2.654A1 1 0 005.05 6.05 6.981 6.981 0 003 11a7 7 0 1011.95-4.95c-.592-.591-.98-.985-1.348-1.467-.363-.476-.724-1.063-1.207-2.03zM12.12 15.12A3 3 0 017 13s.879.5 2.5.5c0-1 .5-4 1.25-4.5.5 1 .786 1.293 1.371 1.879A2.99 2.99 0 0113 13a2.99 2.99 0 01-.879 2.121z" clip-rule="evenodd"/>
                                                        </svg>
                                                        <?php echo $habit['streak']; ?>/<?php echo $habit['target_days']; ?> days
                                                    </span>
                                                    
                                                    <span class="flex items-center">
                                                        <svg class="w-4 h-4 mr-1 text-purple-500" fill="currentColor" viewBox="0 0 20 20">
                                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                                        </svg>
                                                        <?php echo $habit['points']; ?> pts
                                                    </span>
                                                    
                                                    <?php if ($habit['missed_days'] > 0 && $habit['status'] === 'active'): ?>
                                                        <span class="flex items-center text-red-600">
                                                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                                            </svg>
                                                            <?php echo $habit['missed_days']; ?> missed
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Progress Bar -->
                                                <div class="mb-2">
                                                    <div class="flex justify-between text-xs text-gray-600 mb-1">
                                                        <span>Progress</span>
                                                        <span class="font-semibold"><?php echo $habit['completion_percentage']; ?>%</span>
                                                    </div>
                                                    <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                                                        <div class="h-2 rounded-full progress-animated transition-all duration-500" style="width: <?php echo $habit['completion_percentage']; ?>%; background: linear-gradient(90deg, <?php echo $habit['completion_percentage'] >= 100 ? '#56ab2f, #a8e063' : '#34d399, #1f2937'; ?>);"></div>
                                                    </div>
                                                </div>
                                                
                                                <div class="flex items-center gap-4 text-xs text-gray-500">
                                                    <span>📅 <?php echo $habit['days_remaining']; ?> days left</span>
                                                    <?php if ($habit['end_date']): ?>
                                                        <span>🎯 Ends: <?php echo date('M d, Y', strtotime($habit['end_date'])); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Actions -->
                                        <div class="flex flex-col items-center gap-2">
                                            <button onclick="editHabit(<?php echo $habit['id']; ?>, '<?php echo addslashes($habit['name']); ?>', '<?php echo $habit['frequency']; ?>', <?php echo $habit['target_days']; ?>)" class="p-2 rounded-lg hover:bg-gray-100 transition-all duration-300 group">
                                                <svg class="w-5 h-5 text-gray-500 group-hover:text-blue-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </button>
                                            <button onclick="deleteHabit(<?php echo $habit['id']; ?>)" class="p-2 rounded-lg hover:bg-red-50 transition-all duration-300 group">
                                                <svg class="w-5 h-5 text-gray-500 group-hover:text-red-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                
                <!-- Top Performers -->
                <div class="glass-card rounded-2xl p-6 hover-lift animate-slide-up stagger-6">
                    <h3 class="text-xl font-bold mb-4 flex items-center" style="color: #1f2937;">
                        <svg class="w-5 h-5 mr-2 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                        </svg>
                        Top Performers
                    </h3>
                    
                    <?php if (empty($topHabits)): ?>
                        <p class="text-gray-500 text-center py-4">No habits tracked yet</p>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($topHabits as $index => $habit): ?>
                                <div class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition-all duration-300 animate-fade-right" style="animation-delay: <?php echo (0.7 + $index * 0.1); ?>s;">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-white" style="background: <?php echo $index === 0 ? 'linear-gradient(135deg, #FFD700, #FFA500)' : ($index === 1 ? 'linear-gradient(135deg, #C0C0C0, #808080)' : 'linear-gradient(135deg, #CD7F32, #8B4513)'); ?>">
                                        <?php echo $index + 1; ?>
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-semibold text-gray-900 text-sm"><?php echo htmlspecialchars($habit['name']); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo $habit['streak']; ?> days • <?php echo $habit['points']; ?> pts</p>
                                    </div>
                                    <svg class="w-5 h-5 text-orange-500 flame-icon" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.214.33-.403.713-.57 1.116-.334.804-.614 1.768-.84 2.734a31.365 31.365 0 00-.613 3.58 2.64 2.64 0 01-.945-1.067c-.328-.68-.398-1.534-.398-2.654A1 1 0 005.05 6.05 6.981 6.981 0 003 11a7 7 0 1011.95-4.95c-.592-.591-.98-.985-1.348-1.467-.363-.476-.724-1.063-1.207-2.03zM12.12 15.12A3 3 0 017 13s.879.5 2.5.5c0-1 .5-4 1.25-4.5.5 1 .786 1.293 1.371 1.879A2.99 2.99 0 0113 13a2.99 2.99 0 01-.879 2.121z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Motivation Card -->
                <div class="glass-card rounded-2xl p-6 hover-lift animate-slide-up" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); animation-delay: 0.8s;">
                    <div class="text-white">
                        <div class="float-gentle inline-block mb-4">
                            <svg class="w-12 h-12" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold mb-2">Keep It Up!</h3>
                        <p class="text-white/90 text-sm leading-relaxed">
                            Every small step counts. You're building a better version of yourself, one habit at a time. Stay consistent! 💪
                        </p>
                    </div>
                </div>

                <!-- Frequency Distribution -->
                <div class="glass-card rounded-2xl p-6 hover-lift animate-slide-up" style="animation-delay: 0.9s;">
                    <h3 class="text-xl font-bold mb-4 flex items-center" style="color: #1f2937;">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        By Frequency
                    </h3>
                    
                    <?php
                    $frequencyCount = ['daily' => 0, 'weekly' => 0, 'monthly' => 0];
                    foreach ($habits as $habit) {
                        $frequencyCount[$habit['frequency']]++;
                    }
                    $total = array_sum($frequencyCount);
                    ?>
                    
                    <div class="space-y-4">
                        <?php foreach ($frequencyCount as $frequency => $count): ?>
                            <?php $percentage = $total > 0 ? ($count / $total) * 100 : 0; ?>
                            <div class="animate-fade-right" style="animation-delay: <?php echo (1.0 + array_search($frequency, array_keys($frequencyCount)) * 0.1); ?>s;">
                                <div class="flex justify-between text-sm mb-2">
                                    <span class="font-medium capitalize text-gray-700"><?php echo $frequency; ?></span>
                                    <span class="text-gray-600"><?php echo $count; ?> (<?php echo round($percentage); ?>%)</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2.5 overflow-hidden">
                                    <div class="h-2.5 rounded-full progress-animated" style="width: <?php echo $percentage; ?>%; background: linear-gradient(90deg, #34d399 0%, #1f2937 100%);"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- My Submissions Widget -->
                <div class="glass-card rounded-2xl p-6 shadow-lg animate-fade-right" style="animation-delay: 1.3s;">
                    <h3 class="text-lg font-bold mb-4 flex items-center" style="color: #1f2937;">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        My Submissions
                    </h3>
                    
                    <div class="grid grid-cols-3 gap-2 mb-4">
                        <div class="text-center p-3 bg-yellow-50 rounded-lg">
                            <div class="text-2xl font-bold text-yellow-600"><?php echo $completionStats['pending'] ?? 0; ?></div>
                            <div class="text-xs text-yellow-700">Pending</div>
                        </div>
                        <div class="text-center p-3 bg-green-50 rounded-lg">
                            <div class="text-2xl font-bold text-green-600"><?php echo $completionStats['approved'] ?? 0; ?></div>
                            <div class="text-xs text-green-700">Approved</div>
                        </div>
                        <div class="text-center p-3 bg-red-50 rounded-lg">
                            <div class="text-2xl font-bold text-red-600"><?php echo $completionStats['rejected'] ?? 0; ?></div>
                            <div class="text-xs text-red-700">Rejected</div>
                        </div>
                    </div>
                    
                    <div class="space-y-2 max-h-64 overflow-y-auto">
                        <?php 
                        $recentSubmissions = array_slice($userCompletions, 0, 5);
                        foreach ($recentSubmissions as $submission): 
                        ?>
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="font-semibold text-sm text-gray-900"><?php echo htmlspecialchars($submission['habit_name']); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo date('M d, Y', strtotime($submission['completion_date'])); ?></div>
                                    </div>
                                    <span class="px-2 py-1 rounded text-xs font-semibold <?php
                                        echo $submission['verification_status'] === 'approved' ? 'bg-green-100 text-green-700' :
                                            ($submission['verification_status'] === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700');
                                    ?>">
                                        <?php echo ucfirst($submission['verification_status']); ?>
                                    </span>
                                </div>
                                <?php if ($submission['verification_status'] === 'approved'): ?>
                                    <div class="text-xs text-green-600 mt-1">
                                        +<?php echo $submission['points_awarded']; ?> points earned
                                    </div>
                                <?php elseif ($submission['verification_notes']): ?>
                                    <div class="text-xs text-gray-600 mt-1">
                                        <?php echo htmlspecialchars($submission['verification_notes']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($recentSubmissions)): ?>
                            <div class="text-center py-4 text-gray-500 text-sm">
                                No submissions yet
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Habit Modal -->
<div id="habitModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 modal-backdrop" onclick="closeModalOnBackdrop(event)">
    <div class="modal-content glass-card rounded-2xl p-8 max-w-md w-full shadow-2xl" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between mb-6">
            <h3 id="modalTitle" class="text-2xl font-bold" style="color: #1f2937;">Add New Habit</h3>
            <button onclick="closeHabitModal()" class="p-2 hover:bg-gray-100 rounded-lg transition-all duration-300">
                <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <form id="habitForm" onsubmit="saveHabit(event)" class="space-y-5">
            <input type="hidden" id="habitId" value="">
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Habit Name</label>
                <input type="text" id="habitName" required class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:border-green-500 focus:ring-2 focus:ring-green-200 transition-all duration-300" placeholder="e.g., Morning Exercise, Read 30 minutes">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Frequency</label>
                <select id="habitFrequency" required class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:border-green-500 focus:ring-2 focus:ring-green-200 transition-all duration-300">
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Target Duration (Days)</label>
                <input type="number" id="habitTargetDays" required min="1" max="365" value="30" class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:border-green-500 focus:ring-2 focus:ring-green-200 transition-all duration-300" placeholder="e.g., 30, 60, 90">
                <p class="mt-1 text-xs text-gray-500">How many days do you want to maintain this habit?</p>
            </div>

            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeHabitModal()" class="flex-1 px-6 py-3 rounded-xl font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 transition-all duration-300">
                    Cancel
                </button>
                <button type="submit" class="flex-1 px-6 py-3 rounded-xl font-semibold text-white shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105" style="background: linear-gradient(135deg, #1f2937 0%, #34d399 100%);">
                    Save Habit
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Request Modal -->
<div id="editRequestModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 modal-backdrop" onclick="closeEditRequestModal(event)">
    <div class="modal-content glass-card rounded-2xl p-8 max-w-md w-full shadow-2xl" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-2xl font-bold" style="color: #1f2937;">Request Edit Permission</h3>
            <button onclick="closeEditRequestModal()" class="p-2 hover:bg-gray-100 rounded-lg transition-all duration-300">
                <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <form id="editRequestForm" onsubmit="submitEditRequest(event)" class="space-y-5">
            <input type="hidden" id="requestHabitId" value="">
            <input type="hidden" id="requestHabitName" value="">
            <input type="hidden" id="requestHabitFrequency" value="">
            <input type="hidden" id="requestHabitTargetDays" value="">
            
            <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-yellow-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <div class="text-sm text-yellow-700">
                        <p class="font-semibold mb-1">Admin Approval Required</p>
                        <p class="text-xs">You need permission from an admin to edit this habit. Please provide a reason for your request.</p>
                    </div>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Habit: <span id="requestHabitNameDisplay" class="font-normal text-gray-600"></span>
                </label>
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Reason for Edit</label>
                <textarea 
                    id="editReason" 
                    required
                    rows="4" 
                    class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 transition-all duration-300" 
                    placeholder="E.g., I want to change the frequency from daily to weekly because..."></textarea>
            </div>

            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeEditRequestModal()" class="flex-1 px-6 py-3 rounded-xl font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 transition-all duration-300">
                    Cancel
                </button>
                <button type="submit" id="submitRequestBtn" class="flex-1 px-6 py-3 rounded-xl font-semibold text-white shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105" style="background: linear-gradient(135deg, #FFA500 0%, #FF6B35 100%);">
                    Send Request
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Proof Upload Modal -->
<div id="proofModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 modal-backdrop" onclick="closeModalOnBackdrop(event, 'proofModal')">
    <div class="modal-content glass-card rounded-2xl max-w-md w-full shadow-2xl max-h-[90vh] flex flex-col" onclick="event.stopPropagation()">
        <!-- Fixed Header -->
        <div class="flex items-center justify-between p-6 border-b border-gray-200">
            <div>
                <h3 class="text-2xl font-bold" style="color: #1f2937;">Submit Proof</h3>
                <p id="proofHabitName" class="text-sm text-gray-600 mt-1"></p>
            </div>
            <button onclick="closeProofModal()" class="p-2 hover:bg-gray-100 rounded-lg transition-all duration-300">
                <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Scrollable Content -->
        <div class="overflow-y-auto flex-1 custom-scrollbar">
            <form id="proofForm" onsubmit="submitProof(event)" class="p-6 space-y-5">
            <input type="hidden" id="proofHabitId" value="">
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Upload Photo or Video
                    <span class="text-xs font-normal text-gray-500">(Max 50MB)</span>
                </label>
                <div class="relative">
                    <input 
                        type="file" 
                        id="proofFile" 
                        accept="image/*,video/*" 
                        required 
                        class="hidden"
                        onchange="handleFileSelect(event)">
                    <label 
                        for="proofFile" 
                        class="flex flex-col items-center justify-center w-full h-40 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer hover:border-green-500 transition-all duration-300"
                        id="fileDropZone">
                        <svg class="w-12 h-12 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        <span class="text-sm text-gray-500" id="fileLabel">Click to upload or drag and drop</span>
                        <span class="text-xs text-gray-400 mt-1">JPG, PNG, GIF, MP4, MOV, AVI</span>
                    </label>
                    <div id="filePreview" class="hidden mt-4">
                        <img id="imagePreview" class="hidden w-full h-48 object-cover rounded-xl">
                        <video id="videoPreview" class="hidden w-full h-48 rounded-xl" controls></video>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Description (Optional)
                </label>
                <textarea 
                    id="proofDescription" 
                    rows="3" 
                    class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:border-green-500 focus:ring-2 focus:ring-green-200 transition-all duration-300" 
                    placeholder="Add notes about your activity..."></textarea>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    <div class="text-sm text-blue-700">
                        <p class="font-semibold mb-1">Verification Required</p>
                        <p class="text-xs">Your proof will be reviewed by a doctor or instructor. Points will be awarded after approval.</p>
                    </div>
                </div>
            </div>

            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeProofModal()" class="flex-1 px-6 py-3 rounded-xl font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 transition-all duration-300">
                    Cancel
                </button>
                <button type="submit" id="submitProofBtn" class="flex-1 px-6 py-3 rounded-xl font-semibold text-white shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105" style="background: linear-gradient(135deg, #1f2937 0%, #34d399 100%);">
                    Submit for Review
                </button>
            </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Custom Scrollbar Styles for Proof Modal */
.custom-scrollbar::-webkit-scrollbar {
    width: 8px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 10px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #1f2937 0%, #34d399 100%);
    border-radius: 10px;
    transition: background 0.3s ease;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, #0f1215 0%, #8cc4a1 100%);
}

/* Firefox Scrollbar */
.custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: #34d399 #f1f5f9;
}

/* Smooth scroll behavior */
.custom-scrollbar {
    scroll-behavior: smooth;
}
</style>

<script>
    let currentFilter = 'all';
    
    // Filter Buttons Styling
    function updateFilterButtons() {
        document.querySelectorAll('.filter-btn').forEach(btn => {
            const filter = btn.getAttribute('data-filter');
            if (filter === currentFilter) {
                btn.style.background = 'linear-gradient(135deg, #1f2937 0%, #34d399 100%)';
                btn.style.color = 'white';
            } else {
                btn.style.background = 'rgba(161, 209, 177, 0.1)';
                btn.style.color = '#1f2937';
            }
        });
    }
    
    // Initialize filter buttons
    updateFilterButtons();
    
    // Filter habits
    function filterHabits(frequency) {
        currentFilter = frequency;
        updateFilterButtons();
        
        const cards = document.querySelectorAll('.habit-card');
        cards.forEach((card, index) => {
            const cardFrequency = card.getAttribute('data-frequency');
            
            if (frequency === 'all' || cardFrequency === frequency) {
                card.style.display = 'block';
                card.style.animation = `fadeInRight 0.5s ease-out ${index * 0.1}s forwards`;
            } else {
                card.style.display = 'none';
            }
        });
    }
    
    // Modal Functions
    function openAddHabitModal() {
        document.getElementById('modalTitle').textContent = 'Add New Habit';
        document.getElementById('habitId').value = '';
        document.getElementById('habitName').value = '';
        document.getElementById('habitFrequency').value = 'daily';
        document.getElementById('habitTargetDays').value = '30';
        document.getElementById('habitModal').classList.remove('hidden');
        document.getElementById('habitModal').classList.add('flex');
    }
    
    function closeHabitModal() {
        document.getElementById('habitModal').classList.add('hidden');
        document.getElementById('habitModal').classList.remove('flex');
    }
    
    function closeModalOnBackdrop(event, modalId = 'habitModal') {
        if (event.target.id === modalId) {
            if (modalId === 'habitModal') {
                closeHabitModal();
            } else if (modalId === 'proofModal') {
                closeProofModal();
            }
        }
    }
    
    function editHabit(id, name, frequency, targetDays) {
        // Store habit details for potential request
        const habitData = {
            id: id,
            name: name,
            frequency: frequency,
            targetDays: targetDays || 30
        };
        
        // Check if user has approval to edit
        checkEditPermission(habitData);
    }
    
    async function checkEditPermission(habitData) {
        const formData = new FormData();
        formData.append('action', 'check_edit_permission');
        formData.append('habit_id', habitData.id);
        
        try {
            const response = await fetch('habits.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.has_approval) {
                // User has approval, open edit modal
                openEditModal(habitData);
            } else {
                // User needs to request permission
                openEditRequestModal(habitData);
            }
        } catch (error) {
            console.error('Error checking permission:', error);
            // On error, show request modal
            openEditRequestModal(habitData);
        }
    }
    
    function openEditModal(habitData) {
        document.getElementById('modalTitle').textContent = 'Edit Habit';
        document.getElementById('habitId').value = habitData.id;
        document.getElementById('habitName').value = habitData.name;
        document.getElementById('habitFrequency').value = habitData.frequency;
        document.getElementById('habitTargetDays').value = habitData.targetDays;
        document.getElementById('habitModal').classList.remove('hidden');
        document.getElementById('habitModal').classList.add('flex');
    }
    
    function openEditRequestModal(habitData) {
        document.getElementById('requestHabitId').value = habitData.id;
        document.getElementById('requestHabitName').value = habitData.name;
        document.getElementById('requestHabitFrequency').value = habitData.frequency;
        document.getElementById('requestHabitTargetDays').value = habitData.targetDays;
        document.getElementById('requestHabitNameDisplay').textContent = habitData.name;
        document.getElementById('editReason').value = '';
        document.getElementById('editRequestModal').classList.remove('hidden');
        document.getElementById('editRequestModal').classList.add('flex');
    }
    
    function closeEditRequestModal(event) {
        if (event && event.target.id !== 'editRequestModal') return;
        document.getElementById('editRequestModal').classList.add('hidden');
        document.getElementById('editRequestModal').classList.remove('flex');
    }
    
    async function submitEditRequest(event) {
        event.preventDefault();
        
        const submitBtn = document.getElementById('submitRequestBtn');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending...';
        
        const formData = new FormData();
        formData.append('action', 'request_edit');
        formData.append('habit_id', document.getElementById('requestHabitId').value);
        formData.append('reason', document.getElementById('editReason').value);
        
        try {
            const response = await fetch('habits.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                if (result.has_approval) {
                    // User already has approval, open edit modal
                    closeEditRequestModal();
                    const habitData = {
                        id: document.getElementById('requestHabitId').value,
                        name: document.getElementById('requestHabitName').value,
                        frequency: document.getElementById('requestHabitFrequency').value,
                        targetDays: document.getElementById('requestHabitTargetDays').value
                    };
                    openEditModal(habitData);
                } else {
                    // Request submitted successfully
                    showNotification('✅ ' + result.message, 'success');
                    closeEditRequestModal();
                }
            } else {
                showNotification('❌ ' + result.message, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('❌ Error submitting request. Please try again.', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    }
    
    // Save Habit (Add or Update)
    async function saveHabit(event) {
        event.preventDefault();
        
        const id = document.getElementById('habitId').value;
        const name = document.getElementById('habitName').value;
        const frequency = document.getElementById('habitFrequency').value;
        const targetDays = document.getElementById('habitTargetDays').value;
        
        const formData = new FormData();
        formData.append('action', id ? 'update' : 'create');
        if (id) formData.append('id', id);
        formData.append('name', name);
        formData.append('frequency', frequency);
        formData.append('target_days', targetDays);
        
        try {
            const response = await fetch('habits.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification(result.message || (id ? '✅ Habit updated successfully!' : '✅ Habit created successfully!'), 'success');
                closeHabitModal();
                setTimeout(() => location.reload(), 1500);
            } else {
                showNotification('❌ ' + (result.message || 'Error saving habit. Please try again.'), 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('❌ Error saving habit. Please try again.', 'error');
        }
    }
    
    // Proof Modal Functions
    function openProofModal(habitId, habitName) {
        document.getElementById('proofHabitId').value = habitId;
        document.getElementById('proofHabitName').textContent = habitName;
        document.getElementById('proofFile').value = '';
        document.getElementById('proofDescription').value = '';
        document.getElementById('filePreview').classList.add('hidden');
        document.getElementById('imagePreview').classList.add('hidden');
        document.getElementById('videoPreview').classList.add('hidden');
        document.getElementById('fileLabel').textContent = 'Click to upload or drag and drop';
        document.getElementById('proofModal').classList.remove('hidden');
        document.getElementById('proofModal').classList.add('flex');
    }
    
    function closeProofModal() {
        document.getElementById('proofModal').classList.add('hidden');
        document.getElementById('proofModal').classList.remove('flex');
    }
    
    function handleFileSelect(event) {
        const file = event.target.files[0];
        if (!file) return;
        
        const fileLabel = document.getElementById('fileLabel');
        const filePreview = document.getElementById('filePreview');
        const imagePreview = document.getElementById('imagePreview');
        const videoPreview = document.getElementById('videoPreview');
        
        fileLabel.textContent = file.name;
        filePreview.classList.remove('hidden');
        
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                imagePreview.src = e.target.result;
                imagePreview.classList.remove('hidden');
                videoPreview.classList.add('hidden');
            };
            reader.readAsDataURL(file);
        } else if (file.type.startsWith('video/')) {
            const url = URL.createObjectURL(file);
            videoPreview.src = url;
            videoPreview.classList.remove('hidden');
            imagePreview.classList.add('hidden');
        }
    }
    
    // Submit Proof
    async function submitProof(event) {
        event.preventDefault();
        
        const submitBtn = document.getElementById('submitProofBtn');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Uploading...';
        
        const formData = new FormData();
        formData.append('action', 'submit_proof');
        formData.append('habit_id', document.getElementById('proofHabitId').value);
        formData.append('proof_file', document.getElementById('proofFile').files[0]);
        formData.append('description', document.getElementById('proofDescription').value);
        
        try {
            const response = await fetch('habits.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert('✅ ' + result.message);
                closeProofModal();
                location.reload();
            } else {
                alert('❌ ' + result.message);
            }
        } catch (error) {
            alert('Error submitting proof. Please try again.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit for Review';
        }
    }
    
    // Check Habit (Deprecated - opens proof modal instead)
    async function checkHabit(id) {
        // This function is no longer used - proof submission is required
        alert('Please submit proof of completion to track your habit progress.');
    }
    
    // Show notification
    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 px-6 py-4 rounded-xl shadow-2xl z-50 animate-slide-up ${type === 'success' ? 'bg-green-500' : 'bg-red-500'} text-white font-semibold`;
        notification.textContent = message;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateY(-20px)';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
    
    // Delete Habit
    async function deleteHabit(id) {
        if (!confirm('Are you sure you want to delete this habit?')) return;
        
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);
        
        try {
            const response = await fetch('habits.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                location.reload();
            } else {
                alert('Error deleting habit. Please try again.');
            }
        } catch (error) {
            alert('Error deleting habit. Please try again.');
        }
    }
    
    // Close modal on ESC key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeHabitModal();
        }
    });
    
    // Add page load animation
    document.addEventListener('DOMContentLoaded', function() {
        document.body.style.opacity = '0';
        setTimeout(() => {
            document.body.style.transition = 'opacity 0.5s ease-in';
            document.body.style.opacity = '1';
        }, 100);
    });
</script>

<?php require_once __DIR__ . '/../src/views/partials/footer.php'; ?>
