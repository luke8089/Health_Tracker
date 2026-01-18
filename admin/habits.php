<?php
require_once __DIR__ . '/../src/helpers/Database.php';
require_once __DIR__ . '/../src/helpers/Utils.php';

$db = (new Database())->connect();

// Handle AJAX requests for habit details
if (isset($_GET['action']) && $_GET['action'] === 'get_habit_details') {
    header('Content-Type: application/json');
    
    try {
        $habitId = $_GET['id'] ?? 0;
        
        // Get habit details
        $stmt = $db->prepare("
            SELECT h.*, u.name as user_name, u.email as user_email
            FROM habits h
            LEFT JOIN users u ON h.user_id = u.id
            WHERE h.id = ?
        ");
        $stmt->execute([$habitId]);
        $habit = $stmt->fetch();
        
        if (!$habit) {
            echo json_encode(['success' => false, 'message' => 'Habit not found']);
            exit;
        }
        
        // Get habit completions
        $stmt = $db->prepare("
            SELECT hc.*, hc.completion_date, hc.verification_status,
                   hc.proof_path, hc.description, hc.verified_at, hc.verified_by
            FROM habit_completions hc
            WHERE hc.habit_id = ?
            ORDER BY hc.completion_date DESC
            LIMIT 50
        ");
        $stmt->execute([$habitId]);
        $completions = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'habit' => $habit,
            'completions' => $completions
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'delete':
                $habitId = $_POST['habit_id'] ?? 0;
                $stmt = $db->prepare("DELETE FROM habits WHERE id = ?");
                $stmt->execute([$habitId]);
                Utils::redirect('habits.php', 'Habit deleted successfully', 'success');
                break;
                
            case 'toggle_status':
                $habitId = $_POST['habit_id'] ?? 0;
                $currentStatus = $_POST['current_status'] ?? 'active';
                $newStatus = $currentStatus === 'active' ? 'inactive' : 'active';
                $stmt = $db->prepare("UPDATE habits SET status = ? WHERE id = ?");
                $stmt->execute([$newStatus, $habitId]);
                Utils::redirect('habits.php', 'Habit status updated', 'success');
                break;
        }
    } catch (Exception $e) {
        Utils::redirect('habits.php', 'Error: ' . $e->getMessage(), 'error');
    }
}

// Get filters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$frequency = $_GET['frequency'] ?? '';
$page = max(1, $_GET['page'] ?? 1);
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Build query
$where = ["1=1"];
$params = [];

if ($search) {
    $where[] = "(h.name LIKE ? OR u.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status) {
    $where[] = "h.status = ?";
    $params[] = $status;
}

if ($frequency) {
    $where[] = "h.frequency = ?";
    $params[] = $frequency;
}

$whereClause = implode(' AND ', $where);

// Get total count
$stmt = $db->prepare("SELECT COUNT(*) as total FROM habits h LEFT JOIN users u ON h.user_id = u.id WHERE $whereClause");
$stmt->execute($params);
$totalHabits = $stmt->fetch()['total'];
$totalPages = ceil($totalHabits / $perPage);

// Get habits
$stmt = $db->prepare("
    SELECT h.*, u.name as user_name, u.email as user_email,
           (SELECT COUNT(*) FROM habit_completions WHERE habit_id = h.id) as total_completions,
           (SELECT COUNT(*) FROM habit_completions WHERE habit_id = h.id AND verification_status = 'approved') as approved_completions
    FROM habits h
    LEFT JOIN users u ON h.user_id = u.id
    WHERE $whereClause
    ORDER BY h.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$habits = $stmt->fetchAll();

// Get statistics
$stmt = $db->query("SELECT status, COUNT(*) as count FROM habits GROUP BY status");
$statusCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$stmt = $db->query("SELECT frequency, COUNT(*) as count FROM habits GROUP BY frequency");
$frequencyCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Now include header after all processing
$pageTitle = "Habits Management";
require_once __DIR__ . '/include/header.php';
?>

<script src="../public/assets/js/modal.js"></script>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-md p-6 animate-fade hover:shadow-lg transition">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">Total Habits</p>
                <p class="text-3xl font-bold text-gray-900"><?php echo number_format($totalHabits); ?></p>
            </div>
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-md p-6 animate-fade hover:shadow-lg transition" style="animation-delay: 0.1s;">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">Active Habits</p>
                <p class="text-3xl font-bold text-green-600"><?php echo number_format($statusCounts['active'] ?? 0); ?></p>
            </div>
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-md p-6 animate-fade hover:shadow-lg transition" style="animation-delay: 0.2s;">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">Inactive Habits</p>
                <p class="text-3xl font-bold text-gray-600"><?php echo number_format($statusCounts['inactive'] ?? 0); ?></p>
            </div>
            <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-md p-6 animate-fade hover:shadow-lg transition" style="animation-delay: 0.3s;">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">Completed Habits</p>
                <p class="text-3xl font-bold text-gray-900"><?php echo number_format($statusCounts['completed'] ?? 0); ?></p>
            </div>
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
    </div>
</div>

<!-- Header with Search and Filters -->
<div class="bg-white rounded-xl shadow-md p-6 mb-6 animate-fade">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Habits Management</h2>
            <p class="text-sm text-gray-600 mt-1">Manage and monitor user habits</p>
        </div>
        
        <!-- Search and Filters -->
        <form method="GET" class="flex flex-col sm:flex-row gap-3">
            <div class="relative">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Search habits..." 
                       class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-400 focus:border-transparent w-full sm:w-64">
                <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            
            <select name="status" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-400 focus:border-transparent">
                <option value="">All Status</option>
                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
            </select>
            
            <select name="frequency" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-400 focus:border-transparent">
                <option value="">All Frequencies</option>
                <option value="daily" <?php echo $frequency === 'daily' ? 'selected' : ''; ?>>Daily</option>
                <option value="weekly" <?php echo $frequency === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                <option value="monthly" <?php echo $frequency === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
            </select>
            
            <button type="submit" class="px-6 py-2 bg-gradient-to-r from-gray-800 to-green-400 text-white rounded-lg hover:shadow-lg transition-all transform hover:-translate-y-0.5">
                Filter
            </button>
            
            <?php if ($search || $status || $frequency): ?>
                <a href="habits.php" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Clear
                </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Habits Table -->
<div class="bg-white rounded-xl shadow-md overflow-hidden animate-fade">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Habit</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">User</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Frequency</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Progress</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Streak</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-4 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($habits)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                            <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-lg font-semibold">No habits found</p>
                            <p class="text-sm">Try adjusting your filters</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($habits as $habit): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4">
                                <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($habit['name']); ?></p>
                                <p class="text-sm text-gray-600">Target: <?php echo $habit['target_days']; ?> days</p>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($habit['user_name'] ?? 'Unknown'); ?></p>
                                <p class="text-xs text-gray-600"><?php echo htmlspecialchars($habit['user_email'] ?? ''); ?></p>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 capitalize">
                                    <?php echo htmlspecialchars($habit['frequency']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 bg-gray-200 rounded-full h-2 max-w-[100px]">
                                            <?php 
                                            $progress = $habit['target_days'] > 0 ? ($habit['approved_completions'] / $habit['target_days']) * 100 : 0;
                                            $progress = min(100, $progress);
                                            ?>
                                            <div class="bg-green-500 h-2 rounded-full transition-all" style="width: <?php echo $progress; ?>%"></div>
                                        </div>
                                        <span class="text-xs font-semibold text-gray-600"><?php echo round($progress); ?>%</span>
                                    </div>
                                    <p class="text-xs text-gray-500"><?php echo $habit['approved_completions']; ?>/<?php echo $habit['target_days']; ?> completed</p>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <svg class="w-5 h-5 text-orange-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                    </svg>
                                    <span class="font-bold text-gray-900"><?php echo $habit['streak']; ?></span>
                                    <span class="text-xs text-gray-600">day<?php echo $habit['streak'] != 1 ? 's' : ''; ?></span>
                                </div>
                                <?php if ($habit['missed_days'] > 0): ?>
                                    <p class="text-xs text-red-600 mt-1">Missed: <?php echo $habit['missed_days']; ?> days</p>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="habit_id" value="<?php echo $habit['id']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo $habit['status']; ?>">
                                    <button type="submit" class="px-3 py-1 text-xs font-semibold rounded-full transition <?php 
                                        echo $habit['status'] === 'active' ? 'bg-green-100 text-green-800 hover:bg-green-200' : 
                                            ($habit['status'] === 'completed' ? 'bg-blue-100 text-blue-800 hover:bg-blue-200' : 'bg-gray-100 text-gray-800 hover:bg-gray-200'); 
                                    ?>">
                                        <?php echo ucfirst($habit['status']); ?>
                                    </button>
                                </form>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button onclick="viewHabitDetails(<?php echo $habit['id']; ?>)" 
                                            class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="View Details">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </button>
                                    <button onclick="deleteHabit(<?php echo $habit['id']; ?>, '<?php echo htmlspecialchars(addslashes($habit['name'])); ?>')" 
                                            class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition" title="Delete">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
            <div class="flex items-center justify-between">
                <p class="text-sm text-gray-600">
                    Showing <?php echo min($offset + 1, $totalHabits); ?> to <?php echo min($offset + $perPage, $totalHabits); ?> of <?php echo $totalHabits; ?> results
                </p>
                
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&frequency=<?php echo urlencode($frequency); ?>" 
                           class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition">Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&frequency=<?php echo urlencode($frequency); ?>" 
                           class="px-4 py-2 border rounded-lg transition <?php echo $i === $page ? 'bg-green-500 text-white border-green-500' : 'border-gray-300 hover:bg-gray-100'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&frequency=<?php echo urlencode($frequency); ?>" 
                           class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// View habit details in modal
async function viewHabitDetails(habitId) {
    try {
        const response = await fetch(`habits.php?action=get_habit_details&id=${habitId}`);
        const data = await response.json();
        
        if (!data.success) {
            await AppModal.alert({
                title: 'Error',
                message: data.message || 'Failed to load habit details',
                type: 'danger'
            });
            return;
        }
        
        const habit = data.habit;
        const completions = data.completions || [];
        
        // Build status badge
        const statusColors = {
            'active': 'bg-green-100 text-green-800',
            'inactive': 'bg-gray-100 text-gray-800',
            'completed': 'bg-blue-100 text-blue-800'
        };
        const statusClass = statusColors[habit.status] || 'bg-gray-100 text-gray-800';
        
        // Build completions HTML
        let completionsHTML = '';
        if (completions.length > 0) {
            completionsHTML = `
                <div class="space-y-3 max-h-96 overflow-y-auto">
                    ${completions.map(comp => {
                        const verificationColors = {
                            'pending': 'bg-yellow-100 text-yellow-800',
                            'approved': 'bg-green-100 text-green-800',
                            'rejected': 'bg-red-100 text-red-800'
                        };
                        const verificationClass = verificationColors[comp.verification_status] || 'bg-gray-100 text-gray-800';
                        
                        return `
                            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                <div class="flex items-start justify-between gap-3 mb-2">
                                    <div class="flex-1">
                                        <p class="text-sm text-gray-600">${new Date(comp.completion_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</p>
                                        <p class="text-xs text-gray-500 mt-1">${new Date(comp.completion_date).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}</p>
                                    </div>
                                    <span class="px-3 py-1 ${verificationClass} text-xs font-bold rounded-full capitalize">
                                        ${escapeHtml(comp.verification_status)}
                                    </div>
                                </div>
                                ${comp.description ? `<p class="text-sm text-gray-700 mt-2 italic">"${escapeHtml(comp.description)}"</p>` : ''}
                                ${comp.proof_path ? `
                                    <div class="mt-3">
                                        <img src="../public/${escapeHtml(comp.proof_path)}" alt="Proof" class="w-full max-w-xs rounded-lg border border-gray-300">
                                    </div>
                                ` : ''}
                                ${comp.verified_at ? `
                                    <p class="text-xs text-gray-500 mt-2">
                                        Verified: ${new Date(comp.verified_at).toLocaleDateString()}
                                    </p>
                                ` : ''}
                            </div>
                        `;
                    }).join('')}
                </div>
            `;
        } else {
            completionsHTML = '<p class="text-center text-gray-500 py-8">No completions recorded yet</p>';
        }
        
        // Calculate progress
        const progress = habit.target_days > 0 ? (habit.current_progress / habit.target_days) * 100 : 0;
        
        // Create modal content
        const modalContent = `
            <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4" onclick="closeHabitModal(event)">
                <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden" onclick="event.stopPropagation()">
                    <!-- Header -->
                    <div class="bg-gradient-to-r from-gray-800 to-green-400 text-white p-6">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <h2 class="text-2xl font-bold mb-2">${escapeHtml(habit.name)}</h2>
                                <div class="flex items-center gap-3 flex-wrap">
                                    <span class="px-3 py-1 bg-white/20 backdrop-blur-sm rounded-full text-sm font-semibold">
                                        ${escapeHtml(habit.user_name)}
                                    </span>
                                    <span class="px-3 py-1 bg-white/20 backdrop-blur-sm rounded-full text-sm font-semibold capitalize">
                                        ${escapeHtml(habit.frequency)}
                                    </span>
                                    <span class="text-sm opacity-90">
                                        Started: ${new Date(habit.created_at).toLocaleDateString()}
                                    </span>
                                </div>
                            </div>
                            <button onclick="closeHabitModal()" class="text-white hover:bg-white/20 rounded-lg p-2 transition">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Body -->
                    <div class="p-6 overflow-y-auto" style="max-height: calc(90vh - 200px);">
                        <!-- Stats -->
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                            <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-4 border border-purple-200">
                                <p class="text-sm text-purple-600 font-semibold mb-1">Status</p>
                                <span class="inline-block px-4 py-2 ${statusClass} text-sm font-bold rounded-full capitalize">
                                    ${escapeHtml(habit.status)}
                                </span>
                            </div>
                            
                            <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl p-4 border border-orange-200">
                                <p class="text-sm text-orange-600 font-semibold mb-1">Current Streak</p>
                                <div class="flex items-center gap-2">
                                    <svg class="w-6 h-6 text-orange-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                    </svg>
                                    <span class="text-2xl font-bold text-orange-900">${habit.streak || 0}</span>
                                    <span class="text-sm text-orange-600">days</span>
                                </div>
                            </div>
                            
                            <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-4 border border-blue-200">
                                <p class="text-sm text-blue-600 font-semibold mb-1">Progress</p>
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 bg-white rounded-full h-2">
                                        <div class="bg-gradient-to-r from-blue-400 to-blue-600 h-2 rounded-full" style="width: ${Math.min(100, progress)}%"></div>
                                    </div>
                                    <span class="text-sm font-bold text-blue-900">${Math.round(progress)}%</span>
                                </div>
                                <p class="text-xs text-blue-600 mt-1">${habit.current_progress || 0}/${habit.target_days} days</p>
                            </div>
                            
                            <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-4 border border-green-200">
                                <p class="text-sm text-green-600 font-semibold mb-1">Total Completions</p>
                                <p class="text-3xl font-bold text-green-900">${completions.length}</p>
                            </div>
                        </div>
                        
                        ${habit.description ? `
                            <div class="mb-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                                <h3 class="text-sm font-bold text-blue-900 mb-2">Description</h3>
                                <p class="text-sm text-blue-800">${escapeHtml(habit.description)}</p>
                            </div>
                        ` : ''}
                        
                        <!-- Completions -->
                        <div class="mb-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Completion History
                            </h3>
                            ${completionsHTML}
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex justify-end gap-3">
                        <button onclick="closeHabitModal()" class="px-6 py-2 bg-gray-200 text-gray-700 font-semibold rounded-xl hover:bg-gray-300 transition">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        // Add modal to page
        const modalDiv = document.createElement('div');
        modalDiv.id = 'habitDetailsModal';
        modalDiv.innerHTML = modalContent;
        document.body.appendChild(modalDiv);
        
        // Prevent body scroll
        document.body.style.overflow = 'hidden';
        
    } catch (error) {
        console.error('Error loading habit details:', error);
        await AppModal.alert({
            title: 'Error',
            message: 'Failed to load habit details. Please try again.',
            type: 'danger'
        });
    }
}

function closeHabitModal(event) {
    if (!event || event.target === event.currentTarget || event.currentTarget.tagName === 'BUTTON') {
        const modal = document.getElementById('habitDetailsModal');
        if (modal) {
            modal.remove();
            document.body.style.overflow = '';
        }
    }
}

// Delete habit with modal confirmation
async function deleteHabit(habitId, habitName) {
    const confirmed = await AppModal.confirm({
        title: 'Delete Habit',
        message: `Are you sure you want to delete the habit "<strong>${habitName}</strong>"? This action cannot be undone and will also delete all completion records.`,
        confirmText: 'Delete',
        cancelText: 'Cancel',
        type: 'danger'
    });

    if (confirmed) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="habit_id" value="${habitId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php require_once __DIR__ . '/include/footer.php'; ?>
