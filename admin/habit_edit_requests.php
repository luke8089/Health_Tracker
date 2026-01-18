<?php
require_once __DIR__ . '/../src/helpers/Database.php';
require_once __DIR__ . '/../src/helpers/Utils.php';
require_once __DIR__ . '/../src/helpers/Auth.php';

// Check authentication first
$auth = new Auth();
$auth->requireLogin();
$auth->requireRole('admin');
$currentUser = $auth->getCurrentUser();

$db = (new Database())->connect();

// Handle approval/rejection actions BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $requestId = $_POST['request_id'] ?? 0;
        $adminId = $currentUser['id'];
        
        switch ($_POST['action']) {
            case 'approve':
                $stmt = $db->prepare("
                    UPDATE habit_edit_requests 
                    SET status = 'approved', admin_id = ?, reviewed_at = NOW(), admin_notes = ?
                    WHERE id = ?
                ");
                $stmt->execute([$adminId, $_POST['notes'] ?? 'Approved', $requestId]);
                Utils::redirect('habit_edit_requests.php', 'Edit request approved successfully', 'success');
                break;
                
            case 'reject':
                $notes = $_POST['notes'] ?? 'Request rejected';
                $stmt = $db->prepare("
                    UPDATE habit_edit_requests 
                    SET status = 'rejected', admin_id = ?, reviewed_at = NOW(), admin_notes = ?
                    WHERE id = ?
                ");
                $stmt->execute([$adminId, $notes, $requestId]);
                Utils::redirect('habit_edit_requests.php', 'Edit request rejected', 'success');
                break;
        }
        exit;
    } catch (Exception $e) {
        Utils::redirect('habit_edit_requests.php', 'Error: ' . $e->getMessage(), 'error');
    }
}

$pageTitle = "Habit Edit Requests";
require_once __DIR__ . '/include/header.php';

