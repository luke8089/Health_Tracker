<?php
/**
 * Activities Page
 * Health Tracker Application
 */

require_once __DIR__ . '/../src/helpers/Auth.php';
require_once __DIR__ . '/../src/models/User.php';
require_once __DIR__ . '/../src/models/Activity.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requireRole('user');

$currentUser = $auth->getCurrentUser();
$activityModel = new Activity();

// Handle form submission
$message = null;
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $data = [
                    'type' => $_POST['type'] ?? '',
                    'duration_minutes' => (int)($_POST['duration_minutes'] ?? 0),
                    'weight' => (float)($_POST['weight'] ?? 70)
                ];
                
                if ($activityModel->create($currentUser['id'], $data)) {
                    $message = 'Activity logged successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to log activity. Please try again.';
                    $messageType = 'error';
                }
                break;
                
            case 'delete':
                $activityId = (int)($_POST['activity_id'] ?? 0);
                if ($activityModel->delete($activityId)) {
                    $message = 'Activity deleted successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to delete activity.';
                    $messageType = 'error';
                }
                break;
        }
    }
}

// Get pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Get user activities and stats
$activities = $activityModel->getUserActivities($currentUser['id'], $perPage + 1, $offset);
$hasMore = count($activities) > $perPage;
if ($hasMore) {
    array_pop($activities);
}

$stats = $activityModel->getActivityStats($currentUser['id']);
$weeklyGoal = $activityModel->getWeeklyGoalProgress($currentUser['id']);

// Activity types
$activityTypes = [
    'walking' => ['icon' => '🚶', 'label' => 'Walking', 'met' => 3.8],
    'running' => ['icon' => '🏃', 'label' => 'Running', 'met' => 11.0],
    'cycling' => ['icon' => '🚴', 'label' => 'Cycling', 'met' => 8.0],
    'swimming' => ['icon' => '🏊', 'label' => 'Swimming', 'met' => 8.0],
    'yoga' => ['icon' => '🧘', 'label' => 'Yoga', 'met' => 3.0],
    'weight_training' => ['icon' => '🏋️', 'label' => 'Weight Training', 'met' => 6.0],
    'dancing' => ['icon' => '💃', 'label' => 'Dancing', 'met' => 5.0],
    'sports' => ['icon' => '⚽', 'label' => 'Sports', 'met' => 6.5],
    'hiking' => ['icon' => '🥾', 'label' => 'Hiking', 'met' => 7.0],
    'other' => ['icon' => '🎯', 'label' => 'Other Activity', 'met' => 4.0]
];

$title = "Activities - Health Tracker";
require_once __DIR__ . '/../src/views/partials/header.php';
?>

