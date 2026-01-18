<?php
/**
 * User Dashboard
 * Health Tracker Application
 */

require_once __DIR__ . '/../src/helpers/Auth.php';
require_once __DIR__ . '/../src/models/User.php';
require_once __DIR__ . '/../src/models/Assessment.php';
require_once __DIR__ . '/../src/models/Habit.php';
require_once __DIR__ . '/../src/models/Activity.php';
require_once __DIR__ . '/../src/models/Recommendation.php';
require_once __DIR__ . '/../src/models/HabitCompletion.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requireRole('user');

$currentUser = $auth->getCurrentUser();
$userModel = new User();
$assessmentModel = new Assessment();
$habitModel = new Habit();
$activityModel = new Activity();
$recommendationModel = new Recommendation();
$completionModel = new HabitCompletion();

// Handle habit proof submission via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_proof') {
    header('Content-Type: application/json');
    
    if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/habits/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExt = strtolower(pathinfo($_FILES['proof_file']['name'], PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov', 'avi'];
        
        if (!in_array($fileExt, $allowedTypes)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type']);
            exit;
        }
        
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
                'user_id' => $currentUser['id'],
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
}

// Get user stats
$stats = $userModel->getUserStats($currentUser['id']);
$recentAssessments = $assessmentModel->getUserAssessments($currentUser['id'], 3);
$recentHabits = $habitModel->getUserHabits($currentUser['id']);
$recentActivities = $activityModel->getUserActivities($currentUser['id'], 5);
$recentRecommendations = $recommendationModel->getUserRecommendations($currentUser['id'], 3);

// Ensure all variables are arrays (not false/null)
if (!is_array($stats)) $stats = [];
if (!is_array($recentAssessments)) $recentAssessments = [];
if (!is_array($recentHabits)) $recentHabits = [];
if (!is_array($recentActivities)) $recentActivities = [];
if (!is_array($recentRecommendations)) $recentRecommendations = [];

$title = "Dashboard - Health Tracker";
require_once __DIR__ . '/../src/views/partials/header.php';
?>

<div class="min-h-screen bg-gray-50" data-page="dashboard">
    <div class="max-w-7xl mx-auto py-3 px-3 sm:px-4 lg:px-6">
        
        <!-- Welcome Section -->
        <div class="rounded-xl shadow-lg text-white mb-4" style="background: linear-gradient(135deg, #1C2529 0%, #A1D1B1 100%);">
            <div class="p-4 sm:p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-xl sm:text-2xl font-bold mb-0.5">Welcome back, <?php echo htmlspecialchars($currentUser['name']); ?>!</h1>
                        <p class="text-white/90 text-sm">Ready to continue your wellness journey today?</p>
                    </div>
                    <div class="hidden md:block">
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Overview -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <div class="bg-white rounded-lg shadow-sm p-3 hover:shadow-md transition-shadow duration-200">
                <div class="flex items-center gap-2">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xl font-bold text-gray-900" id="habits-count"><?php echo $stats['habits'] ?? 0; ?></p>
                        <p class="text-xs text-gray-600 truncate">Active Habits</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-3 hover:shadow-md transition-shadow duration-200">
                <div class="flex items-center gap-2">
                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xl font-bold text-gray-900" id="activities-count"><?php echo $stats['activities'] ?? 0; ?></p>
                        <p class="text-xs text-gray-600 truncate">Activities Logged</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-3 hover:shadow-md transition-shadow duration-200">
                <div class="flex items-center gap-2">
                    <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xl font-bold text-gray-900" id="points-count"><?php echo number_format($stats['points'] ?? 0); ?></p>
                        <p class="text-xs text-gray-600 truncate">Total Points</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-3 hover:shadow-md transition-shadow duration-200">
                <div class="flex items-center gap-2">
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs text-gray-500 mb-0.5">Current Tier</p>
                        <?php 
                        $tier = $stats['tier'] ?? 'bronze';
                        $tierColors = [
                            'bronze' => 'bg-orange-100 text-orange-800',
                            'silver' => 'bg-gray-200 text-gray-800',
                            'gold' => 'bg-yellow-100 text-yellow-800'
                        ];
                        $tierEmojis = [
                            'bronze' => '🥉',
                            'silver' => '🥈',
                            'gold' => '🥇'
                        ];
                        ?>
                        <span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-full <?php echo $tierColors[$tier]; ?>" id="tier-badge">
                            <span class="mr-0.5"><?php echo $tierEmojis[$tier]; ?></span>
                            <?php echo ucfirst($tier); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            
            <!-- Left Column -->
            <div class="space-y-4">
                
                <!-- Quick Actions -->
                <div class="bg-white rounded-lg shadow-sm p-4">
                    <h2 class="text-base font-bold text-gray-900 mb-3">Quick Actions</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <a href="assessment.php" class="flex items-center p-3 rounded-lg transition-colors duration-200" style="background-color: #A1D1B1; color: #1C2529;" onmouseover="this.style.backgroundColor='#8cc4a1'" onmouseout="this.style.backgroundColor='#A1D1B1'">
                            <div class="w-9 h-9 rounded-lg flex items-center justify-center mr-3 flex-shrink-0" style="background-color: #1C2529; color: white;">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <h3 class="font-semibold text-sm text-gray-900">Take Assessment</h3>
                                <p class="text-xs text-gray-600 truncate">Evaluate your health</p>
                            </div>
                        </a>

                        <a href="habits.php" class="flex items-center p-3 rounded-lg transition-colors duration-200" style="background-color: #A1D1B1; color: #1C2529;" onmouseover="this.style.backgroundColor='#8cc4a1'" onmouseout="this.style.backgroundColor='#A1D1B1'">
                            <div class="w-9 h-9 rounded-lg flex items-center justify-center mr-3 flex-shrink-0" style="background-color: #1C2529; color: white;">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <h3 class="font-semibold text-sm text-gray-900">Track Habits</h3>
                                <p class="text-xs text-gray-600 truncate">Log daily progress</p>
                            </div>
                        </a>

                        <a href="activities.php" class="flex items-center p-3 rounded-lg transition-colors duration-200" style="background-color: #A1D1B1; color: #1C2529;" onmouseover="this.style.backgroundColor='#8cc4a1'" onmouseout="this.style.backgroundColor='#A1D1B1'">
                            <div class="w-9 h-9 rounded-lg flex items-center justify-center mr-3 flex-shrink-0" style="background-color: #1C2529; color: white;">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <h3 class="font-semibold text-sm text-gray-900">Log Activity</h3>
                                <p class="text-xs text-gray-600 truncate">Record exercises</p>
                            </div>
                        </a>

                        <a href="messages.php" class="flex items-center p-3 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors duration-200">
                            <div class="w-9 h-9 bg-indigo-500 rounded-lg flex items-center justify-center mr-3 flex-shrink-0">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <h3 class="font-semibold text-sm text-gray-900">Messages</h3>
                                <p class="text-xs text-gray-600 truncate">Connect with pros</p>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Recent Activities -->
                <?php if (!empty($recentActivities)): ?>
                <div class="bg-white rounded-lg shadow-sm p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-base font-bold text-gray-900">Recent Activities</h2>
                        <a href="/activities.php" class="text-green-400 hover:text-blue-700 text-xs font-medium">View all</a>
                    </div>
                    <div class="space-y-2">
                        <?php foreach (array_slice($recentActivities, 0, 3) as $activity): ?>
                        <div class="flex items-center gap-3 p-2.5 bg-gray-50 rounded-lg">
                            <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style="background-color: #A1D1B1; color: #1C2529;">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="font-medium text-sm text-gray-900 truncate"><?php echo htmlspecialchars($activity['type']); ?></h3>
                                <p class="text-xs text-gray-600">
                                    <?php echo $activity['duration_minutes']; ?> min • 
                                    <?php echo $activity['calories_burned']; ?> cal
                                </p>
                            </div>
                            <span class="text-xs text-gray-500 flex-shrink-0">
                                <?php echo date('M j', strtotime($activity['created_at'])); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Active Habits -->
                <?php if (!empty($recentHabits)): ?>
                <div class="bg-white rounded-lg shadow-sm p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-base font-bold text-gray-900">Today's Habits</h2>
                        <a href="/habits.php" class="text-green-400 hover:text-blue-700 text-xs font-medium">Manage habits</a>
                    </div>
                    <div class="space-y-2">
                        <?php foreach (array_slice($recentHabits, 0, 4) as $habit): ?>
                        <div class="flex items-center justify-between gap-3 p-2.5 bg-gray-50 rounded-lg">
                            <div class="flex items-center gap-2 min-w-0 flex-1">
                                <div class="w-9 h-9 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="font-medium text-sm text-gray-900 truncate"><?php echo htmlspecialchars($habit['name']); ?></h3>
                                    <p class="text-xs text-gray-600"><?php echo $habit['streak']; ?> day streak</p>
                                </div>
                            </div>
                            <button onclick="openProofModal(<?php echo $habit['id']; ?>, '<?php echo htmlspecialchars($habit['name'], ENT_QUOTES); ?>')" 
                                    class="px-3 py-1.5 text-xs font-semibold text-white rounded-lg shadow hover:shadow-md transition-all duration-200 flex-shrink-0" 
                                    style="background: linear-gradient(135deg, #1C2529 0%, #A1D1B1 100%);">
                                Mark Done
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                 <!-- Recent Recommendations -->
                <?php if (!empty($recentRecommendations)): ?>
                <div class="bg-white rounded-lg shadow-sm p-4">
                    <h2 class="text-base font-bold text-gray-900 mb-2.5">System Recommendations</h2>
                    <div class="space-y-2">
                        <?php foreach ($recentRecommendations as $recommendation): ?>
                        <div class="p-2.5 border border-gray-200 rounded-lg">
                            <div class="flex items-start justify-between mb-1 gap-2">
                                <h3 class="font-medium text-xs text-gray-900 flex-1"><?php echo htmlspecialchars($recommendation['title']); ?></h3>
                                <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full <?php 
                                    echo $recommendation['urgency'] === 'high' ? 'bg-red-100 text-red-800' : 
                                        ($recommendation['urgency'] === 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'); 
                                ?>">
                                    <?php echo ucfirst($recommendation['urgency']); ?>
                                </span>
                            </div>
                            <p class="text-xs text-gray-600"><?php echo htmlspecialchars(substr($recommendation['details'], 0, 100) . '...'); ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Column -->
            <div class="space-y-4">
                
                <!-- Health Score -->
                <?php if (!empty($recentAssessments)): ?>
                <?php $latestAssessment = $recentAssessments[0]; ?>
                <div class="bg-white rounded-lg shadow-sm p-4">
                    <h2 class="text-base font-bold text-gray-900 mb-2">Latest Health Score</h2>
                    <div class="text-center">
                        <div class="relative inline-flex items-center justify-center">
                            <div class="w-24 h-24">
                                <svg class="w-24 h-24 transform -rotate-90" viewBox="0 0 100 100">
                                    <circle cx="50" cy="50" r="40" fill="none" stroke="#e5e7eb" stroke-width="8"/>
                                    <circle cx="50" cy="50" r="40" fill="none" stroke="<?php echo $latestAssessment['severity'] === 'high' ? '#ef4444' : ($latestAssessment['severity'] === 'moderate' ? '#f59e0b' : '#10b981'); ?>" 
                                            stroke-width="8" stroke-dasharray="<?php echo (100 - $latestAssessment['score']) * 2.51; ?> 251.2"
                                            stroke-linecap="round"/>
                                </svg>
                            </div>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <div class="text-center">
                                    <div class="text-lg font-bold text-gray-900"><?php echo $latestAssessment['score']; ?></div>
                                    <div class="text-xs text-gray-600">Score</div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full <?php 
                                echo $latestAssessment['severity'] === 'high' ? 'bg-red-100 text-red-800' : 
                                    ($latestAssessment['severity'] === 'moderate' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'); 
                            ?>">
                                <?php echo ucfirst($latestAssessment['severity']); ?> Risk
                            </span>
                        </div>
                        <p class="text-xs text-gray-600 mt-1.5">
                            Assessed <?php echo date('M j, Y', strtotime($latestAssessment['created_at'])); ?>
                        </p>
                    </div>
                    <a href="assessment.php" class="block w-full text-center mt-3 text-green-400 hover:text-blue-700 text-xs font-medium">
                        Take new assessment
                    </a>
                </div>
                <?php endif; ?>

                <!-- Doctor Recommendations -->
                <?php 
                // Get doctor recommendations
                $db = new Database();
                $conn = $db->connect();
                $drStmt = $conn->prepare("
                    SELECT dr.*, u.name as doctor_name, a.score, a.severity, a.created_at as assessment_date
                    FROM doctor_recommendations dr
                    JOIN assessments a ON dr.assessment_id = a.id
                    JOIN users u ON dr.doctor_id = u.id
                    WHERE a.user_id = ?
                    ORDER BY dr.created_at DESC
                    LIMIT 3
                ");
                $drStmt->execute([$currentUser['id']]);
                $doctorRecommendations = $drStmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                
                <?php if (!empty($doctorRecommendations)): ?>
                <div class="bg-gradient-to-br from-purple-50 to-blue-50 rounded-lg shadow-md p-3.5 border border-purple-200">
                    <div class="flex items-center gap-2 mb-2.5">
                        <div class="w-8 h-8 bg-gradient-to-r from-purple-500 to-blue-500 rounded-full flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h2 class="text-sm font-bold text-gray-900">Doctor's Recommendations</h2>
                            <p class="text-xs text-gray-600 truncate">Personalized healthcare advice</p>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <?php foreach ($doctorRecommendations as $drec): ?>
                        <div class="bg-white p-3 rounded-lg shadow-sm border border-purple-100">
                            <div class="flex items-start justify-between mb-1.5 gap-2">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <div class="w-7 h-7 bg-gradient-to-r from-gray-800 to-green-400 rounded-full flex items-center justify-center text-white font-bold text-xs flex-shrink-0">
                                        Dr
                                    </div>
                                    <div class="min-w-0">
                                        <h3 class="font-bold text-xs text-gray-900 truncate"><?php echo htmlspecialchars($drec['doctor_name']); ?></h3>
                                        <p class="text-xs text-gray-500 truncate">
                                            <?php echo date('M j, Y', strtotime($drec['assessment_date'])); ?>
                                        </p>
                                    </div>
                                </div>
                                <span class="text-xs text-gray-500 flex-shrink-0"><?php echo date('M j', strtotime($drec['created_at'])); ?></span>
                            </div>
                            
                            <div class="bg-blue-50 border-l-4 border-blue-500 p-2 rounded-r-lg mb-2">
                                <p class="text-xs text-gray-800 leading-relaxed"><?php echo nl2br(htmlspecialchars($drec['recommendation_text'])); ?></p>
                            </div>
                            
                            <div class="flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-1 bg-gray-100 text-gray-700 rounded-full font-semibold">
                                        Score: <?php echo $drec['score']; ?>%
                                    </span>
                                    <span class="px-2 py-1 rounded-full font-semibold <?php 
                                        echo $drec['severity'] === 'critical' ? 'bg-red-100 text-red-800' : 
                                            ($drec['severity'] === 'poor' ? 'bg-orange-100 text-orange-800' : 
                                            ($drec['severity'] === 'fair' ? 'bg-yellow-100 text-yellow-800' : 
                                            ($drec['severity'] === 'good' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'))); 
                                    ?>">
                                        <?php echo ucfirst($drec['severity']); ?>
                                    </span>
                                </div>
                                <a href="messages.php" class="text-green-400 hover:text-blue-700 font-semibold flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                    </svg>
                                    Reply
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <a href="messages.php" class="block w-full text-center mt-2.5 text-purple-700 hover:text-purple-900 text-xs font-semibold">
                        View all doctor messages →
                    </a>
                </div>
                <?php endif; ?>
                
               

                <!-- Progress Tip -->
                <div class="bg-gradient-to-r from-green-400 to-blue-500 rounded-lg shadow-md p-3.5 text-white">
                    <h2 class="text-sm font-bold mb-1.5">Daily Tip</h2>
                    <p class="text-green-100 text-xs mb-2.5 leading-relaxed">
                        "Small changes lead to big results. Focus on one healthy habit at a time and build from there."
                    </p>
                    <div class="flex items-center text-green-100">
                        <svg class="w-3.5 h-3.5 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-6-3a2 2 0 11-4 0 2 2 0 014 0zm-2 4a5 5 0 00-4.546 2.916A5.986 5.986 0 0010 16a5.986 5.986 0 004.546-2.084A5 5 0 0010 11z" clip-rule="evenodd"/>
                        </svg>
                        <span class="text-xs">Health Tracker Team</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Proof Submission Modal -->
<div id="proofModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(8px);" onclick="closeModalOnBackdrop(event)">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl transform transition-all max-h-[90vh] flex flex-col" onclick="event.stopPropagation()">
        <!-- Fixed Header -->
        <div class="flex items-center justify-between p-6 border-b border-gray-200">
            <div>
                <h3 class="text-2xl font-bold" style="color: #1C2529;">Submit Proof</h3>
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
                <button type="submit" id="submitProofBtn" class="flex-1 px-6 py-3 rounded-xl font-semibold text-white shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105" style="background: linear-gradient(135deg, #1C2529 0%, #A1D1B1 100%);">
                    Submit for Review
                </button>
            </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Custom Scrollbar Styles */
.custom-scrollbar::-webkit-scrollbar {
    width: 8px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 10px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #1C2529 0%, #A1D1B1 100%);
    border-radius: 10px;
    transition: background 0.3s ease;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, #0f1215 0%, #8cc4a1 100%);
}

/* Firefox Scrollbar */
.custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: #A1D1B1 #f1f5f9;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize dashboard-specific features
    if (window.app) {
        window.app.loadDashboardStats();
    }
});

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
    document.body.style.overflow = 'hidden';
}

function closeProofModal() {
    document.getElementById('proofModal').classList.add('hidden');
    document.getElementById('proofModal').classList.remove('flex');
    document.body.style.overflow = 'auto';
}

function closeModalOnBackdrop(event) {
    if (event.target === event.currentTarget) {
        closeProofModal();
    }
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

async function submitProof(event) {
    event.preventDefault();
    
    const submitBtn = document.getElementById('submitProofBtn');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Uploading...';
    
    const formData = new FormData();
    formData.append('action', 'submit_proof');
    formData.append('habit_id', document.getElementById('proofHabitId').value);
    formData.append('proof_file', document.getElementById('proofFile').files[0]);
    formData.append('description', document.getElementById('proofDescription').value);
    
    try {
        const response = await fetch('dashboard.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('✅ ' + result.message, 'success');
            closeProofModal();
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification('❌ ' + result.message, 'error');
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('❌ Error submitting proof. Please try again.', 'error');
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    }
}

function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 px-6 py-4 rounded-xl shadow-2xl z-50 ${type === 'success' ? 'bg-green-500' : 'bg-red-500'} text-white font-semibold transition-all duration-300`;
    notification.style.transform = 'translateY(-20px)';
    notification.style.opacity = '0';
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.transform = 'translateY(0)';
        notification.style.opacity = '1';
    }, 10);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateY(-20px)';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Close modal on ESC key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modal = document.getElementById('proofModal');
        if (modal && !modal.classList.contains('hidden')) {
            closeProofModal();
        }
    }
});
</script>

<?php require_once __DIR__ . '/../src/views/partials/footer.php'; ?>
