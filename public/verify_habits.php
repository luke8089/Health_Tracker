<?php
/**
 * Habit Completion Verification Page
 * For Doctors and Instructors
 */

require_once __DIR__ . '/../src/helpers/Auth.php';
require_once __DIR__ . '/../src/models/HabitCompletion.php';

// Check authentication - only doctors and admins
$auth = new Auth();
$auth->requireLogin();
$currentUser = $auth->getCurrentUser();

if ($currentUser['role'] !== 'doctor' && $currentUser['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

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
}

// Get pending verifications
$pendingCompletions = $completionModel->getPendingVerifications(100);

require_once __DIR__ . '/../src/includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Habit Completions - Health Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
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
</head>
<body class="pt-20">
    <div class="container mx-auto px-4 py-8 max-w-7xl">
        <!-- Header -->
        <div class="glass-card rounded-2xl p-8 mb-8 animate-slide-up">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-4xl font-bold text-gray-900 mb-2">Habit Verification</h1>
                    <p class="text-gray-600">Review and approve user habit completions</p>
                </div>
                <div class="bg-gradient-to-r from-purple-500 to-indigo-500 text-white px-6 py-3 rounded-xl">
                    <div class="text-3xl font-bold"><?php echo count($pendingCompletions); ?></div>
                    <div class="text-sm">Pending Reviews</div>
                </div>
            </div>
        </div>

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
                    <div class="glass-card rounded-2xl p-6 verification-card animate-slide-up" style="animation-delay: <?php echo $index * 0.1; ?>s;">
                        <!-- User Info -->
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-12 h-12 rounded-full bg-gradient-to-r from-purple-400 to-indigo-400 flex items-center justify-center text-white font-bold text-lg">
                                <?php echo strtoupper(substr($completion['user_name'], 0, 1)); ?>
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
                                    src="<?php echo htmlspecialchars($completion['proof_path']); ?>" 
                                    alt="Proof"
                                    class="w-full h-48 object-cover cursor-pointer"
                                    onclick="viewMedia('<?php echo htmlspecialchars($completion['proof_path']); ?>', 'image')">
                            <?php else: ?>
                                <video 
                                    src="<?php echo htmlspecialchars($completion['proof_path']); ?>" 
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
                                class="flex-1 px-4 py-3 bg-gradient-to-r from-green-500 to-emerald-500 text-white rounded-xl font-semibold hover:shadow-lg transition-all duration-300">
                                ✓ Approve
                            </button>
                            <button 
                                onclick="verifyCompletion(<?php echo $completion['id']; ?>, 'reject')"
                                class="flex-1 px-4 py-3 bg-gradient-to-r from-red-500 to-pink-500 text-white rounded-xl font-semibold hover:shadow-lg transition-all duration-300">
                                ✗ Reject
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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
                    alert('✅ ' + result.message);
                    location.reload();
                } else {
                    alert('❌ ' + result.message);
                }
            } catch (error) {
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
    </script>
</body>
</html>
