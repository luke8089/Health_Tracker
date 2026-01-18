<?php
/**
 * Habit Completion Verification Page
 * For Doctors
 */

require_once __DIR__ . '/../src/helpers/Auth.php';
require_once __DIR__ . '/../src/models/HabitCompletion.php';

// Check authentication - only doctors
$auth = new Auth();
$auth->requireLogin();
$auth->requireRole('doctor');

$currentUser = $auth->getCurrentUser();
$completionModel = new HabitCompletion();

// Handle verification actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'verify') {
        $result = $completionModel->verifyCompletion(
            $_POST['completion_id'],
            $currentUser['id'],
            $_POST['decision'], // 'approve' or 'reject'
            $_POST['notes'] ?? null
        );
        echo json_encode($result);
        exit;
    }
    
    if ($_POST['action'] === 'create_habit') {
        try {
            require_once __DIR__ . '/../src/helpers/Database.php';
            $db = new Database();
            $conn = $db->connect();
            
            $stmt = $conn->prepare("
                INSERT INTO habits (user_id, name, frequency, target_days, start_date, end_date, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())
            ");
            
            $result = $stmt->execute([
                intval($_POST['user_id']),
                $_POST['habit_name'],
                $_POST['frequency'],
                intval($_POST['target_days']),
                $_POST['start_date'],
                $_POST['end_date']
            ]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Habit created successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create habit']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'get_users') {
        try {
            require_once __DIR__ . '/../src/helpers/Database.php';
            $db = new Database();
            $conn = $db->connect();
            
            // SECURITY: Get only users connected to this doctor
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
    
    if ($_POST['action'] === 'assign_user_to_habit') {
        try {
            require_once __DIR__ . '/../src/helpers/Database.php';
            $db = new Database();
            $conn = $db->connect();
            
            $habitId = intval($_POST['habit_id']);
            $userId = intval($_POST['user_id']);
            
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
                SELECT COUNT(*) as count FROM habits 
                WHERE user_id = ? AND name = ? AND status = 'active'
            ");
            $checkStmt->execute([$userId, $originalHabit['name']]);
            $exists = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($exists > 0) {
                echo json_encode(['success' => false, 'message' => 'User already has this habit']);
                exit;
            }
            
            // Create a copy of the habit for the new user
            $stmt = $conn->prepare("
                INSERT INTO habits (user_id, name, frequency, target_days, start_date, end_date, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())
            ");
            
            $result = $stmt->execute([
                $userId,
                $originalHabit['name'],
                $originalHabit['frequency'],
                $originalHabit['target_days'],
                $originalHabit['start_date'],
                $originalHabit['end_date']
            ]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'User successfully assigned to habit!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to assign habit']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// Get pending verifications - only from connected users
$pendingCompletions = $completionModel->getPendingVerifications(100, $currentUser['id']);

// Get all habits created for connected users
try {
    require_once __DIR__ . '/../src/helpers/Database.php';
    $db = new Database();
    $conn = $db->connect();
    
    $stmt = $conn->prepare("
        SELECT DISTINCT h.*, u.name as user_name, u.email as user_email, u.avatar as user_avatar,
               (SELECT COUNT(*) FROM habit_completions WHERE habit_id = h.id AND verification_status = 'approved') as completed_count
        FROM habits h
        INNER JOIN users u ON h.user_id = u.id
        INNER JOIN user_doctor_connections udc ON u.id = udc.user_id
        WHERE udc.doctor_id = ? AND udc.status = 'active'
        ORDER BY h.created_at DESC
    ");
    $stmt->execute([$currentUser['id']]);
    $allHabits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $allHabits = [];
}

$title = "Verify Habit Completions - Health Tracker";
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
    
    .verification-card {
        transition: all 0.3s ease;
    }
    
    .verification-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }
</style>

<div class="container mx-auto px-4 py-8 max-w-7xl">
    <!-- Header -->
    <div class="glass-card rounded-2xl p-8 mb-8 animate-slide-up">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h1 class="text-4xl font-bold text-gray-900 mb-2">Habit Management</h1>
                <p class="text-gray-600">Create, manage, and verify user habit completions</p>
            </div>
            <div class="flex items-center gap-4">
                <button onclick="openCreateHabitModal()" 
                        class="px-6 py-3 bg-gradient-to-r from-primary to-accent text-white rounded-xl font-semibold hover:shadow-lg transition-all duration-300 transform hover:scale-105">
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Create Habit
                </button>
                <div class="bg-gradient-to-r from-primary to-accent text-white px-6 py-3 rounded-xl">
                    <div class="text-3xl font-bold"><?php echo count($pendingCompletions); ?></div>
                    <div class="text-sm">Pending Reviews</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tabs -->
    <div class="glass-card rounded-2xl mb-8 animate-slide-up">
        <div class="flex border-b border-gray-200">
            <button onclick="switchTab('verifications')" id="tab-verifications" class="tab-button active px-8 py-4 font-semibold text-gray-700 border-b-2 border-accent transition-all">
                Pending Verifications (<?php echo count($pendingCompletions); ?>)
            </button>
            <button onclick="switchTab('habits')" id="tab-habits" class="tab-button px-8 py-4 font-semibold text-gray-500 hover:text-gray-700 border-b-2 border-transparent transition-all">
                All Habits (<?php echo count($allHabits); ?>)
            </button>
        </div>
    </div>

    <!-- Verifications Tab Content -->
    <div id="content-verifications" class="tab-content">
        <?php if (empty($pendingCompletions)): ?>
            <!-- No Pending Verifications -->
            <div class="glass-card rounded-2xl p-12 text-center animate-slide-up">
                <svg class="w-24 h-24 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h3 class="text-2xl font-bold text-gray-900 mb-2">All Caught Up!</h3>
                <p class="text-gray-600">No pending habit completions to verify.</p>
            </div>
        <?php else: ?>
            <!-- Verification Grid -->
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($pendingCompletions as $index => $completion): ?>
                <div class="glass-card rounded-2xl p-6 verification-card animate-slide-up" style="animation-delay: <?php echo $index * 0.1; ?>s;" id="habit-<?php echo $completion['id']; ?>">
                    <!-- User Info -->
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-12 h-12 rounded-full bg-gradient-to-r from-primary to-accent flex items-center justify-center text-white font-bold text-lg overflow-hidden">
                            <?php if (!empty($completion['user_avatar']) && file_exists(__DIR__ . '/../public/' . $completion['user_avatar'])): ?>
                                <img src="../public/<?php echo htmlspecialchars($completion['user_avatar']); ?>" alt="<?php echo htmlspecialchars($completion['user_name']); ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <?php echo strtoupper(substr($completion['user_name'], 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900"><?php echo htmlspecialchars($completion['user_name']); ?></h3>
                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($completion['user_email']); ?></p>
                        </div>
                    </div>

                    <!-- Habit Info -->
                    <div class="mb-4">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="font-bold text-lg text-gray-900"><?php echo htmlspecialchars($completion['habit_name']); ?></h4>
                            <span class="px-3 py-1 rounded-full text-xs font-semibold <?php 
                                echo $completion['frequency'] === 'daily' ? 'bg-green-100 text-green-700' : 
                                    ($completion['frequency'] === 'weekly' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'); 
                            ?>">
                                <?php echo ucfirst($completion['frequency']); ?>
                            </span>
                        </div>
                        <p class="text-sm text-gray-600">
                            📅 <?php echo date('M d, Y', strtotime($completion['completion_date'])); ?>
                        </p>
                    </div>

                    <!-- Proof Media -->
                    <div class="mb-4 rounded-xl overflow-hidden bg-gray-100">
                        <?php if ($completion['proof_type'] === 'image'): ?>
                            <img 
                                src="../public/<?php echo htmlspecialchars($completion['proof_path']); ?>" 
                                alt="Proof"
                                class="w-full h-48 object-cover cursor-pointer hover:opacity-90 transition-opacity"
                                onclick="viewMedia('../public/<?php echo htmlspecialchars($completion['proof_path']); ?>', 'image')">
                        <?php else: ?>
                            <video 
                                src="../public/<?php echo htmlspecialchars($completion['proof_path']); ?>" 
                                class="w-full h-48 object-cover"
                                controls>
                            </video>
                        <?php endif; ?>
                    </div>

                    <!-- Description -->
                    <?php if ($completion['description']): ?>
                        <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                            <p class="text-sm text-gray-700"><?php echo htmlspecialchars($completion['description']); ?></p>
                        </div>
                    <?php endif; ?>

                    <!-- Time -->
                    <div class="text-xs text-gray-500 mb-4">
                        Submitted <?php echo date('M d, Y g:i A', strtotime($completion['created_at'])); ?>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-2">
                        <button 
                            onclick="verifyCompletion(<?php echo $completion['id']; ?>, 'approve')"
                            class="flex-1 px-4 py-3 bg-gradient-to-r from-green-500 to-emerald-500 text-white rounded-xl font-semibold hover:shadow-lg transition-all duration-300 transform hover:scale-105">
                            ✓ Approve
                        </button>
                        <button 
                            onclick="verifyCompletion(<?php echo $completion['id']; ?>, 'reject')"
                            class="flex-1 px-4 py-3 bg-gradient-to-r from-red-500 to-pink-500 text-white rounded-xl font-semibold hover:shadow-lg transition-all duration-300 transform hover:scale-105">
                            ✗ Reject
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- All Habits Tab Content -->
    <div id="content-habits" class="tab-content hidden">
        <?php if (empty($allHabits)): ?>
            <div class="glass-card rounded-2xl p-12 text-center animate-slide-up">
                <svg class="w-24 h-24 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <h3 class="text-2xl font-bold text-gray-900 mb-2">No Habits Yet</h3>
                <p class="text-gray-600">Create habits for your patients to get started.</p>
            </div>
        <?php else: ?>
            <!-- Habits Grid -->
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($allHabits as $index => $habit): ?>
                    <div class="glass-card rounded-2xl p-6 verification-card animate-slide-up" style="animation-delay: <?php echo $index * 0.05; ?>s;">
                        <!-- User Info -->
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-12 h-12 rounded-full bg-gradient-to-r from-primary to-accent flex items-center justify-center text-white font-bold text-lg overflow-hidden">
                                <?php if (!empty($habit['user_avatar']) && file_exists(__DIR__ . '/../public/' . $habit['user_avatar'])): ?>
                                    <img src="../public/<?php echo htmlspecialchars($habit['user_avatar']); ?>" alt="<?php echo htmlspecialchars($habit['user_name']); ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($habit['user_name'], 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="font-semibold text-gray-900 truncate"><?php echo htmlspecialchars($habit['user_name']); ?></h3>
                                <p class="text-xs text-gray-500 truncate"><?php echo htmlspecialchars($habit['user_email']); ?></p>
                            </div>
                        </div>

                        <!-- Habit Info -->
                        <div class="mb-4">
                            <div class="flex items-start justify-between mb-2">
                                <h4 class="font-bold text-lg text-gray-900 flex-1"><?php echo htmlspecialchars($habit['name']); ?></h4>
                                <span class="px-2 py-1 rounded-full text-xs font-semibold ml-2 flex-shrink-0 <?php 
                                    echo $habit['status'] === 'active' ? 'bg-green-100 text-green-700' : 
                                        ($habit['status'] === 'completed' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700'); 
                                ?>">
                                    <?php echo ucfirst($habit['status']); ?>
                                </span>
                            </div>
                            
                            <div class="space-y-2 text-sm">
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-600">Frequency:</span>
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold <?php 
                                        echo $habit['frequency'] === 'daily' ? 'bg-green-100 text-green-700' : 
                                            ($habit['frequency'] === 'weekly' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'); 
                                    ?>">
                                        <?php echo ucfirst($habit['frequency']); ?>
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-600">Target Days:</span>
                                    <span class="font-semibold text-gray-900"><?php echo $habit['target_days']; ?> days</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-600">Streak:</span>
                                    <span class="font-semibold text-orange-600">🔥 <?php echo $habit['streak']; ?> days</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-600">Completions:</span>
                                    <span class="font-semibold text-green-600">✅ <?php echo $habit['completed_count']; ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Date Range -->
                        <div class="mb-4 p-3 bg-gray-50 rounded-lg text-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">📅 Start:</span>
                                <span class="font-semibold"><?php echo date('M d, Y', strtotime($habit['start_date'])); ?></span>
                            </div>
                            <div class="flex items-center justify-between mt-1">
                                <span class="text-gray-600">🏁 End:</span>
                                <span class="font-semibold"><?php echo date('M d, Y', strtotime($habit['end_date'])); ?></span>
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <?php 
                            $daysElapsed = max(0, (strtotime('now') - strtotime($habit['start_date'])) / 86400);
                            $progress = min(100, ($habit['completed_count'] / $habit['target_days']) * 100);
                        ?>
                        <div class="mb-4">
                            <div class="flex items-center justify-between text-xs text-gray-600 mb-1">
                                <span>Progress</span>
                                <span><?php echo round($progress); ?>%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-gradient-to-r from-primary to-accent h-2 rounded-full transition-all" style="width: <?php echo $progress; ?>%"></div>
                            </div>
                        </div>

                        <!-- Action Button -->
                        <button 
                            onclick="openAssignUserModal(<?php echo $habit['id']; ?>, '<?php echo htmlspecialchars($habit['name'], ENT_QUOTES); ?>')"
                            class="w-full px-4 py-2 bg-gradient-to-r from-blue-500 to-indigo-500 text-white rounded-xl font-semibold hover:shadow-lg transition-all duration-300 transform hover:scale-105 text-sm">
                            <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                            </svg>
                            Assign to Another User
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Habit Modal -->
<div id="createHabitModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black bg-opacity-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-primary to-accent">
            <div class="flex items-center justify-between">
                <h2 class="text-2xl font-bold text-white">Create Habit for User</h2>
                <button onclick="closeCreateHabitModal()" class="text-white hover:bg-white hover:bg-opacity-20 rounded-lg p-2 transition-all">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
        
        <form id="createHabitForm" class="p-6 space-y-6">
            <!-- User Selection -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Select User *</label>
                <div class="relative">
                    <input type="text" id="userSearch" 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-accent focus:border-accent"
                           placeholder="Search users by name or email..." 
                           onkeyup="filterUsers()"
                           onfocus="showUserDropdown()">
                    <input type="hidden" id="selected_user_id" name="user_id" required>
                    
                    <!-- User Dropdown -->
                    <div id="userDropdown" class="hidden absolute z-10 w-full mt-2 bg-white border-2 border-gray-300 rounded-xl shadow-lg max-h-60 overflow-y-auto">
                        <div id="userList" class="divide-y divide-gray-200">
                            <!-- Users will be loaded here -->
                        </div>
                    </div>
                    
                    <!-- Selected User Display -->
                    <div id="selectedUserDisplay" class="hidden mt-3 p-3 bg-green-50 border-2 border-green-200 rounded-xl">
                        <div class="flex items-center gap-3">
                            <div id="selectedUserAvatar" class="w-10 h-10 rounded-full bg-gradient-to-r from-primary to-accent flex items-center justify-center text-white font-bold"></div>
                            <div>
                                <p id="selectedUserName" class="font-semibold text-gray-900"></p>
                                <p id="selectedUserEmail" class="text-sm text-gray-600"></p>
                            </div>
                            <button type="button" onclick="clearUserSelection()" class="ml-auto text-red-600 hover:text-red-800">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Habit Name -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Habit Name *</label>
                <input type="text" name="habit_name" required
                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-accent focus:border-accent"
                       placeholder="e.g., Morning Exercise, Drink Water">
            </div>
            
            <!-- Frequency -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Frequency *</label>
                <select name="frequency" required
                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-accent focus:border-accent">
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                </select>
            </div>
            
            <!-- Target Days -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Target Days *</label>
                <input type="number" name="target_days" min="1" max="365" value="30" required
                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-accent focus:border-accent"
                       placeholder="Number of days">
                <p class="text-xs text-gray-500 mt-1">Total number of days this habit should be tracked</p>
            </div>
            
            <!-- Date Range -->
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Start Date *</label>
                    <input type="date" name="start_date" required
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-accent focus:border-accent"
                           value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">End Date *</label>
                    <input type="date" name="end_date" required
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-accent focus:border-accent"
                           value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                </div>
            </div>
            
            <!-- Submit Buttons -->
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeCreateHabitModal()" 
                        class="flex-1 px-6 py-3 bg-gray-200 text-gray-700 font-semibold rounded-xl hover:bg-gray-300 transition-all">
                    Cancel
                </button>
                <button type="submit" 
                        class="flex-1 px-6 py-3 bg-gradient-to-r from-primary to-accent text-white font-semibold rounded-xl hover:shadow-lg transition-all">
                    Create Habit
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Assign User to Habit Modal -->
<div id="assignUserModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black bg-opacity-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-blue-500 to-indigo-500">
            <div class="flex items-center justify-between">
                <h2 class="text-2xl font-bold text-white">Assign User to Habit</h2>
                <button onclick="closeAssignUserModal()" class="text-white hover:bg-white hover:bg-opacity-20 rounded-lg p-2 transition-all">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
        
        <div class="p-6">
            <div class="mb-4 p-4 bg-blue-50 border-2 border-blue-200 rounded-xl">
                <p class="text-sm text-gray-700"><strong>Habit:</strong> <span id="assignHabitName" class="text-blue-700 font-semibold"></span></p>
            </div>
            
            <form id="assignUserForm" class="space-y-4">
                <input type="hidden" id="assign_habit_id" name="habit_id">
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Select User *</label>
                    <div class="relative">
                        <input type="text" id="assignUserSearch" 
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Search users..." 
                               onkeyup="filterAssignUsers()"
                               onfocus="showAssignUserDropdown()">
                        <input type="hidden" id="assign_selected_user_id" name="user_id" required>
                        
                        <div id="assignUserDropdown" class="hidden absolute z-10 w-full mt-2 bg-white border-2 border-gray-300 rounded-xl shadow-lg max-h-60 overflow-y-auto">
                            <div id="assignUserList" class="divide-y divide-gray-200"></div>
                        </div>
                        
                        <div id="assignSelectedUserDisplay" class="hidden mt-3 p-3 bg-green-50 border-2 border-green-200 rounded-xl">
                            <div class="flex items-center gap-3">
                                <div id="assignSelectedUserAvatar" class="w-10 h-10 rounded-full bg-gradient-to-r from-primary to-accent flex items-center justify-center text-white font-bold"></div>
                                <div class="flex-1">
                                    <p id="assignSelectedUserName" class="font-semibold text-gray-900"></p>
                                    <p id="assignSelectedUserEmail" class="text-sm text-gray-600"></p>
                                </div>
                                <button type="button" onclick="clearAssignUserSelection()" class="text-red-600 hover:text-red-800">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="closeAssignUserModal()" 
                            class="flex-1 px-6 py-3 bg-gray-200 text-gray-700 font-semibold rounded-xl hover:bg-gray-300 transition-all">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="flex-1 px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-500 text-white font-semibold rounded-xl hover:shadow-lg transition-all">
                        Assign User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Media Viewer Modal -->
<div id="mediaModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black bg-opacity-75" onclick="closeMediaModal()">
    <div class="max-w-4xl w-full" onclick="event.stopPropagation()">
        <div class="flex justify-end mb-4">
            <button onclick="closeMediaModal()" class="text-white hover:text-gray-300">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <img id="modalImage" class="hidden w-full rounded-xl" src="" alt="Proof">
    </div>
</div>

<script>
    // Tab switching
    function switchTab(tabName) {
        // Update tab buttons
        document.querySelectorAll('.tab-button').forEach(btn => {
            btn.classList.remove('active', 'text-gray-700', 'border-accent');
            btn.classList.add('text-gray-500', 'border-transparent');
        });
        document.getElementById('tab-' + tabName).classList.add('active', 'text-gray-700', 'border-accent');
        document.getElementById('tab-' + tabName).classList.remove('text-gray-500', 'border-transparent');
        
        // Update content
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.add('hidden');
        });
        document.getElementById('content-' + tabName).classList.remove('hidden');
    }
    
    async function verifyCompletion(completionId, decision) {
        const notes = decision === 'reject' ? prompt('Optional: Add a note explaining why this was rejected:') : null;
        
        if (decision === 'reject' && notes === null) {
            return; // User cancelled
        }
        
        const formData = new FormData();
        formData.append('action', 'verify');
        formData.append('completion_id', completionId);
        formData.append('decision', decision);
        if (notes) formData.append('notes', notes);
        
        try {
            const response = await fetch('verify_habits.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Show success message
                alert('✅ ' + result.message);
                location.reload();
            } else {
                alert('❌ ' + result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error processing verification. Please try again.');
        }
    }
    
    function viewMedia(path, type) {
        const modal = document.getElementById('mediaModal');
        const modalImage = document.getElementById('modalImage');
        
        if (type === 'image') {
            modalImage.src = path;
            modalImage.classList.remove('hidden');
        }
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
    
    function closeMediaModal() {
        document.getElementById('mediaModal').classList.add('hidden');
        document.getElementById('mediaModal').classList.remove('flex');
    }
    
    // Create Habit Modal Functions
    let allUsers = [];
    
    async function openCreateHabitModal() {
        document.getElementById('createHabitModal').classList.remove('hidden');
        document.getElementById('createHabitModal').classList.add('flex');
        
        // Load users
        await loadUsers();
    }
    
    function closeCreateHabitModal() {
        document.getElementById('createHabitModal').classList.add('hidden');
        document.getElementById('createHabitModal').classList.remove('flex');
        document.getElementById('createHabitForm').reset();
        clearUserSelection();
    }
    
    async function loadUsers() {
        try {
            const formData = new FormData();
            formData.append('action', 'get_users');
            
            const response = await fetch('verify_habits.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                allUsers = result.users;
                displayUsers(allUsers);
            } else {
                alert('Failed to load users: ' + result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error loading users');
        }
    }
    
    function displayUsers(users) {
        const userList = document.getElementById('userList');
        
        if (users.length === 0) {
            userList.innerHTML = '<div class="p-4 text-center text-gray-500">No users found</div>';
            return;
        }
        
        userList.innerHTML = users.map(user => `
            <div class="p-3 hover:bg-gray-50 cursor-pointer transition-colors" onclick="selectUser(${user.id}, '${user.name.replace(/'/g, "\\'")}', '${user.email}', '${user.avatar || ''}')">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-r from-primary to-accent flex items-center justify-center text-white font-bold overflow-hidden">
                        ${user.avatar ? `<img src="../public/${user.avatar}" alt="${user.name}" class="w-full h-full object-cover">` : user.name.charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <p class="font-semibold text-gray-900">${user.name}</p>
                        <p class="text-sm text-gray-600">${user.email}</p>
                    </div>
                </div>
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
        showUserDropdown();
    }
    
    function showUserDropdown() {
        document.getElementById('userDropdown').classList.remove('hidden');
    }
    
    function selectUser(userId, userName, userEmail, userAvatar) {
        // Set hidden input
        document.getElementById('selected_user_id').value = userId;
        
        // Update search input
        document.getElementById('userSearch').value = userName;
        
        // Show selected user display
        const avatarDiv = document.getElementById('selectedUserAvatar');
        if (userAvatar) {
            avatarDiv.innerHTML = `<img src="../public/${userAvatar}" alt="${userName}" class="w-full h-full object-cover rounded-full">`;
        } else {
            avatarDiv.textContent = userName.charAt(0).toUpperCase();
        }
        
        document.getElementById('selectedUserName').textContent = userName;
        document.getElementById('selectedUserEmail').textContent = userEmail;
        document.getElementById('selectedUserDisplay').classList.remove('hidden');
        
        // Hide dropdown
        document.getElementById('userDropdown').classList.add('hidden');
    }
    
    function clearUserSelection() {
        document.getElementById('selected_user_id').value = '';
        document.getElementById('userSearch').value = '';
        document.getElementById('selectedUserDisplay').classList.add('hidden');
    }
    
    // Handle form submission
    document.getElementById('createHabitForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const userId = document.getElementById('selected_user_id').value;
        if (!userId) {
            alert('Please select a user');
            return;
        }
        
        const formData = new FormData(this);
        formData.append('action', 'create_habit');
        
        try {
            const response = await fetch('verify_habits.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert('✅ ' + result.message);
                closeCreateHabitModal();
            } else {
                alert('❌ ' + result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error creating habit. Please try again.');
        }
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('userDropdown');
        const searchInput = document.getElementById('userSearch');
        
        if (!dropdown.contains(e.target) && e.target !== searchInput) {
            dropdown.classList.add('hidden');
        }
    });
    
    // Assign User to Habit Functions
    let assignUsersCache = [];
    
    async function openAssignUserModal(habitId, habitName) {
        document.getElementById('assignUserModal').classList.remove('hidden');
        document.getElementById('assignUserModal').classList.add('flex');
        document.getElementById('assign_habit_id').value = habitId;
        document.getElementById('assignHabitName').textContent = habitName;
        
        // Load users if not already loaded
        if (assignUsersCache.length === 0) {
            await loadAssignUsers();
        } else {
            displayAssignUsers(assignUsersCache);
        }
    }
    
    function closeAssignUserModal() {
        document.getElementById('assignUserModal').classList.add('hidden');
        document.getElementById('assignUserModal').classList.remove('flex');
        document.getElementById('assignUserForm').reset();
        clearAssignUserSelection();
    }
    
    async function loadAssignUsers() {
        try {
            const formData = new FormData();
            formData.append('action', 'get_users');
            
            const response = await fetch('verify_habits.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                assignUsersCache = result.users;
                displayAssignUsers(assignUsersCache);
            }
        } catch (error) {
            console.error('Error:', error);
        }
    }
    
    function displayAssignUsers(users) {
        const userList = document.getElementById('assignUserList');
        
        if (users.length === 0) {
            userList.innerHTML = '<div class="p-4 text-center text-gray-500">No users found</div>';
            return;
        }
        
        userList.innerHTML = users.map(user => `
            <div class="p-3 hover:bg-gray-50 cursor-pointer transition-colors" onclick="selectAssignUser(${user.id}, '${user.name.replace(/'/g, "\\'")}', '${user.email}', '${user.avatar || ''}')">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-r from-primary to-accent flex items-center justify-center text-white font-bold overflow-hidden">
                        ${user.avatar ? `<img src="../public/${user.avatar}" alt="${user.name}" class="w-full h-full object-cover">` : user.name.charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <p class="font-semibold text-gray-900">${user.name}</p>
                        <p class="text-sm text-gray-600">${user.email}</p>
                    </div>
                </div>
            </div>
        `).join('');
    }
    
    function filterAssignUsers() {
        const searchTerm = document.getElementById('assignUserSearch').value.toLowerCase();
        const filteredUsers = assignUsersCache.filter(user => 
            user.name.toLowerCase().includes(searchTerm) || 
            user.email.toLowerCase().includes(searchTerm)
        );
        displayAssignUsers(filteredUsers);
        showAssignUserDropdown();
    }
    
    function showAssignUserDropdown() {
        document.getElementById('assignUserDropdown').classList.remove('hidden');
    }
    
    function selectAssignUser(userId, userName, userEmail, userAvatar) {
        document.getElementById('assign_selected_user_id').value = userId;
        document.getElementById('assignUserSearch').value = userName;
        
        const avatarDiv = document.getElementById('assignSelectedUserAvatar');
        if (userAvatar) {
            avatarDiv.innerHTML = `<img src="../public/${userAvatar}" alt="${userName}" class="w-full h-full object-cover rounded-full">`;
        } else {
            avatarDiv.textContent = userName.charAt(0).toUpperCase();
        }
        
        document.getElementById('assignSelectedUserName').textContent = userName;
        document.getElementById('assignSelectedUserEmail').textContent = userEmail;
        document.getElementById('assignSelectedUserDisplay').classList.remove('hidden');
        document.getElementById('assignUserDropdown').classList.add('hidden');
    }
    
    function clearAssignUserSelection() {
        document.getElementById('assign_selected_user_id').value = '';
        document.getElementById('assignUserSearch').value = '';
        document.getElementById('assignSelectedUserDisplay').classList.add('hidden');
    }
    
    // Handle assign user form submission
    document.getElementById('assignUserForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const userId = document.getElementById('assign_selected_user_id').value;
        if (!userId) {
            alert('Please select a user');
            return;
        }
        
        const formData = new FormData(this);
        formData.append('action', 'assign_user_to_habit');
        
        try {
            const response = await fetch('verify_habits.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert('✅ ' + result.message);
                closeAssignUserModal();
            } else {
                alert('❌ ' + result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error assigning user. Please try again.');
        }
    });
    
    // Scroll to habit if hash is present
    document.addEventListener('DOMContentLoaded', function() {
        if (window.location.hash) {
            const element = document.querySelector(window.location.hash);
            if (element) {
                setTimeout(() => {
                    element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    element.classList.add('ring-4', 'ring-accent');
                }, 500);
            }
        }
    });
</script>

<style>
    .tab-button.active {
        color: #1C2529;
        border-bottom-color: #A1D1B1;
    }
</style>

</main>
</body>
</html>
