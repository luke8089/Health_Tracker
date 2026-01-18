<?php
/**
 * Monitor Created Habits Page
 * For Doctors to track habits they created for users
 */

require_once __DIR__ . '/../src/helpers/Auth.php';
require_once __DIR__ . '/../src/helpers/Database.php';
require_once __DIR__ . '/../src/helpers/Utils.php';

// Check authentication - only doctors
$auth = new Auth();
$auth->requireLogin();
$auth->requireRole('doctor');

$currentUser = $auth->getCurrentUser();
$db = new Database();
$conn = $db->connect();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'get_habit_details') {
        try {
            $habitId = intval($_POST['habit_id']);
            
            // Get habit completions
            $stmt = $conn->prepare("
                SELECT 
                    hc.*,
                    u.name as verified_by_name
                FROM habit_completions hc
                LEFT JOIN users u ON hc.verified_by = u.id
                WHERE hc.habit_id = ?
                ORDER BY hc.completion_date DESC
            ");
            $stmt->execute([$habitId]);
            $completions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'completions' => $completions]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'delete_habit') {
        try {
            $habitId = intval($_POST['habit_id']);
            
            // Delete habit
            $stmt = $conn->prepare("DELETE FROM habits WHERE id = ?");
            $result = $stmt->execute([$habitId]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Habit deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete habit']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'get_users') {
        try {
            // Get only connected users (not doctors)
            $stmt = $conn->prepare("
                SELECT DISTINCT u.id, u.name, u.email, u.avatar 
                FROM users u
                INNER JOIN user_doctor_connections udc ON u.id = udc.user_id
                WHERE u.role = 'user' 
                AND udc.doctor_id = ?
                AND udc.status = 'active'
                ORDER BY u.name ASC
            ");
            $stmt->execute([$currentUser['id']]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'users' => $users]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'create_habit') {
        try {
            $userId = intval($_POST['user_id']);
            $name = trim($_POST['name']);
            $frequency = $_POST['frequency'];
            $targetDays = intval($_POST['target_days']);
            $startDate = $_POST['start_date'];
            $endDate = $_POST['end_date'];
            
            // Validate inputs
            if (empty($name)) {
                echo json_encode(['success' => false, 'message' => 'Habit name is required']);
                exit;
            }
            
            if (!in_array($frequency, ['daily', 'weekly', 'monthly'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid frequency']);
                exit;
            }
            
            if ($targetDays < 1) {
                echo json_encode(['success' => false, 'message' => 'Target days must be at least 1']);
                exit;
            }
            
            // Validate dates
            if (strtotime($endDate) < strtotime($startDate)) {
                echo json_encode(['success' => false, 'message' => 'End date must be after start date']);
                exit;
            }
            
            // Insert habit
            $stmt = $conn->prepare("
                INSERT INTO habits 
                (user_id, name, frequency, target_days, start_date, end_date, status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
            ");
            
            $result = $stmt->execute([
                $userId,
                $name,
                $frequency,
                $targetDays,
                $startDate,
                $endDate
            ]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Habit created successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create habit']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'add_user_to_habit') {
        try {
            $habitId = intval($_POST['habit_id']);
            $newUserId = intval($_POST['user_id']);
            
            // Get the original habit details
            $stmt = $conn->prepare("SELECT * FROM habits WHERE id = ?");
            $stmt->execute([$habitId]);
            $originalHabit = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$originalHabit) {
                echo json_encode(['success' => false, 'message' => 'Habit not found']);
                exit;
            }
            
            // Check if user already has this habit
            $checkStmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM habits 
                WHERE user_id = ? 
                AND name = ? 
                AND start_date = ? 
                AND end_date = ?
            ");
            $checkStmt->execute([
                $newUserId,
                $originalHabit['name'],
                $originalHabit['start_date'],
                $originalHabit['end_date']
            ]);
            $exists = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($exists['count'] > 0) {
                echo json_encode(['success' => false, 'message' => 'User already has this habit assigned']);
                exit;
            }
            
            // Create a new habit entry for the new user with same settings
            $insertStmt = $conn->prepare("
                INSERT INTO habits 
                (user_id, name, frequency, target_days, start_date, end_date, status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
            ");
            
            $result = $insertStmt->execute([
                $newUserId,
                $originalHabit['name'],
                $originalHabit['frequency'],
                $originalHabit['target_days'],
                $originalHabit['start_date'],
                $originalHabit['end_date']
            ]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'User added to habit successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add user to habit']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'remove_user_from_habit') {
        try {
            $habitId = intval($_POST['habit_id']);
            
            // Delete the habit (removes user from habit)
            $stmt = $conn->prepare("DELETE FROM habits WHERE id = ?");
            $result = $stmt->execute([$habitId]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'User removed from habit successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to remove user from habit']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'get_habit_info') {
        try {
            $habitId = intval($_POST['habit_id']);
            
            $stmt = $conn->prepare("SELECT * FROM habits WHERE id = ?");
            $stmt->execute([$habitId]);
            $habit = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($habit) {
                echo json_encode(['success' => true, 'habit' => $habit]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Habit not found']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'update_habit') {
        try {
            $habitId = intval($_POST['habit_id']);
            $name = trim($_POST['name']);
            $frequency = $_POST['frequency'];
            $targetDays = intval($_POST['target_days']);
            $startDate = $_POST['start_date'];
            $endDate = $_POST['end_date'];
            $status = $_POST['status'];
            
            // Validate inputs
            if (empty($name)) {
                echo json_encode(['success' => false, 'message' => 'Habit name is required']);
                exit;
            }
            
            if (!in_array($frequency, ['daily', 'weekly', 'monthly'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid frequency']);
                exit;
            }
            
            if (!in_array($status, ['active', 'completed', 'abandoned'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid status']);
                exit;
            }
            
            // Update habit
            $stmt = $conn->prepare("
                UPDATE habits 
                SET name = ?, 
                    frequency = ?, 
                    target_days = ?, 
                    start_date = ?, 
                    end_date = ?, 
                    status = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $result = $stmt->execute([
                $name,
                $frequency,
                $targetDays,
                $startDate,
                $endDate,
                $status,
                $habitId
            ]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Habit updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update habit']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// Get all users who have habits assigned AND are connected to this doctor
$usersStmt = $conn->prepare("
    SELECT DISTINCT
        u.id,
        u.name,
        u.email,
        u.avatar,
        COUNT(DISTINCT h.id) as total_habits,
        SUM(CASE WHEN h.status = 'active' THEN 1 ELSE 0 END) as active_habits,
        SUM(CASE WHEN h.status = 'completed' THEN 1 ELSE 0 END) as completed_habits
    FROM users u
    INNER JOIN user_doctor_connections udc ON u.id = udc.user_id
    LEFT JOIN habits h ON u.id = h.user_id
    WHERE u.role = 'user' 
    AND udc.doctor_id = ?
    AND udc.status = 'active'
    GROUP BY u.id, u.name, u.email, u.avatar
    HAVING total_habits > 0
    ORDER BY u.name ASC
");
$usersStmt->execute([$currentUser['id']]);
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// Get all habits grouped by user (only for connected users) with completion stats
$habitsStmt = $conn->prepare("
    SELECT 
        h.*,
        u.name as user_name,
        u.email as user_email,
        u.avatar as user_avatar,
        (SELECT COUNT(*) FROM habit_completions WHERE habit_id = h.id AND verification_status = 'approved') as completed_count,
        (SELECT COUNT(*) FROM habit_completions WHERE habit_id = h.id AND verification_status = 'pending') as pending_count,
        (SELECT COUNT(*) FROM habit_completions WHERE habit_id = h.id AND verification_status = 'rejected') as rejected_count,
        DATEDIFF(CURDATE(), h.start_date) as days_elapsed,
        DATEDIFF(h.end_date, CURDATE()) as days_remaining
    FROM habits h
    JOIN users u ON h.user_id = u.id
    INNER JOIN user_doctor_connections udc ON u.id = udc.user_id
    WHERE udc.doctor_id = ?
    AND udc.status = 'active'
    ORDER BY u.name ASC, h.created_at DESC
");
$habitsStmt->execute([$currentUser['id']]);
$allHabits = $habitsStmt->fetchAll(PDO::FETCH_ASSOC);

// Group habits by user
$habitsByUser = [];
foreach ($allHabits as $habit) {
    $userId = $habit['user_id'];
    if (!isset($habitsByUser[$userId])) {
        $habitsByUser[$userId] = [];
    }
    $habitsByUser[$userId][] = $habit;
}

$title = "Monitor Created Habits - Health Tracker";
require_once __DIR__ . '/includes/header.php';
?>

<style>
    .glass-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px) saturate(180%);
        border: 1px solid rgba(255, 255, 255, 0.3);
    }
    
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
    
    .animate-slide-up {
        animation: slideInUp 0.5s ease-out;
    }
    
    .habit-card {
        transition: all 0.3s ease;
    }
    
    .habit-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }
    
    .progress-ring {
        transition: stroke-dashoffset 0.5s ease;
    }
    
    .user-filter-chip {
        cursor: pointer;
        transition: all 0.3s ease;
        background: white;
        color: #374151;
        border-color: #d1d5db;
    }
    
    .user-filter-chip:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    
    .user-filter-chip.active {
        background: linear-gradient(90deg, #1C2529 0%, #A1D1B1 100%);
        color: white;
        border-color: #1C2529;
        box-shadow: 0 4px 6px -1px rgba(28, 37, 41, 0.3);
    }
    
    #searchResults {
        scrollbar-width: thin;
        scrollbar-color: #A1D1B1 #f3f4f6;
    }
    
    #searchResults::-webkit-scrollbar {
        width: 8px;
    }
    
    #searchResults::-webkit-scrollbar-track {
        background: #f3f4f6;
        border-radius: 10px;
    }
    
    #searchResults::-webkit-scrollbar-thumb {
        background: #A1D1B1;
        border-radius: 10px;
    }
    
    #searchResults::-webkit-scrollbar-thumb:hover {
        background: #1C2529;
    }
    
    mark {
        background-color: #fef08a;
        padding: 2px 4px;
        border-radius: 3px;
        font-weight: 600;
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    #searchResults {
        animation: fadeIn 0.2s ease-out;
    }
</style>

<div class="container mx-auto px-4 py-8 max-w-7xl">
    <!-- Header -->
    <div class="glass-card rounded-2xl p-8 mb-8 animate-slide-up">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h1 class="text-4xl font-bold text-gray-900 mb-2">Monitor User Habits</h1>
                <p class="text-gray-600">Filter by user to view and manage their habits</p>
            </div>
            <div class="flex items-center gap-4">
                <a href="verify_habits.php" 
                   class="px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl font-semibold hover:shadow-lg transition-all duration-300 transform hover:scale-105">
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                    Verify Completions
                </a>
                <div class="bg-gradient-to-r from-primary to-accent text-white px-6 py-3 rounded-xl text-center">
                    <div class="text-3xl font-bold"><?php echo count($users); ?></div>
                    <div class="text-sm">Total Users</div>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($users)): ?>
        <!-- No Users -->
        <div class="glass-card rounded-2xl p-12 text-center animate-slide-up">
            <svg class="w-24 h-24 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            <h3 class="text-2xl font-bold text-gray-900 mb-2">No Users with Habits</h3>
            <p class="text-gray-600 mb-6">No habits have been created yet. Start by creating habits for your users.</p>
            <a href="verify_habits.php" 
               class="inline-block px-6 py-3 bg-gradient-to-r from-primary to-accent text-white rounded-xl font-semibold hover:shadow-lg transition-all">
                Create First Habit
            </a>
        </div>
    <?php else: ?>
        <!-- User Filter Section -->
        <div class="mb-6">
            <div class="glass-card rounded-2xl p-6">
                <div class="flex items-center justify-between mb-4 flex-wrap gap-4">
                    <div class="flex items-center gap-4">
                        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                        </svg>
                        <h2 class="text-xl font-bold text-gray-900">Filter by User</h2>
                        <span id="userCountBadge" class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm font-semibold">
                            <?php echo count($users); ?> users
                        </span>
                    </div>
                    
                    <!-- View Toggle -->
                    <div class="flex gap-2">
                        <button onclick="toggleView('all')" 
                                id="viewAllBtn"
                                class="px-4 py-2 bg-gradient-to-r from-primary to-accent text-white rounded-xl font-semibold transition-all">
                            View All
                        </button>
                        <button onclick="toggleView('search')" 
                                id="viewSearchBtn"
                                class="px-4 py-2 bg-gray-200 text-gray-700 rounded-xl font-semibold transition-all">
                            Search Mode
                        </button>
                    </div>
                </div>
                
                <!-- Advanced Search Section -->
                <div id="searchSection" class="hidden">
                    <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-4 mb-4">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-blue-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <div class="flex-1">
                                <h3 class="font-semibold text-blue-900 mb-1">Search Mode Activated</h3>
                                <p class="text-sm text-blue-700">Type to search through <?php echo count($users); ?> users. Click a result to view their habits.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="relative mb-4">
                        <input type="text" 
                               id="userFilterSearch" 
                               placeholder="🔍 Search by name, email, or ID..." 
                               class="w-full px-4 py-4 pl-12 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-accent transition-all text-lg"
                               onkeyup="performSearch()"
                               autocomplete="off">
                        <svg class="w-6 h-6 text-gray-400 absolute left-4 top-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <button onclick="clearSearch()" 
                                id="clearSearchBtn"
                                class="hidden absolute right-4 top-4 text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    
                    <!-- Search Results Dropdown -->
                    <div id="searchResults" class="hidden absolute z-50 w-full max-w-4xl bg-white rounded-xl shadow-2xl border-2 border-gray-200 max-h-96 overflow-y-auto mt-2">
                        <div id="searchResultsList" class="divide-y divide-gray-100">
                            <!-- Results will be inserted here -->
                        </div>
                        <div id="noResults" class="hidden p-8 text-center text-gray-500">
                            <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-lg font-semibold">No users found</p>
                            <p class="text-sm">Try a different search term</p>
                        </div>
                    </div>
                    
                    <!-- Quick Stats -->
                    <div id="searchStats" class="hidden grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
                        <div class="bg-green-50 rounded-xl p-4 text-center">
                            <div class="text-2xl font-bold text-green-700" id="statsActiveUsers">0</div>
                            <div class="text-xs text-green-600">Users with Active Habits</div>
                        </div>
                        <div class="bg-blue-50 rounded-xl p-4 text-center">
                            <div class="text-2xl font-bold text-blue-700" id="statsCompletedUsers">0</div>
                            <div class="text-xs text-blue-600">Users with Completed Habits</div>
                        </div>
                        <div class="bg-purple-50 rounded-xl p-4 text-center">
                            <div class="text-2xl font-bold text-purple-700" id="statsTotalHabits">0</div>
                            <div class="text-xs text-purple-600">Total Habits</div>
                        </div>
                        <div class="bg-orange-50 rounded-xl p-4 text-center">
                            <div class="text-2xl font-bold text-orange-700" id="statsAvgHabits">0</div>
                            <div class="text-xs text-orange-600">Avg Habits per User</div>
                        </div>
                    </div>
                </div>
                
                <!-- Selected User Info (shown after search) -->
                <div id="selectedUserInfo" class="hidden bg-gradient-to-r from-primary to-accent text-white rounded-xl p-4 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div id="selectedUserAvatar" class="w-12 h-12 rounded-full bg-white bg-opacity-20 flex items-center justify-center font-bold"></div>
                        <div>
                            <h3 id="selectedUserName" class="font-bold text-lg"></h3>
                            <p id="selectedUserEmail" class="text-sm text-white text-opacity-80"></p>
                        </div>
                    </div>
                    <button onclick="clearSelection()" class="px-4 py-2 bg-white bg-opacity-20 hover:bg-opacity-30 rounded-lg transition-all">
                        Clear Selection
                    </button>
                </div>
            </div>
        </div>

        <!-- User Sections -->
        <?php foreach ($users as $userIndex => $user): ?>
            <div class="user-section mb-8 animate-slide-up" 
                 style="animation-delay: <?php echo $userIndex * 0.1; ?>s;"
                 data-user-id="<?php echo $user['id']; ?>">
                
                <!-- User Header -->
                <div class="glass-card rounded-2xl p-6 mb-4">
                    <div class="flex items-center justify-between flex-wrap gap-4">
                        <div class="flex items-center gap-4">
                            <div class="w-16 h-16 rounded-full bg-gradient-to-r from-primary to-accent flex items-center justify-center text-white text-2xl font-bold overflow-hidden">
                                <?php if (!empty($user['avatar']) && file_exists(__DIR__ . '/../public/' . $user['avatar'])): ?>
                                    <img src="../public/<?php echo htmlspecialchars($user['avatar']); ?>" 
                                         alt="<?php echo htmlspecialchars($user['name']); ?>" 
                                         class="w-full h-full object-cover">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h2 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($user['name']); ?></h2>
                                <p class="text-gray-600"><?php echo htmlspecialchars($user['email']); ?></p>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-4">
                            <!-- Stats -->
                            <div class="flex gap-3">
                                <div class="text-center px-4 py-2 bg-green-50 rounded-xl">
                                    <div class="text-2xl font-bold text-green-700"><?php echo $user['active_habits']; ?></div>
                                    <div class="text-xs text-green-600">Active</div>
                                </div>
                                <div class="text-center px-4 py-2 bg-blue-50 rounded-xl">
                                    <div class="text-2xl font-bold text-blue-700"><?php echo $user['completed_habits']; ?></div>
                                    <div class="text-xs text-blue-600">Completed</div>
                                </div>
                                <div class="text-center px-4 py-2 bg-gray-50 rounded-xl">
                                    <div class="text-2xl font-bold text-gray-700"><?php echo $user['total_habits']; ?></div>
                                    <div class="text-xs text-gray-600">Total</div>
                                </div>
                            </div>
                            
                            <!-- Create Habit Button -->
                            <button onclick="openCreateHabitModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>')"
                                    class="px-6 py-3 bg-gradient-to-r from-primary to-accent text-white rounded-xl font-semibold hover:shadow-lg transition-all duration-300 transform hover:scale-105">
                                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Create Habit
                            </button>
                        </div>
                    </div>
                </div>

                <!-- User's Habits Grid -->
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php 
                    if (isset($habitsByUser[$user['id']])):
                        foreach ($habitsByUser[$user['id']] as $habitIndex => $habit): 
                            $progress = $habit['target_days'] > 0 ? ($habit['completed_count'] / $habit['target_days']) * 100 : 0;
                            $progress = min(100, $progress);
                            
                            // Calculate streak color
                            $streakColor = 'text-gray-700';
                            if ($habit['streak'] >= 30) $streakColor = 'text-purple-700';
                            elseif ($habit['streak'] >= 14) $streakColor = 'text-green-700';
                            elseif ($habit['streak'] >= 7) $streakColor = 'text-blue-700';
                            
                            // Status badge
                            $statusBadge = '';
                            if ($habit['status'] === 'active') {
                                $statusBadge = '<span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">Active</span>';
                            } elseif ($habit['status'] === 'completed') {
                                $statusBadge = '<span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-semibold">Completed</span>';
                            } else {
                                $statusBadge = '<span class="px-3 py-1 bg-red-100 text-red-700 rounded-full text-xs font-semibold">Abandoned</span>';
                            }
                    ?>
                        <div class="glass-card rounded-2xl p-6 habit-card" data-status="<?php echo $habit['status']; ?>">
                            
                            <!-- Habit Header -->
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="font-bold text-xl text-gray-900"><?php echo htmlspecialchars($habit['name']); ?></h4>
                                <?php echo $statusBadge; ?>
                            </div>

                            <!-- Frequency and Streak -->
                            <div class="flex items-center gap-2 mb-4">
                                <span class="px-3 py-1 rounded-full text-xs font-semibold <?php 
                                    echo $habit['frequency'] === 'daily' ? 'bg-green-100 text-green-700' : 
                                        ($habit['frequency'] === 'weekly' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'); 
                                ?>">
                                    <?php echo ucfirst($habit['frequency']); ?>
                                </span>
                                <span class="text-sm text-gray-600">
                                    🔥 <strong class="<?php echo $streakColor; ?>"><?php echo $habit['streak']; ?> days</strong>
                                </span>
                            </div>

                            <!-- Progress Circle -->
                            <div class="flex items-center justify-center mb-4">
                                <div class="relative">
                                    <svg class="w-32 h-32 transform -rotate-90">
                                        <circle cx="64" cy="64" r="56" stroke="#e5e7eb" stroke-width="8" fill="none"/>
                                        <circle cx="64" cy="64" r="56" 
                                                stroke="url(#gradient-<?php echo $habit['id']; ?>)" 
                                                stroke-width="8" 
                                                fill="none"
                                                stroke-linecap="round"
                                                class="progress-ring"
                                                stroke-dasharray="<?php echo 2 * pi() * 56; ?>"
                                                stroke-dashoffset="<?php echo 2 * pi() * 56 * (1 - $progress / 100); ?>"/>
                                        <defs>
                                            <linearGradient id="gradient-<?php echo $habit['id']; ?>" x1="0%" y1="0%" x2="100%" y2="100%">
                                                <stop offset="0%" style="stop-color:#1C2529;stop-opacity:1" />
                                                <stop offset="100%" style="stop-color:#A1D1B1;stop-opacity:1" />
                                            </linearGradient>
                                        </defs>
                                    </svg>
                                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                                        <span class="text-3xl font-bold text-gray-900"><?php echo round($progress); ?>%</span>
                                        <span class="text-xs text-gray-500">Complete</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Stats Grid -->
                            <div class="grid grid-cols-3 gap-3 mb-4">
                                <div class="bg-green-50 rounded-xl p-3 text-center">
                                    <div class="text-2xl font-bold text-green-700"><?php echo $habit['completed_count']; ?></div>
                                    <div class="text-xs text-green-600">Completed</div>
                                </div>
                                <div class="bg-yellow-50 rounded-xl p-3 text-center">
                                    <div class="text-2xl font-bold text-yellow-700"><?php echo $habit['pending_count']; ?></div>
                                    <div class="text-xs text-yellow-600">Pending</div>
                                </div>
                                <div class="bg-red-50 rounded-xl p-3 text-center">
                                    <div class="text-2xl font-bold text-red-700"><?php echo $habit['rejected_count']; ?></div>
                                    <div class="text-xs text-red-600">Rejected</div>
                                </div>
                            </div>

                            <!-- Date Info -->
                            <div class="bg-gray-50 rounded-xl p-3 mb-4 text-sm">
                                <div class="flex justify-between mb-1">
                                    <span class="text-gray-600">Start:</span>
                                    <span class="font-semibold"><?php echo date('M d, Y', strtotime($habit['start_date'])); ?></span>
                                </div>
                                <div class="flex justify-between mb-1">
                                    <span class="text-gray-600">End:</span>
                                    <span class="font-semibold"><?php echo date('M d, Y', strtotime($habit['end_date'])); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Remaining:</span>
                                    <span class="font-semibold <?php echo $habit['days_remaining'] < 0 ? 'text-red-600' : 'text-green-600'; ?>">
                                        <?php echo $habit['days_remaining'] < 0 ? 'Ended' : $habit['days_remaining'] . ' days'; ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="grid grid-cols-2 gap-2">
                                <button onclick="viewHabitDetails(<?php echo $habit['id']; ?>, '<?php echo htmlspecialchars($habit['name']); ?>', '<?php echo htmlspecialchars($habit['user_name']); ?>')"
                                        class="px-3 py-2 bg-gradient-to-r from-primary to-accent text-white rounded-xl font-semibold hover:shadow-lg transition-all text-sm">
                                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    Details
                                </button>
                                <button onclick="openEditHabitModal(<?php echo $habit['id']; ?>)"
                                        class="px-3 py-2 bg-blue-100 text-blue-700 rounded-xl font-semibold hover:bg-blue-200 transition-all text-sm">
                                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                    Edit
                                </button>
                                <button onclick="removeUserFromHabit(<?php echo $habit['id']; ?>, '<?php echo htmlspecialchars($habit['user_name']); ?>', '<?php echo htmlspecialchars($habit['name']); ?>')"
                                        class="px-3 py-2 bg-red-100 text-red-700 rounded-xl font-semibold hover:bg-red-200 transition-all text-sm">
                                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                    Remove
                                </button>
                                <button onclick="deleteHabit(<?php echo $habit['id']; ?>, '<?php echo htmlspecialchars($habit['name']); ?>')"
                                        class="px-3 py-2 bg-gray-100 text-gray-700 rounded-xl font-semibold hover:bg-gray-200 transition-all text-sm">
                                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                    Delete
                                </button>
                            </div>
                        </div>
                    <?php 
                        endforeach;
                    endif;
                    ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Habit Details Modal -->
<div id="detailsModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black bg-opacity-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-primary to-accent sticky top-0">
            <div class="flex items-center justify-between">
                <div>
                    <h2 id="modalTitle" class="text-2xl font-bold text-white mb-1">Habit Details</h2>
                    <p id="modalSubtitle" class="text-white text-opacity-90">Completion history</p>
                </div>
                <button onclick="closeDetailsModal()" class="text-white hover:bg-white hover:bg-opacity-20 rounded-lg p-2 transition-all">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
        
        <div id="detailsContent" class="p-6">
            <!-- Content will be loaded dynamically -->
        </div>
    </div>
</div>

<!-- Create Habit for User Modal -->
<div id="createHabitModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black bg-opacity-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full">
        <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-primary to-accent">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-white mb-1">Create New Habit</h2>
                    <p id="createHabitSubtitle" class="text-white text-opacity-90">Create a habit for this user</p>
                </div>
                <button onclick="closeCreateHabitModal()" class="text-white hover:bg-white hover:bg-opacity-20 rounded-lg p-2 transition-all">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
        
        <form id="createHabitForm" class="p-6">
            <input type="hidden" id="createHabitUserId" name="user_id">
            
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Habit Name *</label>
                <input type="text" 
                       id="createHabitName" 
                       name="name"
                       required
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-accent transition-all"
                       placeholder="e.g., Morning Exercise, Meditation">
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Frequency *</label>
                    <select id="createHabitFrequency" 
                            name="frequency"
                            required
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-accent transition-all">
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Target Days *</label>
                    <input type="number" 
                           id="createHabitTargetDays" 
                           name="target_days"
                           required
                           min="1"
                           value="30"
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-accent transition-all">
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Start Date *</label>
                    <input type="date" 
                           id="createHabitStartDate" 
                           name="start_date"
                           required
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-accent transition-all">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">End Date *</label>
                    <input type="date" 
                           id="createHabitEndDate" 
                           name="end_date"
                           required
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-accent transition-all">
                </div>
            </div>
            
            <div id="createHabitMessage" class="hidden mb-4 p-4 rounded-xl"></div>
            
            <div class="flex gap-3">
                <button type="button" 
                        onclick="closeCreateHabitModal()"
                        class="flex-1 px-6 py-3 bg-gray-200 text-gray-700 rounded-xl font-semibold hover:bg-gray-300 transition-all">
                    Cancel
                </button>
                <button type="submit"
                        class="flex-1 px-6 py-3 bg-gradient-to-r from-primary to-accent text-white rounded-xl font-semibold hover:shadow-lg transition-all">
                    Create Habit
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Add User to Habit Modal (kept for compatibility) -->
<div id="addUserModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black bg-opacity-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full">
        <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-primary to-accent">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-white mb-1">Add User to Habit</h2>
                    <p id="addUserSubtitle" class="text-white text-opacity-90">Select a user to assign this habit</p>
                </div>
                <button onclick="closeAddUserModal()" class="text-white hover:bg-white hover:bg-opacity-20 rounded-lg p-2 transition-all">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
        
        <div class="p-6">
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Search User</label>
                <input type="text" 
                       id="userSearch" 
                       placeholder="Search by name or email..." 
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-accent transition-all"
                       onkeyup="filterUsers()">
            </div>
            
            <div id="usersList" class="space-y-2 max-h-96 overflow-y-auto">
                <!-- Users will be loaded here -->
            </div>
            
            <div id="addUserMessage" class="hidden mt-4 p-4 rounded-xl"></div>
        </div>
    </div>
</div>

<!-- Edit Habit Modal -->
<div id="editHabitModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black bg-opacity-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full">
        <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-primary to-accent">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-white mb-1">Edit Habit</h2>
                    <p class="text-white text-opacity-90">Update habit details</p>
                </div>
                <button onclick="closeEditHabitModal()" class="text-white hover:bg-white hover:bg-opacity-20 rounded-lg p-2 transition-all">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
        
        <form id="editHabitForm" class="p-6">
            <input type="hidden" id="editHabitId" name="habit_id">
            
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Habit Name *</label>
                <input type="text" 
                       id="editHabitName" 
                       name="name"
                       required
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-accent transition-all"
                       placeholder="e.g., Morning Exercise">
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Frequency *</label>
                    <select id="editHabitFrequency" 
                            name="frequency"
                            required
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-accent transition-all">
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Target Days *</label>
                    <input type="number" 
                           id="editHabitTargetDays" 
                           name="target_days"
                           required
                           min="1"
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-accent transition-all"
                           placeholder="30">
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Start Date *</label>
                    <input type="date" 
                           id="editHabitStartDate" 
                           name="start_date"
                           required
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-accent transition-all">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">End Date *</label>
                    <input type="date" 
                           id="editHabitEndDate" 
                           name="end_date"
                           required
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-accent transition-all">
                </div>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Status *</label>
                <select id="editHabitStatus" 
                        name="status"
                        required
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-accent transition-all">
                    <option value="active">Active</option>
                    <option value="completed">Completed</option>
                    <option value="abandoned">Abandoned</option>
                </select>
            </div>
            
            <div id="editHabitMessage" class="hidden mb-4 p-4 rounded-xl"></div>
            
            <div class="flex gap-3">
                <button type="button" 
                        onclick="closeEditHabitModal()"
                        class="flex-1 px-6 py-3 bg-gray-200 text-gray-700 rounded-xl font-semibold hover:bg-gray-300 transition-all">
                    Cancel
                </button>
                <button type="submit"
                        class="flex-1 px-6 py-3 bg-gradient-to-r from-primary to-accent text-white rounded-xl font-semibold hover:shadow-lg transition-all">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Store all users data for searching
    const allUsersData = <?php echo json_encode($users); ?>;
    let currentView = 'all';
    let selectedUserId = null;
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        calculateStats();
        
        // Show all users by default
        if (allUsersData.length > 50) {
            // If more than 50 users, suggest search mode
            toggleView('search');
        }
        
        // Close search results when clicking outside
        document.addEventListener('click', function(e) {
            const searchResults = document.getElementById('searchResults');
            const searchInput = document.getElementById('userFilterSearch');
            if (!searchResults.contains(e.target) && e.target !== searchInput) {
                searchResults.classList.add('hidden');
            }
        });
        
        // Keyboard navigation for search
        const searchInput = document.getElementById('userFilterSearch');
        searchInput.addEventListener('keydown', function(e) {
            const searchResults = document.getElementById('searchResultsList');
            const resultItems = searchResults.querySelectorAll('[onclick^="selectUserFromSearch"]');
            
            if (resultItems.length === 0) return;
            
            let currentIndex = -1;
            resultItems.forEach((item, index) => {
                if (item.classList.contains('bg-gray-100')) {
                    currentIndex = index;
                }
            });
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                resultItems.forEach(item => item.classList.remove('bg-gray-100'));
                const nextIndex = currentIndex < resultItems.length - 1 ? currentIndex + 1 : 0;
                resultItems[nextIndex].classList.add('bg-gray-100');
                resultItems[nextIndex].scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                resultItems.forEach(item => item.classList.remove('bg-gray-100'));
                const prevIndex = currentIndex > 0 ? currentIndex - 1 : resultItems.length - 1;
                resultItems[prevIndex].classList.add('bg-gray-100');
                resultItems[prevIndex].scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'Enter' && currentIndex >= 0) {
                e.preventDefault();
                resultItems[currentIndex].click();
            } else if (e.key === 'Escape') {
                document.getElementById('searchResults').classList.add('hidden');
            }
        });
    });
    
    // Toggle between "View All" and "Search" modes
    function toggleView(mode) {
        currentView = mode;
        const searchSection = document.getElementById('searchSection');
        const viewAllBtn = document.getElementById('viewAllBtn');
        const viewSearchBtn = document.getElementById('viewSearchBtn');
        const userSections = document.querySelectorAll('.user-section');
        
        if (mode === 'search') {
            searchSection.classList.remove('hidden');
            viewSearchBtn.classList.add('bg-gradient-to-r', 'from-primary', 'to-accent', 'text-white');
            viewSearchBtn.classList.remove('bg-gray-200', 'text-gray-700');
            viewAllBtn.classList.remove('bg-gradient-to-r', 'from-primary', 'to-accent', 'text-white');
            viewAllBtn.classList.add('bg-gray-200', 'text-gray-700');
            
            // Hide all user sections initially in search mode
            userSections.forEach(section => section.style.display = 'none');
            
            // Show stats
            document.getElementById('searchStats').classList.remove('hidden');
        } else {
            searchSection.classList.add('hidden');
            viewAllBtn.classList.add('bg-gradient-to-r', 'from-primary', 'to-accent', 'text-white');
            viewAllBtn.classList.remove('bg-gray-200', 'text-gray-700');
            viewSearchBtn.classList.remove('bg-gradient-to-r', 'from-primary', 'to-accent', 'text-white');
            viewSearchBtn.classList.add('bg-gray-200', 'text-gray-700');
            
            // Show all user sections
            userSections.forEach(section => section.style.display = 'block');
            
            // Hide selected user info
            document.getElementById('selectedUserInfo').classList.add('hidden');
        }
    }
    
    // Advanced search with debouncing
    let searchTimeout;
    function performSearch() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            const searchTerm = document.getElementById('userFilterSearch').value.toLowerCase().trim();
            const searchResults = document.getElementById('searchResults');
            const searchResultsList = document.getElementById('searchResultsList');
            const noResults = document.getElementById('noResults');
            const clearBtn = document.getElementById('clearSearchBtn');
            
            // Show/hide clear button
            if (searchTerm) {
                clearBtn.classList.remove('hidden');
            } else {
                clearBtn.classList.add('hidden');
                searchResults.classList.add('hidden');
                return;
            }
            
            // Filter users
            const filteredUsers = allUsersData.filter(user => {
                return user.name.toLowerCase().includes(searchTerm) ||
                       user.email.toLowerCase().includes(searchTerm) ||
                       user.id.toString().includes(searchTerm);
            });
            
            // Display results
            if (filteredUsers.length > 0) {
                searchResultsList.innerHTML = filteredUsers.slice(0, 100).map(user => `
                    <div class="p-4 hover:bg-gray-50 cursor-pointer transition-all flex items-center justify-between"
                         onclick="selectUserFromSearch(${user.id})">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-gradient-to-r from-primary to-accent flex items-center justify-center text-white font-bold overflow-hidden">
                                ${user.avatar ? 
                                    `<img src="../public/${user.avatar}" alt="${user.name}" class="w-full h-full object-cover">` :
                                    user.name.charAt(0).toUpperCase()
                                }
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-900">${highlightMatch(user.name, searchTerm)}</h4>
                                <p class="text-sm text-gray-500">${highlightMatch(user.email, searchTerm)}</p>
                                <p class="text-xs text-gray-400">ID: ${user.id}</p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <div class="text-center px-3 py-1 bg-green-50 rounded-lg">
                                <div class="text-lg font-bold text-green-700">${user.active_habits}</div>
                                <div class="text-xs text-green-600">Active</div>
                            </div>
                            <div class="text-center px-3 py-1 bg-gray-50 rounded-lg">
                                <div class="text-lg font-bold text-gray-700">${user.total_habits}</div>
                                <div class="text-xs text-gray-600">Total</div>
                            </div>
                        </div>
                    </div>
                `).join('');
                
                noResults.classList.add('hidden');
                
                // Add "showing X of Y" message if more than 100 results
                if (filteredUsers.length > 100) {
                    searchResultsList.innerHTML += `
                        <div class="p-4 bg-blue-50 text-center text-sm text-blue-700">
                            Showing first 100 of ${filteredUsers.length} results. Refine your search for better results.
                        </div>
                    `;
                }
            } else {
                searchResultsList.innerHTML = '';
                noResults.classList.remove('hidden');
            }
            
            searchResults.classList.remove('hidden');
        }, 300); // 300ms debounce
    }
    
    // Highlight matching text in search results
    function highlightMatch(text, searchTerm) {
        if (!searchTerm) return text;
        const regex = new RegExp(`(${searchTerm})`, 'gi');
        return text.replace(regex, '<mark class="bg-yellow-200 px-1 rounded">$1</mark>');
    }
    
    // Select user from search results
    function selectUserFromSearch(userId) {
        selectedUserId = userId;
        const user = allUsersData.find(u => u.id === userId);
        
        if (user) {
            // Update selected user info
            document.getElementById('selectedUserAvatar').innerHTML = user.avatar ? 
                `<img src="../public/${user.avatar}" alt="${user.name}" class="w-full h-full object-cover rounded-full">` :
                user.name.charAt(0).toUpperCase();
            document.getElementById('selectedUserName').textContent = user.name;
            document.getElementById('selectedUserEmail').textContent = user.email;
            document.getElementById('selectedUserInfo').classList.remove('hidden');
            
            // Hide search results
            document.getElementById('searchResults').classList.add('hidden');
            
            // Show only selected user's section
            const userSections = document.querySelectorAll('.user-section');
            userSections.forEach(section => {
                if (parseInt(section.dataset.userId) === userId) {
                    section.style.display = 'block';
                    // Smooth scroll to user section
                    section.scrollIntoView({ behavior: 'smooth', block: 'start' });
                } else {
                    section.style.display = 'none';
                }
            });
        }
    }
    
    // Clear search
    function clearSearch() {
        document.getElementById('userFilterSearch').value = '';
        document.getElementById('clearSearchBtn').classList.add('hidden');
        document.getElementById('searchResults').classList.add('hidden');
    }
    
    // Clear selection and show all
    function clearSelection() {
        selectedUserId = null;
        document.getElementById('selectedUserInfo').classList.add('hidden');
        document.getElementById('userFilterSearch').value = '';
        document.getElementById('clearSearchBtn').classList.add('hidden');
        
        // Show all user sections
        const userSections = document.querySelectorAll('.user-section');
        userSections.forEach(section => section.style.display = 'none');
    }
    
    // Calculate and display statistics
    function calculateStats() {
        const activeUsers = allUsersData.filter(u => u.active_habits > 0).length;
        const completedUsers = allUsersData.filter(u => u.completed_habits > 0).length;
        const totalHabits = allUsersData.reduce((sum, u) => sum + u.total_habits, 0);
        const avgHabits = allUsersData.length > 0 ? (totalHabits / allUsersData.length).toFixed(1) : 0;
        
        document.getElementById('statsActiveUsers').textContent = activeUsers;
        document.getElementById('statsCompletedUsers').textContent = completedUsers;
        document.getElementById('statsTotalHabits').textContent = totalHabits;
        document.getElementById('statsAvgHabits').textContent = avgHabits;
    }
    
    // User Filter Functions (kept for compatibility)
    function selectUserFilter(userId) {
        selectUserFromSearch(userId);
    }
    
    function searchUsers() {
        performSearch();
    }
    
    // Create Habit Modal Functions
    let currentSelectedUserId = null;
    
    function openCreateHabitModal(userId, userName) {
        currentSelectedUserId = userId;
        document.getElementById('createHabitUserId').value = userId;
        document.getElementById('createHabitSubtitle').textContent = `Create a new habit for ${userName}`;
        
        // Set default dates (today to 30 days from now)
        const today = new Date();
        const endDate = new Date();
        endDate.setDate(today.getDate() + 30);
        
        document.getElementById('createHabitStartDate').value = today.toISOString().split('T')[0];
        document.getElementById('createHabitEndDate').value = endDate.toISOString().split('T')[0];
        
        document.getElementById('createHabitModal').classList.remove('hidden');
        document.getElementById('createHabitModal').classList.add('flex');
    }
    
    function closeCreateHabitModal() {
        document.getElementById('createHabitModal').classList.add('hidden');
        document.getElementById('createHabitModal').classList.remove('flex');
        document.getElementById('createHabitForm').reset();
        document.getElementById('createHabitMessage').classList.add('hidden');
        currentSelectedUserId = null;
    }
    
    // Handle create habit form submission
    document.getElementById('createHabitForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'create_habit');
        
        try {
            const response = await fetch('monitor_habits.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            const messageDiv = document.getElementById('createHabitMessage');
            messageDiv.classList.remove('hidden');
            
            if (result.success) {
                messageDiv.className = 'mb-4 p-4 rounded-xl bg-green-100 text-green-700';
                messageDiv.textContent = '✅ ' + result.message;
                
                setTimeout(() => {
                    closeCreateHabitModal();
                    location.reload();
                }, 1500);
            } else {
                messageDiv.className = 'mb-4 p-4 rounded-xl bg-red-100 text-red-700';
                messageDiv.textContent = '❌ ' + result.message;
            }
        } catch (error) {
            console.error('Error:', error);
            const messageDiv = document.getElementById('createHabitMessage');
            messageDiv.classList.remove('hidden');
            messageDiv.className = 'mb-4 p-4 rounded-xl bg-red-100 text-red-700';
            messageDiv.textContent = '❌ Error creating habit. Please try again.';
        }
    });
    
    async function viewHabitDetails(habitId, habitName, userName) {
        document.getElementById('modalTitle').textContent = habitName;
        document.getElementById('modalSubtitle').textContent = `${userName}'s Progress`;
        
        try {
            const formData = new FormData();
            formData.append('action', 'get_habit_details');
            formData.append('habit_id', habitId);
            
            const response = await fetch('monitor_habits.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                displayHabitDetails(result.completions);
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to load habit details');
        }
    }
    
    function displayHabitDetails(completions) {
        const detailsContent = document.getElementById('detailsContent');
        
        if (completions.length === 0) {
            detailsContent.innerHTML = `
                <div class="text-center py-12">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-gray-500 text-lg">No completions recorded yet</p>
                </div>
            `;
        } else {
            const statusColors = {
                'pending': 'bg-yellow-100 text-yellow-800 border-yellow-200',
                'approved': 'bg-green-100 text-green-800 border-green-200',
                'rejected': 'bg-red-100 text-red-800 border-red-200'
            };
            
            let html = '<div class="space-y-4">';
            
            completions.forEach(completion => {
                const statusClass = statusColors[completion.verification_status] || 'bg-gray-100 text-gray-800';
                
                html += `
                    <div class="border-2 ${statusClass} rounded-xl p-4">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="font-bold text-gray-900">${new Date(completion.completion_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</span>
                                    <span class="px-3 py-1 ${statusClass} rounded-full text-xs font-semibold uppercase">
                                        ${completion.verification_status}
                                    </span>
                                </div>
                                <p class="text-sm text-gray-600">Submitted: ${new Date(completion.created_at).toLocaleString()}</p>
                            </div>
                            ${completion.points_awarded > 0 ? `
                                <div class="text-right">
                                    <div class="text-2xl font-bold text-green-600">${completion.points_awarded}</div>
                                    <div class="text-xs text-gray-600">Points</div>
                                </div>
                            ` : ''}
                        </div>
                        
                        ${completion.description ? `
                            <div class="mb-3 p-3 bg-white bg-opacity-50 rounded-lg">
                                <p class="text-sm text-gray-700">${completion.description}</p>
                            </div>
                        ` : ''}
                        
                        <div class="mb-3">
                            ${completion.proof_type === 'image' ? `
                                <img src="../public/${completion.proof_path}" 
                                     alt="Proof" 
                                     class="w-full h-48 object-cover rounded-lg cursor-pointer hover:opacity-90 transition-opacity"
                                     onclick="window.open('../public/${completion.proof_path}', '_blank')">
                            ` : `
                                <video src="../public/${completion.proof_path}" 
                                       class="w-full h-48 object-cover rounded-lg"
                                       controls>
                                </video>
                            `}
                        </div>
                        
                        ${completion.verified_by ? `
                            <div class="text-xs text-gray-600">
                                Verified by <strong>${completion.verified_by_name}</strong> on ${new Date(completion.verified_at).toLocaleString()}
                                ${completion.verification_notes ? `<br><em>"${completion.verification_notes}"</em>` : ''}
                            </div>
                        ` : ''}
                    </div>
                `;
            });
            
            html += '</div>';
            detailsContent.innerHTML = html;
        }
        
        // Show modal
        document.getElementById('detailsModal').classList.remove('hidden');
        document.getElementById('detailsModal').classList.add('flex');
    }
    
    function closeDetailsModal() {
        document.getElementById('detailsModal').classList.add('hidden');
        document.getElementById('detailsModal').classList.remove('flex');
    }
    
    async function deleteHabit(habitId, habitName) {
        if (!confirm(`Are you sure you want to delete the habit "${habitName}"? This action cannot be undone.`)) {
            return;
        }
        
        try {
            const formData = new FormData();
            formData.append('action', 'delete_habit');
            formData.append('habit_id', habitId);
            
            const response = await fetch('monitor_habits.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert('✅ ' + result.message);
                location.reload();
            } else {
                alert('❌ ' + result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error deleting habit. Please try again.');
        }
    }
    
    let currentHabitId = null;
    let allUsers = [];
    
    async function openAddUserModal(habitId, habitName) {
        currentHabitId = habitId;
        document.getElementById('addUserSubtitle').textContent = `Assign "${habitName}" to another user`;
        
        try {
            const formData = new FormData();
            formData.append('action', 'get_users');
            
            const response = await fetch('monitor_habits.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                allUsers = result.users;
                displayUsers(allUsers);
                document.getElementById('addUserModal').classList.remove('hidden');
                document.getElementById('addUserModal').classList.add('flex');
            } else {
                alert('❌ Failed to load users');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error loading users. Please try again.');
        }
    }
    
    function displayUsers(users) {
        const usersList = document.getElementById('usersList');
        
        if (users.length === 0) {
            usersList.innerHTML = '<div class="text-center text-gray-500 py-8">No users found</div>';
            return;
        }
        
        usersList.innerHTML = users.map(user => `
            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition-all cursor-pointer user-item"
                 onclick="selectUser(${user.id}, '${user.name.replace(/'/g, "\\'")}')">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full bg-gradient-to-r from-primary to-accent flex items-center justify-center text-white font-bold text-lg overflow-hidden">
                        ${user.avatar ? 
                            `<img src="../public/${user.avatar}" alt="${user.name}" class="w-full h-full object-cover">` :
                            user.name.charAt(0).toUpperCase()
                        }
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-900">${user.name}</h4>
                        <p class="text-sm text-gray-500">${user.email}</p>
                    </div>
                </div>
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </div>
        `).join('');
    }
    
    function filterUsers() {
        const searchTerm = document.getElementById('userSearch').value.toLowerCase();
        const filteredUsers = allUsers.filter(user => 
            user.name.toLowerCase().includes(searchTerm) || 
            user.email.toLowerCase().includes(searchTerm)
        );
        displayUsers(filteredUsers);
    }
    
    async function selectUser(userId, userName) {
        if (!confirm(`Add user "${userName}" to this habit?`)) {
            return;
        }
        
        try {
            const formData = new FormData();
            formData.append('action', 'add_user_to_habit');
            formData.append('habit_id', currentHabitId);
            formData.append('user_id', userId);
            
            const response = await fetch('monitor_habits.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            const messageDiv = document.getElementById('addUserMessage');
            messageDiv.classList.remove('hidden');
            
            if (result.success) {
                messageDiv.className = 'mt-4 p-4 rounded-xl bg-green-100 text-green-700';
                messageDiv.textContent = '✅ ' + result.message;
                
                setTimeout(() => {
                    closeAddUserModal();
                    location.reload();
                }, 1500);
            } else {
                messageDiv.className = 'mt-4 p-4 rounded-xl bg-red-100 text-red-700';
                messageDiv.textContent = '❌ ' + result.message;
            }
        } catch (error) {
            console.error('Error:', error);
            const messageDiv = document.getElementById('addUserMessage');
            messageDiv.classList.remove('hidden');
            messageDiv.className = 'mt-4 p-4 rounded-xl bg-red-100 text-red-700';
            messageDiv.textContent = '❌ Error adding user. Please try again.';
        }
    }
    
    function closeAddUserModal() {
        document.getElementById('addUserModal').classList.add('hidden');
        document.getElementById('addUserModal').classList.remove('flex');
        document.getElementById('userSearch').value = '';
        document.getElementById('addUserMessage').classList.add('hidden');
        currentHabitId = null;
    }
    
    async function removeUserFromHabit(habitId, userName, habitName) {
        if (!confirm(`Remove ${userName} from habit "${habitName}"?\n\nThis will permanently delete this habit assignment and all their progress data.`)) {
            return;
        }
        
        try {
            const formData = new FormData();
            formData.append('action', 'remove_user_from_habit');
            formData.append('habit_id', habitId);
            
            const response = await fetch('monitor_habits.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert('✅ ' + result.message);
                location.reload();
            } else {
                alert('❌ ' + result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error removing user from habit. Please try again.');
        }
    }
    
    async function openEditHabitModal(habitId) {
        try {
            const formData = new FormData();
            formData.append('action', 'get_habit_info');
            formData.append('habit_id', habitId);
            
            const response = await fetch('monitor_habits.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                const habit = result.habit;
                
                // Populate form
                document.getElementById('editHabitId').value = habit.id;
                document.getElementById('editHabitName').value = habit.name;
                document.getElementById('editHabitFrequency').value = habit.frequency;
                document.getElementById('editHabitTargetDays').value = habit.target_days;
                document.getElementById('editHabitStartDate').value = habit.start_date;
                document.getElementById('editHabitEndDate').value = habit.end_date;
                document.getElementById('editHabitStatus').value = habit.status;
                
                // Show modal
                document.getElementById('editHabitModal').classList.remove('hidden');
                document.getElementById('editHabitModal').classList.add('flex');
            } else {
                alert('❌ Failed to load habit details');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error loading habit details. Please try again.');
        }
    }
    
    function closeEditHabitModal() {
        document.getElementById('editHabitModal').classList.add('hidden');
        document.getElementById('editHabitModal').classList.remove('flex');
        document.getElementById('editHabitForm').reset();
        document.getElementById('editHabitMessage').classList.add('hidden');
    }
    
    // Handle edit habit form submission
    document.getElementById('editHabitForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'update_habit');
        
        try {
            const response = await fetch('monitor_habits.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            const messageDiv = document.getElementById('editHabitMessage');
            messageDiv.classList.remove('hidden');
            
            if (result.success) {
                messageDiv.className = 'mb-4 p-4 rounded-xl bg-green-100 text-green-700';
                messageDiv.textContent = '✅ ' + result.message;
                
                setTimeout(() => {
                    closeEditHabitModal();
                    location.reload();
                }, 1500);
            } else {
                messageDiv.className = 'mb-4 p-4 rounded-xl bg-red-100 text-red-700';
                messageDiv.textContent = '❌ ' + result.message;
            }
        } catch (error) {
            console.error('Error:', error);
            const messageDiv = document.getElementById('editHabitMessage');
            messageDiv.classList.remove('hidden');
            messageDiv.className = 'mb-4 p-4 rounded-xl bg-red-100 text-red-700';
            messageDiv.textContent = '❌ Error updating habit. Please try again.';
        }
    });
</script>

</main>
</body>
</html>
