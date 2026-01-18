<?php
require_once __DIR__ . '/../src/helpers/Database.php';

$db = (new Database())->connect();

// Get date range
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// User registrations over time
$stmt = $db->prepare("SELECT DATE(created_at) as date, COUNT(*) as count FROM users WHERE created_at BETWEEN ? AND ? GROUP BY DATE(created_at) ORDER BY date");
$stmt->execute([$startDate, $endDate]);
$registrations = $stmt->fetchAll();

// Assessment severity distribution
$stmt = $db->query("SELECT severity, COUNT(*) as count FROM assessments GROUP BY severity");
$severityData = $stmt->fetchAll();

// Habit completion rates
$stmt = $db->query("SELECT h.frequency, COUNT(hc.id) as completions FROM habits h LEFT JOIN habit_completions hc ON h.id = hc.habit_id WHERE hc.verification_status = 'approved' GROUP BY h.frequency");
$habitData = $stmt->fetchAll();

// Doctor Activity
$stmt = $db->query("SELECT u.name, COUNT(r.id) as recommendations 
                           FROM users u 
                           JOIN doctors d ON u.id = d.id 
                           LEFT JOIN doctor_recommendations r ON d.id = r.doctor_id 
                           GROUP BY u.id ORDER BY recommendations DESC LIMIT 10");
$doctorActivity = $stmt->fetchAll();

// Statistics cards data
$totalUsers = $db->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$totalDoctors = $db->query("SELECT COUNT(*) FROM doctors")->fetchColumn();
$totalAssessments = $db->query("SELECT COUNT(*) FROM assessments")->fetchColumn();
$totalHabits = $db->query("SELECT COUNT(*) FROM habits")->fetchColumn();
$avgScore = $db->query("SELECT AVG(score) FROM assessments")->fetchColumn();

$pageTitle = "Reports & Analytics";
require_once __DIR__ . '/include/header.php';
?>

<!-- Date Range Filter -->
<div class="bg-white rounded-xl shadow-md p-6 mb-6 animate-fade">
    <form method="GET" class="flex items-end gap-4 flex-wrap">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
            <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" 
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-400 focus:border-transparent">
        </div>
        
        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
            <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" 
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-400 focus:border-transparent">
        </div>
        
        <button type="submit" class="px-6 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition font-semibold">
            Apply Filter
        </button>
        
        <button type="button" onclick="window.print()" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-semibold no-print">
            📄 Export PDF
        </button>
    </form>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-6">
    <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white animate-fade">
        <p class="text-blue-100 text-sm font-medium mb-2">Total Users</p>
        <p class="text-4xl font-bold"><?php echo number_format($totalUsers); ?></p>
    </div>
    
    <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white animate-fade">
        <p class="text-green-100 text-sm font-medium mb-2">Total Doctors</p>
        <p class="text-4xl font-bold"><?php echo number_format($totalDoctors); ?></p>
    </div>
    
    <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white animate-fade">
        <p class="text-purple-100 text-sm font-medium mb-2">Assessments</p>
        <p class="text-4xl font-bold"><?php echo number_format($totalAssessments); ?></p>
    </div>
    
    <div class="bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-xl shadow-lg p-6 text-white animate-fade">
        <p class="text-yellow-100 text-sm font-medium mb-2">Active Habits</p>
        <p class="text-4xl font-bold"><?php echo number_format($totalHabits); ?></p>
    </div>
    
    <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-xl shadow-lg p-6 text-white animate-fade">
        <p class="text-red-100 text-sm font-medium mb-2">Avg Score</p>
        <p class="text-4xl font-bold"><?php echo number_format($avgScore, 1); ?></p>
    </div>
</div>

<!-- Charts Grid -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- User Registrations Chart -->
    <div class="bg-white rounded-xl shadow-md p-6 animate-fade">
        <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
            </svg>
            User Registrations Over Time
        </h3>
        <div style="position: relative; height: 300px;">
            <canvas id="registrationsChart"></canvas>
        </div>
    </div>
    
    <!-- Assessment Severity Distribution -->
    <div class="bg-white rounded-xl shadow-md p-6 animate-fade">
        <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/>
            </svg>
            Assessment Severity Distribution
        </h3>
        <div style="position: relative; height: 300px;">
            <canvas id="severityChart"></canvas>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Habit Completion Rates -->
    <div class="bg-white rounded-xl shadow-md p-6 animate-fade">
        <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            Habit Completion by Frequency
        </h3>
        <div style="position: relative; height: 300px;">
            <canvas id="habitsChart"></canvas>
        </div>
    </div>
    
    <!-- Top Doctors by Recommendations -->
    <div class="bg-white rounded-xl shadow-md p-6 animate-fade">
        <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
            Top Doctors by Recommendations
        </h3>
        <div style="position: relative; height: 300px;">
            <canvas id="doctorsChart"></canvas>
        </div>
    </div>
</div>

<!-- Detailed Tables -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Recent Assessments -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden animate-fade">
        <div class="bg-gradient-to-r from-gray-800 to-green-400 px-6 py-4">
            <h3 class="text-lg font-bold text-white">Recent Assessments</h3>
        </div>
        <div class="p-6">
            <?php
            $stmt = $db->query("SELECT a.*, u.name FROM assessments a JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 5");
            $recentAssessments = $stmt->fetchAll();
            ?>
            <div class="space-y-3">
                <?php foreach ($recentAssessments as $assessment): ?>
                    <div class="flex justify-between items-center border-b border-gray-200 pb-3">
                        <div>
                            <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($assessment['name']); ?></p>
                            <p class="text-sm text-gray-600"><?php echo ucfirst($assessment['severity']); ?> - Score: <?php echo $assessment['score']; ?></p>
                        </div>
                        <span class="text-xs text-gray-500"><?php echo date('M j', strtotime($assessment['created_at'])); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Top Active Users -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden animate-fade">
        <div class="bg-gradient-to-r from-gray-800 to-green-400 px-6 py-4">
            <h3 class="text-lg font-bold text-white">Most Active Users</h3>
        </div>
        <div class="p-6">
            <?php
            $stmt = $db->query("SELECT u.name, COUNT(h.id) as habit_count FROM users u LEFT JOIN habits h ON u.id = h.user_id WHERE u.role = 'user' GROUP BY u.id ORDER BY habit_count DESC LIMIT 5");
            $activeUsers = $stmt->fetchAll();
            ?>
            <div class="space-y-3">
                <?php foreach ($activeUsers as $user): ?>
                    <div class="flex justify-between items-center border-b border-gray-200 pb-3">
                        <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($user['name']); ?></p>
                        <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">
                            <?php echo $user['habit_count']; ?> habits
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
// User Registrations Chart
const registrationsCtx = document.getElementById('registrationsChart').getContext('2d');
new Chart(registrationsCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($registrations, 'date')); ?>,
        datasets: [{
            label: 'New Users',
            data: <?php echo json_encode(array_column($registrations, 'count')); ?>,
            borderColor: 'rgb(34, 197, 94)',
            backgroundColor: 'rgba(34, 197, 94, 0.1)',
            tension: 0.4,
            fill: true,
            borderWidth: 3
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { 
                display: true,
                position: 'top'
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                padding: 12,
                cornerRadius: 8
            }
        },
        scales: {
            y: { 
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});

// Severity Distribution Chart
const severityCtx = document.getElementById('severityChart').getContext('2d');
new Chart(severityCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_map('ucfirst', array_column($severityData, 'severity'))); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($severityData, 'count')); ?>,
            backgroundColor: [
                'rgba(239, 68, 68, 0.8)',
                'rgba(249, 115, 22, 0.8)',
                'rgba(234, 179, 8, 0.8)',
                'rgba(34, 197, 94, 0.8)',
                'rgba(59, 130, 246, 0.8)'
            ],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'right',
                labels: {
                    padding: 15,
                    font: {
                        size: 12
                    }
                }
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                padding: 12,
                cornerRadius: 8
            }
        }
    }
});

