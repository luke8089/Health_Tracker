<?php
/**
 * Doctor Dashboard - Optimized
 * Health Tracker Application
 */

// Load optimized bootstrap
require_once __DIR__ . '/includes/bootstrap.php';

// Get doctor statistics (cached for 3 minutes)
$stats = getDoctorStats($currentUser['id'], $queryOptimizer);
$totalPatients = $stats['total_patients'];
$pendingVerifications = $stats['pending_verifications'];
$totalAssessments = $stats['total_assessments'];
$unreadMessages = $stats['unread_messages'];

// Get recent data (all cached)
$recentPatients = getDoctorPatients($currentUser['id'], $queryOptimizer, 5);
$recentVerifications = getPendingVerifications($currentUser['id'], $queryOptimizer, 5);
$recentMessages = getDoctorMessages($currentUser['id'], $queryOptimizer, 5);

// Set page title
$title = setDoctorPageTitle("Dashboard");
require_once __DIR__ . '/includes/header.php';
?>

<style>
    .card-hover {
        transition: all 0.3s ease;
    }
    .card-hover:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    }
</style>

    <div class="min-h-screen">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            
            <!-- Welcome Section -->
            <div class="rounded-2xl shadow-xl text-white mb-8 fade-in" style="background: linear-gradient(135deg, #1C2529 0%, #A1D1B1 100%);">
                <div class="p-8">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-3xl font-bold mb-2">Welcome, Dr. <?php echo htmlspecialchars($currentUser['name']); ?>!</h1>
                            <p class="text-white/90 text-lg">
                                <?php if (!empty($currentUser['specialty'])): ?>
                                    Specialty: <?php echo htmlspecialchars(ucfirst($currentUser['specialty'])); ?>
                                <?php endif; ?>
                            </p>
                            <p class="text-white/80 text-sm mt-1">
                                License: <?php echo htmlspecialchars($currentUser['license_number'] ?? 'N/A'); ?>
                            </p>
                        </div>
                        <div class="hidden md:block">
                            <div class="w-24 h-24 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Patients -->
                <a href="messages.php" class="bg-white rounded-xl shadow-md p-6 card-hover fade-in cursor-pointer">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-2xl font-bold text-gray-900"><?php echo $totalPatients; ?></p>
                            <p class="text-sm text-gray-600">Total Patients</p>
                        </div>
                    </div>
                </a>

                <!-- Pending Verifications -->
                <a href="verify_habits.php" class="bg-white rounded-xl shadow-md p-6 card-hover fade-in cursor-pointer" style="animation-delay: 0.1s;">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center relative">
                                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                                <?php if ($pendingVerifications > 0): ?>
                                    <span class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 rounded-full flex items-center justify-center text-xs text-white font-bold">
                                        <?php echo $pendingVerifications > 9 ? '9+' : $pendingVerifications; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-2xl font-bold text-gray-900"><?php echo $pendingVerifications; ?></p>
                            <p class="text-sm text-gray-600">Pending Verifications</p>
                        </div>
                    </div>
                </a>

                <!-- Assessments Reviewed -->
                <a href="assessments.php" class="bg-white rounded-xl shadow-md p-6 card-hover fade-in cursor-pointer" style="animation-delay: 0.2s;">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-2xl font-bold text-gray-900"><?php echo $totalAssessments; ?></p>
                            <p class="text-sm text-gray-600">Assessments</p>
                        </div>
                    </div>
                </a>

                <!-- Unread Messages -->
                <a href="messages.php" class="bg-white rounded-xl shadow-md p-6 card-hover fade-in cursor-pointer" style="animation-delay: 0.3s;">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center relative">
                                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                <?php if ($unreadMessages > 0): ?>
                                    <span class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 rounded-full flex items-center justify-center text-xs text-white font-bold">
                                        <?php echo $unreadMessages > 9 ? '9+' : $unreadMessages; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-2xl font-bold text-gray-900"><?php echo $unreadMessages; ?></p>
                            <p class="text-sm text-gray-600">Unread Messages</p>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                
                <!-- Recent Patients -->
                <div class="bg-white rounded-xl shadow-md p-6 fade-in" style="animation-delay: 0.4s;">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                            <svg class="w-6 h-6 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                            Recent Patients
                        </h2>
                        <a href="messages.php" class="text-sm text-accent hover:text-primary font-semibold">View All →</a>
                    </div>
                    
                    <?php if (empty($recentPatients)): ?>
                        <div class="text-center py-12">
                            <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            <p class="text-gray-500">No patients yet</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($recentPatients as $patient): ?>
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary to-accent flex items-center justify-center text-white font-bold overflow-hidden">
                                            <?php if (!empty($patient['avatar']) && file_exists(__DIR__ . '/../public/' . $patient['avatar'])): ?>
                                                <img src="../public/<?php echo htmlspecialchars($patient['avatar']); ?>" alt="<?php echo htmlspecialchars($patient['name']); ?>" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <?php echo strtoupper(substr($patient['name'], 0, 2)); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($patient['name']); ?></p>
                                            <p class="text-sm text-gray-600"><?php echo $patient['assessment_count']; ?> assessments</p>
                                        </div>
                                    </div>
                                    <a href="messages.php?user_id=<?php echo $patient['id']; ?>" class="text-accent hover:text-primary transition-all hover:scale-110" title="Message <?php echo htmlspecialchars($patient['name']); ?>">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                        </svg>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pending Verifications -->
                <div class="bg-white rounded-xl shadow-md p-6 fade-in" style="animation-delay: 0.5s;">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                            <svg class="w-6 h-6 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Pending Verifications
                        </h2>
                        <a href="verify_habits.php" class="text-sm text-accent hover:text-primary font-semibold">View All →</a>
                    </div>
                    
                    <?php if (empty($recentVerifications)): ?>
                        <div class="text-center py-12">
                            <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-gray-500">No pending verifications</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($recentVerifications as $verification): ?>
                                <div class="p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                                    <div class="flex items-start justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary to-accent flex items-center justify-center text-white font-bold overflow-hidden">
                                                <?php if (!empty($verification['avatar']) && file_exists(__DIR__ . '/../public/' . $verification['avatar'])): ?>
                                                    <img src="../public/<?php echo htmlspecialchars($verification['avatar']); ?>" alt="<?php echo htmlspecialchars($verification['user_name']); ?>" class="w-full h-full object-cover">
                                                <?php else: ?>
                                                    <?php echo strtoupper(substr($verification['user_name'], 0, 2)); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($verification['habit_name']); ?></p>
                                                <p class="text-sm text-gray-600">by <?php echo htmlspecialchars($verification['user_name']); ?></p>
                                                <p class="text-xs text-gray-500 mt-1">
                                                    <?php 
                                                        $date = new DateTime($verification['created_at']);
                                                        echo $date->format('M j, Y g:i A');
                                                    ?>
                                                </p>
                                        </div>
                                    </div>
                                        <a href="verify_habits.php#habit-<?php echo $verification['id']; ?>" class="text-accent hover:text-primary text-sm font-semibold">
                                            Review →
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <a href="verify_habits.php" class="bg-white rounded-xl shadow-md p-6 card-hover fade-in text-center" style="animation-delay: 0.6s;">
                    <div class="w-16 h-16 bg-gradient-to-br from-accent to-primary rounded-xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h3 class="font-bold text-gray-900 mb-2">Verify Habits</h3>
                    <p class="text-sm text-gray-600">Review and verify patient habit completions</p>
                </a>

                <a href="messages.php" class="bg-white rounded-xl shadow-md p-6 card-hover fade-in text-center" style="animation-delay: 0.7s;">
                    <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                    </div>
                    <h3 class="font-bold text-gray-900 mb-2">Messages</h3>
                    <p class="text-sm text-gray-600">Communicate with your patients</p>
                </a>

                <a href="profile.php" class="bg-white rounded-xl shadow-md p-6 card-hover fade-in text-center" style="animation-delay: 0.8s;">
                    <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <h3 class="font-bold text-gray-900 mb-2">My Profile</h3>
                    <p class="text-sm text-gray-600">Update your profile information</p>
                </a>
            </div>

        </div>
    </div>

</main>
</body>
</html>