// Get filters
$status = $_GET['status'] ?? 'pending';
$page = max(1, $_GET['page'] ?? 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get habit edit requests
$stmt = $db->prepare("
    SELECT her.*, h.name as habit_name, h.frequency, h.streak, u.name as user_name, u.email as user_email,
           a.name as admin_name
    FROM habit_edit_requests her
    JOIN habits h ON her.habit_id = h.id
    JOIN users u ON her.user_id = u.id
    LEFT JOIN users a ON her.admin_id = a.id
    WHERE her.status = ?
    ORDER BY her.requested_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute([$status]);
$requests = $stmt->fetchAll();

// Get counts
$stmt = $db->query("SELECT status, COUNT(*) as count FROM habit_edit_requests GROUP BY status");
$statusCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$totalRequests = $statusCounts[$status] ?? 0;
$totalPages = ceil($totalRequests / $perPage);
?>

<!-- Page Header -->
<div class="mb-8">
    <h1 class="text-3xl font-bold bg-gradient-to-r from-gray-800 to-green-400 bg-clip-text text-transparent mb-2">
        Habit Edit Requests
    </h1>
    <p class="text-gray-600">Review and approve user requests to edit their habits</p>
</div>

<!-- Status Tabs -->
<div class="bg-white rounded-xl shadow-md mb-6">
    <div class="flex border-b border-gray-200 overflow-x-auto">
        <a href="?status=pending" class="px-6 py-4 text-sm font-medium transition-colors <?php echo $status === 'pending' ? 'border-b-2 border-green-500 text-green-600' : 'text-gray-600 hover:text-gray-900'; ?>">
            Pending
            <?php if (isset($statusCounts['pending'])): ?>
                <span class="ml-2 px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800"><?php echo $statusCounts['pending']; ?></span>
            <?php endif; ?>
        </a>
        <a href="?status=approved" class="px-6 py-4 text-sm font-medium transition-colors <?php echo $status === 'approved' ? 'border-b-2 border-green-500 text-green-600' : 'text-gray-600 hover:text-gray-900'; ?>">
            Approved
            <?php if (isset($statusCounts['approved'])): ?>
                <span class="ml-2 px-2 py-1 text-xs rounded-full bg-green-100 text-green-800"><?php echo $statusCounts['approved']; ?></span>
            <?php endif; ?>
        </a>
        <a href="?status=rejected" class="px-6 py-4 text-sm font-medium transition-colors <?php echo $status === 'rejected' ? 'border-b-2 border-green-500 text-green-600' : 'text-gray-600 hover:text-gray-900'; ?>">
            Rejected
            <?php if (isset($statusCounts['rejected'])): ?>
                <span class="ml-2 px-2 py-1 text-xs rounded-full bg-red-100 text-red-800"><?php echo $statusCounts['rejected']; ?></span>
            <?php endif; ?>
        </a>
    </div>
</div>

<!-- Requests Grid -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <?php if (empty($requests)): ?>
        <div class="col-span-full bg-white rounded-xl shadow-md p-12 text-center">
            <svg class="w-20 h-20 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            <p class="text-xl font-semibold text-gray-900 mb-2">No <?php echo $status; ?> requests</p>
            <p class="text-gray-600">All caught up!</p>
        </div>
    <?php else: ?>
        <?php foreach ($requests as $request): ?>
            <div class="bg-white rounded-xl shadow-md overflow-hidden hover:shadow-xl transition-all">
                <!-- User Header -->
                <div class="bg-gradient-to-r from-gray-800 to-green-400 p-4 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-bold"><?php echo htmlspecialchars($request['user_name']); ?></h3>
                            <p class="text-sm text-white/80"><?php echo htmlspecialchars($request['user_email']); ?></p>
                        </div>
                        <span class="px-3 py-1 bg-white/20 rounded-full text-xs font-semibold capitalize">
                            <?php echo htmlspecialchars($request['frequency']); ?>
                        </span>
                    </div>
                </div>
                
                <!-- Request Details -->
                <div class="p-6 space-y-4">
                    <!-- Habit Name -->
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Habit to Edit</p>
                        <p class="font-bold text-lg text-gray-900"><?php echo htmlspecialchars($request['habit_name']); ?></p>
                        <div class="flex items-center gap-4 mt-1 text-sm text-gray-600">
                            <span>🔥 Streak: <?php echo $request['streak']; ?> days</span>
                            <span class="capitalize">📅 <?php echo $request['frequency']; ?></span>
                        </div>
                    </div>
                    
                    <!-- Reason -->
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Reason for Edit</p>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-gray-700 text-sm"><?php echo htmlspecialchars($request['reason']); ?></p>
                        </div>
                    </div>
                    
                    <!-- Request Time -->
                    <div class="pt-4 border-t border-gray-200 text-xs text-gray-500">
                        Requested <?php echo date('M j, Y \a\t g:i A', strtotime($request['requested_at'])); ?>
                    </div>
                    
                    <!-- Actions for Pending -->
                    <?php if ($status === 'pending'): ?>
                        <div class="flex gap-3 pt-4 border-t border-gray-200">
                            <button onclick="openApproveModal(<?php echo $request['id']; ?>, '<?php echo htmlspecialchars($request['habit_name'], ENT_QUOTES); ?>')" class="flex-1 px-4 py-2 bg-green-50 text-green-600 rounded-lg hover:bg-green-100 transition font-medium">
                                ✓ Approve
                            </button>
                            
                            <button onclick="openRejectModal(<?php echo $request['id']; ?>, '<?php echo htmlspecialchars($request['habit_name'], ENT_QUOTES); ?>')" class="flex-1 px-4 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition font-medium">
                                ✗ Reject
                            </button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Status Display for Approved/Rejected -->
                    <?php if ($status !== 'pending'): ?>
                        <div class="pt-4 border-t border-gray-200">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="px-3 py-1 text-xs font-semibold rounded-full <?php echo $status === 'approved' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo ucfirst($status); ?>
                                </span>
                            </div>
                            <?php if ($request['admin_notes']): ?>
                                <p class="text-sm text-gray-600 mt-2"><strong>Admin Notes:</strong> <?php echo htmlspecialchars($request['admin_notes']); ?></p>
                            <?php endif; ?>
                            <p class="text-xs text-gray-500 mt-2">
                                Reviewed by <?php echo htmlspecialchars($request['admin_name'] ?? 'Admin'); ?> on <?php echo date('M j, Y \a\t g:i A', strtotime($request['reviewed_at'])); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
    <div class="bg-white rounded-xl shadow-md px-6 py-4 mt-6">
        <div class="flex items-center justify-between">
            <p class="text-sm text-gray-600">
                Showing <?php echo min($offset + 1, $totalRequests); ?> to <?php echo min($offset + $perPage, $totalRequests); ?> of <?php echo $totalRequests; ?> results
            </p>
            
            <div class="flex gap-2">
                <?php if ($page > 1): ?>
                    <a href="?status=<?php echo $status; ?>&page=<?php echo $page - 1; ?>" 
                       class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition">Previous</a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <a href="?status=<?php echo $status; ?>&page=<?php echo $i; ?>" 
                       class="px-4 py-2 border rounded-lg transition <?php echo $i === $page ? 'bg-green-500 text-white border-green-500' : 'border-gray-300 hover:bg-gray-100'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?status=<?php echo $status; ?>&page=<?php echo $page + 1; ?>" 
                       class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition">Next</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
[x-cloak] { display: none !important; }

.relative {
    position: relative;
}

.absolute {
    position: absolute;
}

.z-10 {
    z-index: 10;
}
</style>

<!-- Approve Modal -->
<div id="approveModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background: rgba(0,0,0,0.5);" onclick="closeModalOnBackdrop(event, 'approveModal')">
    <div class="bg-white rounded-2xl p-8 max-w-md w-full shadow-2xl" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-2xl font-bold bg-gradient-to-r from-gray-800 to-green-400 bg-clip-text text-transparent">
                Approve Edit Request
            </h3>
            <button onclick="closeApproveModal()" class="p-2 hover:bg-gray-100 rounded-lg transition-all duration-300">
                <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <form method="POST" class="space-y-5">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="request_id" id="approveRequestId">
            
            <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-4">
                <p class="font-semibold text-gray-900 mb-2">Habit: <span id="approveHabitName" class="text-green-600"></span></p>
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Approval Note (Optional)</label>
                <textarea name="notes" rows="3" 
                          class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:border-green-500 focus:ring-2 focus:ring-green-200 transition-all duration-300"
                          placeholder="Add any notes for the user..."></textarea>
            </div>
            
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    <div class="text-sm text-blue-700">
                        <p class="font-semibold mb-1">Note</p>
                        <p class="text-xs">The user will be able to edit this habit once. After editing, they'll need to request permission again for future edits.</p>
                    </div>
                </div>
            </div>

            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeApproveModal()" class="flex-1 px-6 py-3 rounded-xl font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 transition-all duration-300">
                    Cancel
                </button>
                <button type="submit" class="flex-1 px-6 py-3 rounded-xl font-semibold text-white bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105">
                    ✓ Confirm Approval
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background: rgba(0,0,0,0.5);" onclick="closeModalOnBackdrop(event, 'rejectModal')">
    <div class="bg-white rounded-2xl p-8 max-w-md w-full shadow-2xl" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-2xl font-bold bg-gradient-to-r from-gray-800 to-red-400 bg-clip-text text-transparent">
                Reject Edit Request
            </h3>
            <button onclick="closeRejectModal()" class="p-2 hover:bg-gray-100 rounded-lg transition-all duration-300">
                <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <form method="POST" class="space-y-5">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="request_id" id="rejectRequestId">
            
            <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-4">
                <p class="font-semibold text-gray-900 mb-2">Habit: <span id="rejectHabitName" class="text-red-600"></span></p>
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Rejection Reason <span class="text-red-500">*</span></label>
                <textarea name="notes" rows="4" required
                          class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition-all duration-300"
                          placeholder="Explain why this request is being rejected..."></textarea>
            </div>
            
            <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-yellow-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <div class="text-sm text-yellow-700">
                        <p class="font-semibold mb-1">Important</p>
                        <p class="text-xs">The user will be notified with your rejection reason. Please be clear and constructive.</p>
                    </div>
                </div>
            </div>

            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeRejectModal()" class="flex-1 px-6 py-3 rounded-xl font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 transition-all duration-300">
                    Cancel
                </button>
                <button type="submit" class="flex-1 px-6 py-3 rounded-xl font-semibold text-white bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105">
                    ✗ Confirm Reject
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openApproveModal(requestId, habitName) {
    document.getElementById('approveRequestId').value = requestId;
    document.getElementById('approveHabitName').textContent = habitName;
    document.getElementById('approveModal').classList.remove('hidden');
    document.getElementById('approveModal').classList.add('flex');
}

function closeApproveModal() {
    document.getElementById('approveModal').classList.add('hidden');
    document.getElementById('approveModal').classList.remove('flex');
}

function openRejectModal(requestId, habitName) {
    document.getElementById('rejectRequestId').value = requestId;
    document.getElementById('rejectHabitName').textContent = habitName;
    document.getElementById('rejectModal').classList.remove('hidden');
    document.getElementById('rejectModal').classList.add('flex');
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.add('hidden');
    document.getElementById('rejectModal').classList.remove('flex');
}

function closeModalOnBackdrop(event, modalId) {
    if (event.target.id === modalId) {
        if (modalId === 'approveModal') {
            closeApproveModal();
        } else if (modalId === 'rejectModal') {
            closeRejectModal();
        }
    }
}

// Close modals on ESC key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeApproveModal();
        closeRejectModal();
    }
});
</script>

<?php require_once __DIR__ . '/include/footer.php'; ?>
