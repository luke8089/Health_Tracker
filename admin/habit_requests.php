<?php
$pageTitle = "Habit Verification Requests";
require_once __DIR__ . '/include/header.php';
require_once __DIR__ . '/../src/helpers/Database.php';
require_once __DIR__ . '/../src/helpers/Utils.php';

$db = (new Database())->connect();

// Handle verification actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $completionId = $_POST['completion_id'] ?? 0;
        $doctorId = $currentUser['id'];
        
        switch ($_POST['action']) {
            case 'approve':
                $points = $_POST['points'] ?? 10;
                $stmt = $db->prepare("UPDATE habit_completions SET verification_status = 'approved', verified_by = ?, verified_at = NOW(), points_awarded = ? WHERE id = ?");
                $stmt->execute([$doctorId, $points, $completionId]);
                
                // Award points to user
                $stmt = $db->prepare("SELECT user_id FROM habit_completions WHERE id = ?");
                $stmt->execute([$completionId]);
                $userId = $stmt->fetchColumn();
                
                $stmt = $db->prepare("INSERT INTO rewards (user_id, points, tier) VALUES (?, ?, 'bronze')");
                $stmt->execute([$userId, $points]);
                
                Utils::setFlashMessage('Habit approved successfully', 'success');
                break;
                
            case 'reject':
                $notes = $_POST['notes'] ?? '';
                $stmt = $db->prepare("UPDATE habit_completions SET verification_status = 'rejected', verified_by = ?, verified_at = NOW(), verification_notes = ? WHERE id = ?");
                $stmt->execute([$doctorId, $notes, $completionId]);
                Utils::setFlashMessage('Habit rejected', 'success');
                break;
        }
        header('Location: habit_requests.php');
        exit;
    } catch (Exception $e) {
        Utils::setFlashMessage('Error: ' . $e->getMessage(), 'error');
    }
}

// Get filters
$status = $_GET['status'] ?? 'pending';
$page = max(1, $_GET['page'] ?? 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get habit completion requests
$stmt = $db->prepare("
    SELECT hc.*, h.name as habit_name, h.frequency, u.name as user_name, u.email as user_email
    FROM habit_completions hc
    JOIN habits h ON hc.habit_id = h.id
    JOIN users u ON hc.user_id = u.id
    WHERE hc.verification_status = ?
    ORDER BY hc.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute([$status]);
$requests = $stmt->fetchAll();

// Get counts
$stmt = $db->query("SELECT verification_status, COUNT(*) as count FROM habit_completions GROUP BY verification_status");
$statusCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$totalRequests = $statusCounts[$status] ?? 0;
$totalPages = ceil($totalRequests / $perPage);
?>

<!-- Status Tabs -->
<div class="bg-white rounded-xl shadow-md mb-6 animate-fade">
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
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-xl font-semibold text-gray-900 mb-2">No <?php echo $status; ?> requests</p>
            <p class="text-gray-600">All caught up!</p>
        </div>
    <?php else: ?>
        <?php foreach ($requests as $request): ?>
            <div class="bg-white rounded-xl shadow-md overflow-hidden hover:shadow-xl transition-all animate-fade">
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
                        <p class="text-xs text-gray-500 mb-1">Habit</p>
                        <p class="font-bold text-lg text-gray-900"><?php echo htmlspecialchars($request['habit_name']); ?></p>
                    </div>
                    
                    <!-- Completion Date -->
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Completion Date</p>
                        <p class="text-gray-900"><?php echo date('M j, Y', strtotime($request['completion_date'])); ?></p>
                    </div>
                    
                    <!-- Description -->
                    <?php if ($request['description']): ?>
                        <div>
                            <p class="text-xs text-gray-500 mb-1">Description</p>
                            <p class="text-gray-700 text-sm"><?php echo htmlspecialchars($request['description']); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Proof Image -->
                    <?php if ($request['proof_type'] === 'image' && $request['proof_path']): ?>
                        <div>
                            <p class="text-xs text-gray-500 mb-2">Proof Image</p>
                            <img src="../public/<?php echo htmlspecialchars($request['proof_path']); ?>" 
                                 alt="Proof" 
                                 class="w-full h-48 object-cover rounded-lg border-2 border-gray-200 cursor-pointer hover:border-green-400 transition"
                                 onclick="window.open(this.src, '_blank')"
                                 onerror="this.src='../public/assets/images/no-image.png'; this.onerror=null;">
                        </div>
                    <?php endif; ?>
                    
                    <!-- Submitted Time -->
                    <div class="pt-4 border-t border-gray-200 text-xs text-gray-500">
                        Submitted <?php echo date('M j, Y \a\t g:i A', strtotime($request['created_at'])); ?>
                    </div>
                    
                    <!-- Actions for Pending -->
                    <?php if ($status === 'pending'): ?>
                        <div class="flex gap-3 pt-4 border-t border-gray-200" x-data="{ showReject: false }">
                            <form method="POST" class="flex-1">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="completion_id" value="<?php echo $request['id']; ?>">
                                <input type="hidden" name="points" value="10">
                                <button type="submit" class="w-full px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition font-medium">
                                    ✓ Approve
                                </button>
                            </form>
                            
                            <button @click="showReject = !showReject" class="flex-1 px-4 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition font-medium">
                                ✗ Reject
                            </button>
                            
                            <div x-show="showReject" x-cloak class="absolute inset-0 bg-white p-6 rounded-xl">
                                <form method="POST" class="space-y-4">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="completion_id" value="<?php echo $request['id']; ?>">
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Rejection Reason</label>
                                        <textarea name="notes" rows="4" required 
                                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-400 focus:border-transparent"
                                                  placeholder="Explain why this is being rejected..."></textarea>
                                    </div>
                                    
                                    <div class="flex gap-3">
                                        <button type="submit" class="flex-1 px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition">
                                            Confirm Reject
                                        </button>
                                        <button type="button" @click="showReject = false" class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Status Display for Approved/Rejected -->
                    <?php if ($status !== 'pending'): ?>
                        <div class="pt-4 border-t border-gray-200">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="px-3 py-1 text-xs font-semibold rounded-full <?php echo $status === 'approved' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo ucfirst($status); ?>
                                </span>
                                <?php if ($status === 'approved' && $request['points_awarded']): ?>
                                    <span class="text-sm font-semibold text-gray-700">+<?php echo $request['points_awarded']; ?> points</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($request['verification_notes']): ?>
                                <p class="text-sm text-gray-600 mt-2"><strong>Notes:</strong> <?php echo htmlspecialchars($request['verification_notes']); ?></p>
                            <?php endif; ?>
                            <p class="text-xs text-gray-500 mt-2">
                                Verified on <?php echo date('M j, Y \a\t g:i A', strtotime($request['verified_at'])); ?>
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

<?php require_once __DIR__ . '/include/footer.php'; ?>
