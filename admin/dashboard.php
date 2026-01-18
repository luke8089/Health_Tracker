<?php
$pageTitle = "Dashboard";
require_once __DIR__ . '/include/header.php';
require_once __DIR__ . '/../src/helpers/Database.php';

$db = (new Database())->connect();

// Get statistics
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
    $totalUsers = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE role = 'doctor'");
    $totalDoctors = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM assessments");
    $totalAssessments = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM habits WHERE status = 'active'");
    $totalHabits = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM habit_completions WHERE verification_status = 'pending'");
    $pendingVerifications = $stmt->fetch()['total'] ?? 0;
    
    // Recent users
    $stmt = $db->query("SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 5");
    $recentUsers = $stmt->fetchAll();
    
    // Assessment stats
    $stmt = $db->query("SELECT severity, COUNT(*) as count FROM assessments WHERE severity IS NOT NULL AND severity != '' GROUP BY severity");
    $severityStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $totalUsers = $totalDoctors = $totalAssessments = $totalHabits = $pendingVerifications = 0;
    $recentUsers = [];
    $severityStats = [];
}
?>

<!-- Stats Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Total Users -->
    <div class="bg-white rounded-xl shadow-md p-6 animate-fade hover:shadow-xl transition-shadow">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">Total Users</p>
                <p class="text-3xl font-bold text-gray-900"><?php echo number_format($totalUsers); ?></p>
            </div>
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- Total Doctors -->
    <div class="bg-white rounded-xl shadow-md p-6 animate-fade hover:shadow-xl transition-shadow" style="animation-delay: 0.1s;">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">Total Doctors</p>
                <p class="text-3xl font-bold text-gray-900"><?php echo number_format($totalDoctors); ?></p>
            </div>
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- Total Assessments -->
    <div class="bg-white rounded-xl shadow-md p-6 animate-fade hover:shadow-xl transition-shadow" style="animation-delay: 0.2s;">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">Assessments</p>
                <p class="text-3xl font-bold text-gray-900"><?php echo number_format($totalAssessments); ?></p>
            </div>
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- Active Habits -->
    <div class="bg-white rounded-xl shadow-md p-6 animate-fade hover:shadow-xl transition-shadow" style="animation-delay: 0.3s;">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">Active Habits</p>
                <p class="text-3xl font-bold text-gray-900"><?php echo number_format($totalHabits); ?></p>
            </div>
            <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Grid -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- Recent Users -->
    <div class="bg-white rounded-xl shadow-md p-6 animate-fade">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-bold text-gray-900">Recent Users</h3>
            <a href="users.php" class="text-sm text-blue-600 hover:text-blue-700 font-semibold">View All →</a>
        </div>
        
        <?php if (empty($recentUsers)): ?>
            <p class="text-center text-gray-500 py-8">No users yet</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($recentUsers as $user): ?>
                    <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                        <div class="w-10 h-10 bg-gradient-to-br from-gray-800 to-green-400 rounded-full flex items-center justify-center text-white font-bold">
                            <?php echo strtoupper(substr($user['name'], 0, 2)); ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-gray-900 truncate"><?php echo htmlspecialchars($user['name']); ?></p>
                            <p class="text-sm text-gray-600 truncate"><?php echo htmlspecialchars($user['email']); ?></p>
                        </div>
                        <span class="px-3 py-1 text-xs font-semibold rounded-full <?php echo $user['role'] === 'doctor' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                            <?php echo ucfirst($user['role']); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Assessment Distribution -->
    <div class="bg-white rounded-xl shadow-md p-6 animate-fade">
        <h3 class="text-lg font-bold text-gray-900 mb-6">Assessment Distribution</h3>
        
        <?php if (empty($severityStats)): ?>
            <p class="text-center text-gray-500 py-8">No assessment data</p>
        <?php else: ?>
            <div class="space-y-4">
                <?php 
                $colors = [
                    'critical' => ['bg' => 'bg-red-500', 'text' => 'text-red-700'],
                    'poor' => ['bg' => 'bg-orange-500', 'text' => 'text-orange-700'],
                    'fair' => ['bg' => 'bg-yellow-500', 'text' => 'text-yellow-700'],
                    'good' => ['bg' => 'bg-green-500', 'text' => 'text-green-700'],
                    'excellent' => ['bg' => 'bg-blue-500', 'text' => 'text-blue-700']
                ];
                $maxCount = max($severityStats);
                foreach ($severityStats as $severity => $count):
                    $percentage = $maxCount > 0 ? ($count / $maxCount) * 100 : 0;
                    $color = $colors[$severity] ?? ['bg' => 'bg-gray-500', 'text' => 'text-gray-700'];
                ?>
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm font-medium <?php echo $color['text']; ?> capitalize"><?php echo $severity; ?></span>
                            <span class="text-sm font-bold text-gray-900"><?php echo $count; ?></span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="<?php echo $color['bg']; ?> h-2 rounded-full transition-all duration-500" style="width: <?php echo $percentage; ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Actions -->
<div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
    <a href="users.php" class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-6 text-white hover:shadow-xl transition-all transform hover:-translate-y-1">
        <svg class="w-12 h-12 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
        </svg>
        <h4 class="font-bold text-lg mb-2">Manage Users</h4>
        <p class="text-sm text-blue-100">View and manage all system users</p>
    </a>

    <a href="habit_requests.php" class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-6 text-white hover:shadow-xl transition-all transform hover:-translate-y-1">
        <div class="relative">
            <svg class="w-12 h-12 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            <?php if ($pendingVerifications > 0): ?>
                <span class="absolute top-0 right-0 w-8 h-8 bg-red-500 rounded-full flex items-center justify-center text-sm font-bold shadow-lg">
                    <?php echo $pendingVerifications > 99 ? '99+' : $pendingVerifications; ?>
                </span>
            <?php endif; ?>
        </div>
        <h4 class="font-bold text-lg mb-2">Habit Requests</h4>
        <p class="text-sm text-green-100">Review pending habit verifications</p>
    </a>

    <a href="reports.php" class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-6 text-white hover:shadow-xl transition-all transform hover:-translate-y-1">
        <svg class="w-12 h-12 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
        </svg>
        <h4 class="font-bold text-lg mb-2">View Reports</h4>
        <p class="text-sm text-purple-100">Analytics and system reports</p>
    </a>
</div>

<?php require_once __DIR__ . '/include/footer.php'; ?>