<div class="min-h-screen bg-gray-50" data-page="activities">
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Activity Tracker</h1>
            <p class="text-gray-600">Log your physical activities and track your fitness progress</p>
        </div>

        <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Left Column - Stats & Add Activity -->
            <div class="space-y-6">
                
                <!-- Weekly Goal Progress -->
                <div class="bg-gradient-to-br from-green-500 to-teal-600 rounded-2xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-bold">Weekly Goal</h2>
                        <span class="text-2xl">🎯</span>
                    </div>
                    <div class="mb-4">
                        <div class="flex items-end justify-between mb-2">
                            <span class="text-3xl font-bold"><?php echo $weeklyGoal['minutes_completed']; ?></span>
                            <span class="text-sm opacity-90">/ <?php echo $weeklyGoal['weekly_goal']; ?> min</span>
                        </div>
                        <div class="w-full bg-white bg-opacity-30 rounded-full h-3">
                            <div class="bg-white rounded-full h-3 transition-all duration-300" 
                                 style="width: <?php echo min(100, $weeklyGoal['progress_percentage']); ?>%"></div>
                        </div>
                    </div>
                    <p class="text-sm opacity-90">
                        <?php if ($weeklyGoal['goal_reached']): ?>
                            🎉 Congratulations! You've reached your weekly goal!
                        <?php else: ?>
                            <?php echo $weeklyGoal['weekly_goal'] - $weeklyGoal['minutes_completed']; ?> minutes to go this week
                        <?php endif; ?>
                    </p>
                </div>

                <!-- Quick Stats -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Your Stats</h2>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                    <span class="text-xl">📊</span>
                                </div>
                                <span class="text-sm text-gray-600">Total Activities</span>
                            </div>
                            <span class="text-xl font-bold text-gray-900"><?php echo $stats['total_activities']; ?></span>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                                    <span class="text-xl">⏱️</span>
                                </div>
                                <span class="text-sm text-gray-600">Total Duration</span>
                            </div>
                            <span class="text-xl font-bold text-gray-900"><?php echo number_format($stats['total_duration']); ?> min</span>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center mr-3">
                                    <span class="text-xl">🔥</span>
                                </div>
                                <span class="text-sm text-gray-600">Calories Burned</span>
                            </div>
                            <span class="text-xl font-bold text-gray-900"><?php echo number_format($stats['total_calories']); ?></span>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                    <span class="text-xl">📈</span>
                                </div>
                                <span class="text-sm text-gray-600">This Week</span>
                            </div>
                            <span class="text-xl font-bold text-gray-900"><?php echo $stats['this_week']; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Add Activity Form -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Log New Activity</h2>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="add">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Activity Type</label>
                            <select name="type" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                <option value="">Select activity...</option>
                                <?php foreach ($activityTypes as $key => $type): ?>
                                <option value="<?php echo $key; ?>">
                                    <?php echo $type['icon'] . ' ' . $type['label']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Duration (minutes)</label>
                            <input type="number" name="duration_minutes" min="1" max="600" required 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                   placeholder="30">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Your Weight (kg)</label>
                            <input type="number" name="weight" min="30" max="300" step="0.1" value="70"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                   placeholder="70">
                            <p class="text-xs text-gray-500 mt-1">Used for calorie calculation</p>
                        </div>
                        
                        <button type="submit" class="w-full bg-gradient-to-r from-green-500 to-teal-600 text-white font-semibold py-3 rounded-lg hover:from-green-600 hover:to-teal-700 transition-all duration-200 shadow-md">
                            ✓ Log Activity
                        </button>
                    </form>
                </div>
            </div>

            <!-- Right Column - Activity History -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-md p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-gray-900">Activity History</h2>
                        <?php if (!empty($stats['by_type'])): ?>
                        <button onclick="toggleStats()" class="text-sm text-green-600 hover:text-green-700 font-medium">
                            View Stats
                        </button>
                        <?php endif; ?>
                    </div>

                    <!-- Activity Type Stats (Hidden by default) -->
                    <?php if (!empty($stats['by_type'])): ?>
                    <div id="activityStats" class="hidden mb-6 p-4 bg-gray-50 rounded-lg">
                        <h3 class="font-semibold text-gray-900 mb-3">Activities by Type</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <?php foreach ($stats['by_type'] as $typeStat): ?>
                            <div class="flex items-center justify-between p-3 bg-white rounded-lg">
                                <div class="flex items-center">
                                    <span class="text-2xl mr-3"><?php echo $activityTypes[$typeStat['type']]['icon'] ?? '🎯'; ?></span>
                                    <div>
                                        <p class="font-medium text-gray-900"><?php echo $activityTypes[$typeStat['type']]['label'] ?? ucfirst($typeStat['type']); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo $typeStat['count']; ?> activities</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-gray-900"><?php echo number_format($typeStat['duration']); ?> min</p>
                                    <p class="text-xs text-orange-600"><?php echo number_format($typeStat['calories']); ?> cal</p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Activity List -->
                    <?php if (empty($activities)): ?>
                    <div class="text-center py-12">
                        <div class="text-6xl mb-4">🏃‍♂️</div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">No Activities Yet</h3>
                        <p class="text-gray-600 mb-6">Start logging your physical activities to track your fitness journey!</p>
                    </div>
                    <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($activities as $activity): ?>
                        <?php 
                            $type = $activityTypes[$activity['type']] ?? $activityTypes['other'];
                            $activityDate = new DateTime($activity['created_at']);
                            $now = new DateTime();
                            $diff = $now->diff($activityDate);
                            
                            if ($diff->d == 0) {
                                $timeAgo = 'Today';
                            } elseif ($diff->d == 1) {
                                $timeAgo = 'Yesterday';
                            } elseif ($diff->d < 7) {
                                $timeAgo = $diff->d . ' days ago';
                            } else {
                                $timeAgo = $activityDate->format('M j, Y');
                            }
                        ?>
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors duration-200">
                            <div class="flex items-center flex-1">
                                <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center mr-4 shadow-sm">
                                    <span class="text-2xl"><?php echo $type['icon']; ?></span>
                                </div>
                                <div class="flex-1">
                                    <h3 class="font-semibold text-gray-900"><?php echo $type['label']; ?></h3>
                                    <div class="flex items-center gap-4 text-sm text-gray-600 mt-1">
                                        <span class="flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            <?php echo $activity['duration_minutes']; ?> min
                                        </span>
                                        <span class="flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/>
                                            </svg>
                                            <?php echo number_format($activity['calories_burned']); ?> cal
                                        </span>
                                        <span class="text-gray-500"><?php echo $timeAgo; ?></span>
                                    </div>
                                </div>
                            </div>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this activity?');" class="ml-4">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="activity_id" value="<?php echo $activity['id']; ?>">
                                <button type="submit" class="text-red-600 hover:text-red-800 p-2 rounded-lg hover:bg-red-50 transition-colors duration-200">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($page > 1 || $hasMore): ?>
                    <div class="flex items-center justify-between mt-6 pt-4 border-t border-gray-200">
                        <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>" class="flex items-center text-green-600 hover:text-green-700 font-medium">
                            <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                            Previous
                        </a>
                        <?php else: ?>
                        <div></div>
                        <?php endif; ?>
                        
                        <span class="text-sm text-gray-600">Page <?php echo $page; ?></span>
                        
                        <?php if ($hasMore): ?>
                        <a href="?page=<?php echo $page + 1; ?>" class="flex items-center text-green-600 hover:text-green-700 font-medium">
                            Next
                            <svg class="w-5 h-5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                        <?php else: ?>
                        <div></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleStats() {
    const statsDiv = document.getElementById('activityStats');
    if (statsDiv) {
        statsDiv.classList.toggle('hidden');
    }
}

// Auto-hide success messages after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alertDiv = document.querySelector('[class*="bg-green-100"], [class*="bg-red-100"]');
    if (alertDiv && alertDiv.textContent.includes('successfully')) {
        setTimeout(() => {
            alertDiv.style.transition = 'opacity 0.5s';
            alertDiv.style.opacity = '0';
            setTimeout(() => alertDiv.remove(), 500);
        }, 5000);
    }
});
</script>

<?php require_once __DIR__ . '/../src/views/partials/footer.php'; ?>