// Habits Chart
const habitsCtx = document.getElementById('habitsChart').getContext('2d');
new Chart(habitsCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_map('ucfirst', array_column($habitData, 'frequency'))); ?>,
        datasets: [{
            label: 'Completions',
            data: <?php echo json_encode(array_column($habitData, 'completions')); ?>,
            backgroundColor: 'rgba(34, 197, 94, 0.8)',
            borderColor: 'rgb(34, 197, 94)',
            borderWidth: 2,
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { 
                display: false 
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                padding: 12,
                cornerRadius: 8
            }
        },
        scales: {
            y: { 
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});

// Doctors Chart
const doctorsCtx = document.getElementById('doctorsChart').getContext('2d');
new Chart(doctorsCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($doctorActivity, 'name')); ?>,
        datasets: [{
            label: 'Recommendations',
            data: <?php echo json_encode(array_column($doctorActivity, 'recommendations')); ?>,
            backgroundColor: 'rgba(168, 85, 247, 0.8)',
            borderColor: 'rgb(168, 85, 247)',
            borderWidth: 2,
            borderRadius: 8
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { 
                display: false 
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                padding: 12,
                cornerRadius: 8
            }
        },
        scales: {
            x: { 
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});
</script>

<style>
@media print {
    .no-print { display: none !important; }
    body { background: white !important; }
    aside { display: none !important; }
    header { display: none !important; }
    main { padding: 0 !important; }
    .shadow-md, .shadow-lg { box-shadow: none !important; }
    .bg-gradient-to-br, .bg-gradient-to-r { 
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}

canvas {
    max-width: 100%;
}
</style>

<?php require_once __DIR__ . '/include/footer.php'; ?>
