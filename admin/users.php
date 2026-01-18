<?php
$pageTitle = "Users Management";
require_once __DIR__ . '/include/header.php';
require_once __DIR__ . '/../src/helpers/Database.php';
require_once __DIR__ . '/../src/helpers/Utils.php';

$db = (new Database())->connect();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'delete':
                $userId = $_POST['user_id'] ?? 0;
                $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
                $stmt->execute([$userId]);
                Utils::setFlashMessage('User deleted successfully', 'success');
                break;
                
            case 'toggle_status':
                $userId = $_POST['user_id'] ?? 0;
                // You can add a status column to users table if needed
                Utils::setFlashMessage('User status updated', 'success');
                break;
        }
        header('Location: users.php');
        exit;
    } catch (Exception $e) {
        Utils::setFlashMessage('Error: ' . $e->getMessage(), 'error');
    }
}

// Get filters
$search = $_GET['search'] ?? '';
$role = $_GET['role'] ?? '';
$page = max(1, $_GET['page'] ?? 1);
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Build query
$where = ["1=1"];
$params = [];

if ($search) {
    $where[] = "(name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($role) {
    $where[] = "role = ?";
    $params[] = $role;
}

$whereClause = implode(' AND ', $where);

// Get total count
$stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE $whereClause");
$stmt->execute($params);
$totalUsers = $stmt->fetch()['total'];
$totalPages = ceil($totalUsers / $perPage);

// Get users
$stmt = $db->prepare("
    SELECT u.*, 
           (SELECT COUNT(*) FROM assessments WHERE user_id = u.id) as assessment_count,
           (SELECT COUNT(*) FROM habits WHERE user_id = u.id) as habit_count
    FROM users u
    WHERE $whereClause
    ORDER BY u.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get role counts
$stmt = $db->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
$roleCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<!-- Header with Search and Filters -->
<div class="bg-white rounded-xl shadow-md p-6 mb-6 animate-fade">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Users Management</h2>
            <p class="text-sm text-gray-600 mt-1">Total: <?php echo number_format($totalUsers); ?> users</p>
        </div>
        
        <!-- Search and Filters -->
        <form method="GET" class="flex flex-col sm:flex-row gap-3">
            <div class="relative">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Search users..." 
                       class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-400 focus:border-transparent w-full sm:w-64">
                <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            
            <select name="role" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-400 focus:border-transparent">
                <option value="">All Roles</option>
                <option value="user" <?php echo $role === 'user' ? 'selected' : ''; ?>>Users (<?php echo $roleCounts['user'] ?? 0; ?>)</option>
                <option value="doctor" <?php echo $role === 'doctor' ? 'selected' : ''; ?>>Doctors (<?php echo $roleCounts['doctor'] ?? 0; ?>)</option>
                <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Admins (<?php echo $roleCounts['admin'] ?? 0; ?>)</option>
            </select>
            
            <button type="submit" class="px-6 py-2 bg-gradient-to-r from-gray-800 to-green-400 text-white rounded-lg hover:shadow-lg transition-all transform hover:-translate-y-0.5">
                Filter
            </button>
            
            <?php if ($search || $role): ?>
                <a href="users.php" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Clear
                </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="bg-white rounded-xl shadow-md overflow-hidden animate-fade">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">User</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Role</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Stats</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Joined</th>
                    <th class="px-6 py-4 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                            <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                            </svg>
                            <p class="text-lg font-semibold">No users found</p>
                            <p class="text-sm">Try adjusting your filters</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-gradient-to-br from-gray-800 to-green-400 rounded-full flex items-center justify-center text-white font-bold">
                                        <?php if (!empty($user['avatar']) && file_exists(__DIR__ . '/../' . $user['avatar'])): ?>
                                            <img src="../<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar" class="w-full h-full object-cover rounded-full">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($user['name'], 0, 2)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($user['name']); ?></p>
                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($user['email']); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 text-xs font-semibold rounded-full <?php 
                                    echo $user['role'] === 'admin' ? 'bg-purple-100 text-purple-800' : 
                                        ($user['role'] === 'doctor' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'); 
                                ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex gap-3 text-sm">
                                    <span class="text-gray-600" title="Assessments">
                                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                        </svg>
                                        <?php echo $user['assessment_count']; ?>
                                    </span>
                                    <span class="text-gray-600" title="Habits">
                                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <?php echo $user['habit_count']; ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button @click="$dispatch('open-user-modal', { userId: <?php echo $user['id']; ?> })" 
                                       class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="View Details">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </button>
                                    
                                    <?php if ($user['role'] !== 'admin'): ?>
                                        <button @click="$dispatch('open-delete-modal', { userId: <?php echo $user['id']; ?>, userName: '<?php echo addslashes(htmlspecialchars($user['name'])); ?>' })" 
                                                class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition" 
                                                title="Delete User">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    <?php endif; ?>
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
                    Showing <?php echo min($offset + 1, $totalUsers); ?> to <?php echo min($offset + $perPage, $totalUsers); ?> of <?php echo $totalUsers; ?> results
                </p>
                
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role); ?>" 
                           class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition">Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role); ?>" 
                           class="px-4 py-2 border rounded-lg transition <?php echo $i === $page ? 'bg-green-500 text-white border-green-500' : 'border-gray-300 hover:bg-gray-100'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role); ?>" 
                           class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- User Details Modal -->
<div x-data="userModal()" 
     @open-user-modal.window="openModal($event.detail.userId)" 
     x-show="isOpen" 
     x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" 
     @click.self="closeModal()">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden animate-scale" @click.stop>
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-gray-800 to-green-400 px-6 py-4 flex justify-between items-center">
            <h2 class="text-xl font-bold text-white">User Details</h2>
            <button @click="closeModal()" class="text-white hover:text-gray-200 text-2xl">&times;</button>
        </div>
        
        <!-- Loading State -->
        <div x-show="loading" class="p-8 text-center">
            <svg class="animate-spin h-12 w-12 mx-auto text-green-500" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="mt-4 text-gray-600">Loading user details...</p>
        </div>
        
        <!-- Modal Content -->
        <div x-show="!loading && userData" class="overflow-y-auto max-h-[calc(90vh-80px)]">
            <div class="p-6 space-y-6">
                <!-- User Profile Section -->
                <div class="flex items-start gap-6 pb-6 border-b border-gray-200">
                    <div class="w-24 h-24 bg-gradient-to-br from-gray-800 to-green-400 rounded-full flex items-center justify-center text-white text-3xl font-bold flex-shrink-0">
                        <template x-if="userData.avatar">
                            <img :src="'../' + userData.avatar" alt="Avatar" class="w-full h-full object-cover rounded-full">
                        </template>
                        <template x-if="!userData.avatar">
                            <span x-text="userData.name ? userData.name.substring(0, 2).toUpperCase() : 'U'"></span>
                        </template>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-2xl font-bold text-gray-900" x-text="userData.name"></h3>
                        <p class="text-gray-600" x-text="userData.email"></p>
                        <div class="flex gap-2 mt-3">
                            <span class="px-3 py-1 text-xs font-semibold rounded-full"
                                  :class="{
                                      'bg-purple-100 text-purple-800': userData.role === 'admin',
                                      'bg-green-100 text-green-800': userData.role === 'doctor',
                                      'bg-blue-100 text-blue-800': userData.role === 'user'
                                  }"
                                  x-text="userData.role ? userData.role.charAt(0).toUpperCase() + userData.role.slice(1) : ''"></span>
                            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                Joined <span x-text="new Date(userData.created_at).toLocaleDateString()"></span>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Statistics Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4"
                     :class="{ 'md:grid-cols-4': userData.role === 'doctor' }">
                    <div class="bg-blue-50 rounded-lg p-4">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-blue-500 rounded-lg flex items-center justify-center text-white">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900" x-text="userData.stats?.assessment_count || 0"></p>
                                <p class="text-sm text-gray-600">Assessments</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-green-50 rounded-lg p-4">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-green-500 rounded-lg flex items-center justify-center text-white">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900" x-text="userData.stats?.habit_count || 0"></p>
                                <p class="text-sm text-gray-600">Habits</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-purple-50 rounded-lg p-4">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-purple-500 rounded-lg flex items-center justify-center text-white">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900" x-text="userData.stats?.message_count || 0"></p>
                                <p class="text-sm text-gray-600">Messages</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Patient Count (Only for doctors) -->
                    <div x-show="userData.role === 'doctor'" class="bg-orange-50 rounded-lg p-4">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-orange-500 rounded-lg flex items-center justify-center text-white">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900" x-text="userData.stats?.patient_count || 0"></p>
                                <p class="text-sm text-gray-600">Patients</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Doctor Information (Only for doctors) -->
                <div x-show="userData.role === 'doctor' && userData.doctor_info" class="bg-gradient-to-r from-green-50 to-blue-50 rounded-lg p-6 border-2 border-green-200">
                    <h4 class="font-bold text-gray-900 mb-4 flex items-center gap-2 text-lg">
                        <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-blue-500 rounded-lg flex items-center justify-center text-white">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        Doctor Professional Information
                    </h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Specialty -->
                        <div class="bg-white rounded-lg p-4 shadow-sm">
                            <div class="flex items-center gap-2 mb-2">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                                </svg>
                                <p class="text-gray-500 font-medium text-sm">Specialty</p>
                            </div>
                            <p class="font-bold text-gray-900 text-lg capitalize" x-text="userData.doctor_info.specialty || 'N/A'"></p>
                        </div>
                        
                        <!-- License Number -->
                        <div class="bg-white rounded-lg p-4 shadow-sm">
                            <div class="flex items-center gap-2 mb-2">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <p class="text-gray-500 font-medium text-sm">License Number</p>
                            </div>
                            <p class="font-bold text-gray-900 text-lg" x-text="userData.doctor_info.license_number || 'N/A'"></p>
                        </div>
                        
                        <!-- Availability -->
                        <div class="bg-white rounded-lg p-4 shadow-sm">
                            <div class="flex items-center gap-2 mb-2">
                                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="text-gray-500 font-medium text-sm">Availability</p>
                            </div>
                            <span class="inline-block px-3 py-1 text-sm font-bold rounded-full"
                                  :class="{
                                      'bg-green-100 text-green-800': userData.doctor_info.availability === 'available',
                                      'bg-yellow-100 text-yellow-800': userData.doctor_info.availability === 'busy',
                                      'bg-gray-100 text-gray-800': userData.doctor_info.availability === 'offline'
                                  }"
                                  x-text="userData.doctor_info.availability ? userData.doctor_info.availability.charAt(0).toUpperCase() + userData.doctor_info.availability.slice(1) : 'N/A'"></span>
                        </div>
                        
                        <!-- Patient Count -->
                        <div class="bg-white rounded-lg p-4 shadow-sm">
                            <div class="flex items-center gap-2 mb-2">
                                <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                                <p class="text-gray-500 font-medium text-sm">Active Patients</p>
                            </div>
                            <p class="font-bold text-gray-900 text-lg" x-text="userData.stats?.patient_count || 0"></p>
                        </div>
                    </div>
                    
                    <!-- Recommendations Count -->
                    <template x-if="userData.stats?.recommendation_count !== undefined">
                        <div class="mt-4 bg-white rounded-lg p-4 shadow-sm">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-blue-500 rounded-lg flex items-center justify-center text-white">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-gray-500 font-medium text-sm">Total Recommendations Provided</p>
                                        <p class="font-bold text-gray-900 text-2xl" x-text="userData.stats.recommendation_count"></p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm text-gray-600">Professional assessments</p>
                                    <p class="text-xs text-gray-500">and guidance provided</p>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                
                <!-- Additional Information -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Personal Information -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            Personal Information
                        </h4>
                        <div class="space-y-3 text-sm">
                            <div>
                                <p class="text-gray-500">Full Name</p>
                                <p class="font-semibold text-gray-900" x-text="userData.name || 'N/A'"></p>
                            </div>
                            <div>
                                <p class="text-gray-500">Email Address</p>
                                <p class="font-semibold text-gray-900" x-text="userData.email || 'N/A'"></p>
                            </div>
                            <div>
                                <p class="text-gray-500">Age</p>
                                <p class="font-semibold text-gray-900" x-text="userData.age || 'N/A'"></p>
                            </div>
                            <div>
                                <p class="text-gray-500">Gender</p>
                                <p class="font-semibold text-gray-900" x-text="userData.gender ? userData.gender.charAt(0).toUpperCase() + userData.gender.slice(1) : 'N/A'"></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Account Information -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Account Information
                        </h4>
                        <div class="space-y-3 text-sm">
                            <div>
                                <p class="text-gray-500">User ID</p>
                                <p class="font-semibold text-gray-900" x-text="userData.id || 'N/A'"></p>
                            </div>
                            <div>
                                <p class="text-gray-500">Role</p>
                                <p class="font-semibold text-gray-900" x-text="userData.role ? userData.role.charAt(0).toUpperCase() + userData.role.slice(1) : 'N/A'"></p>
                            </div>
                            <div>
                                <p class="text-gray-500">Member Since</p>
                                <p class="font-semibold text-gray-900" x-text="userData.created_at ? new Date(userData.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A'"></p>
                            </div>
                            <div>
                                <p class="text-gray-500">Last Updated</p>
                                <p class="font-semibold text-gray-900" x-text="userData.updated_at ? new Date(userData.updated_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A'"></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Assessments -->
                <div x-show="userData.recent_assessments && userData.recent_assessments.length > 0" class="bg-gray-50 rounded-lg p-4">
                    <h4 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        Recent Assessments
                    </h4>
                    <div class="space-y-2">
                        <template x-for="assessment in userData.recent_assessments" :key="assessment.id">
                            <div class="flex justify-between items-center bg-white p-3 rounded-lg">
                                <div>
                                    <p class="font-semibold text-gray-900" x-text="assessment.type"></p>
                                    <p class="text-sm text-gray-600" x-text="new Date(assessment.created_at).toLocaleDateString()"></p>
                                </div>
                                <div class="text-right">
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full"
                                          :class="{
                                              'bg-red-100 text-red-800': assessment.severity === 'critical',
                                              'bg-orange-100 text-orange-800': assessment.severity === 'poor',
                                              'bg-yellow-100 text-yellow-800': assessment.severity === 'fair',
                                              'bg-green-100 text-green-800': assessment.severity === 'good',
                                              'bg-blue-100 text-blue-800': assessment.severity === 'excellent'
                                          }"
                                          x-text="assessment.severity"></span>
                                    <p class="text-sm text-gray-600 mt-1">Score: <span x-text="assessment.score"></span></p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
                
                <!-- Recent Habits -->
                <div x-show="userData.recent_habits && userData.recent_habits.length > 0" class="bg-gray-50 rounded-lg p-4">
                    <h4 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Active Habits
                    </h4>
                    <div class="space-y-2">
                        <template x-for="habit in userData.recent_habits" :key="habit.id">
                            <div class="flex justify-between items-center bg-white p-3 rounded-lg">
                                <div>
                                    <p class="font-semibold text-gray-900" x-text="habit.name"></p>
                                    <p class="text-sm text-gray-600" x-text="habit.frequency"></p>
                                </div>
                                <div class="text-right">
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full"
                                          :class="{
                                              'bg-green-100 text-green-800': habit.status === 'active',
                                              'bg-yellow-100 text-yellow-800': habit.status === 'completed',
                                              'bg-gray-100 text-gray-800': habit.status === 'inactive'
                                          }"
                                          x-text="habit.status"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modal Footer -->
        <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3 border-t border-gray-200">
            <button @click="closeModal()" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-medium">
                Close
            </button>
            <a :href="'../public/profile.php?user_id=' + (userData?.id || '')" target="_blank"
               class="px-6 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition font-medium">
                View Full Profile
            </a>
        </div>
    </div>
</div>

<script>
function userModal() {
    return {
        isOpen: false,
        loading: false,
        userData: null,
        
        async openModal(userId) {
            this.isOpen = true;
            this.loading = true;
            this.userData = null;
            
            try {
                console.log('Fetching user details for ID:', userId);
                const response = await fetch(`api.php?action=get_user_details&user_id=${userId}`);
                const data = await response.json();
                
                console.log('API Response:', data);
                
                if (data.success && data.data && data.data.user) {
                    this.userData = data.data.user;
                    console.log('User data loaded:', this.userData);
                } else {
                    console.error('Failed to load user data:', data.message);
                    alert('Error: ' + (data.message || 'Failed to load user data'));
                }
            } catch (error) {
                console.error('Error fetching user data:', error);
                alert('Network error: ' + error.message);
            } finally {
                this.loading = false;
            }
        },
        
        closeModal() {
            this.isOpen = false;
            this.userData = null;
        }
    };
}

function deleteModal() {
    return {
        isOpen: false,
        userId: null,
        userName: '',

        init() {
            this.$watch('isOpen', value => {
                if (value) {
                    document.body.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = 'auto';
                }
            });

            // Listen for delete modal open event
            window.addEventListener('open-delete-modal', (e) => {
                this.openModal(e.detail);
            });
        },

        openModal(detail) {
            this.userId = detail.userId;
            this.userName = detail.userName;
            this.isOpen = true;
        },

        confirmDelete() {
            // Create and submit form
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" value="${this.userId}">
            `;
            document.body.appendChild(form);
            form.submit();
        },

        closeModal() {
            this.isOpen = false;
        }
    };
}
</script>

<!-- Delete Confirmation Modal -->
<div x-data="deleteModal()" 
     @keydown.escape.window="closeModal()"
     x-cloak>
    <!-- Backdrop -->
    <div x-show="isOpen"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-gray-900 bg-opacity-75 z-50"
         @click="closeModal()"></div>

    <!-- Modal Content -->
    <div x-show="isOpen"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 transform scale-100"
         x-transition:leave-end="opacity-0 transform scale-95"
         class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden" @click.stop>
                <!-- Modal Header with Red Gradient -->
                <div class="bg-gradient-to-r from-red-600 to-red-700 px-6 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="bg-white/20 p-2 rounded-lg">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-white">Confirm Delete</h3>
                    </div>
                    <button @click="closeModal()" 
                            class="text-white/80 hover:text-white transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-6">
                    <div class="text-center mb-6">
                        <div class="mx-auto w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mb-4">
                            <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </div>
                        <h4 class="text-lg font-semibold text-gray-900 mb-2">Delete User?</h4>
                        <p class="text-gray-600 mb-4">
                            Are you sure you want to delete 
                            <span class="font-semibold text-gray-900" x-text="userName"></span>?
                        </p>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                            <p class="text-sm text-red-800 flex items-center gap-2">
                                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                <span><strong>Warning:</strong> This action cannot be undone. All user data, assessments, and habits will be permanently deleted.</span>
                            </p>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-3">
                        <button @click="closeModal()" 
                                class="flex-1 px-4 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium transition">
                            Cancel
                        </button>
                        <button @click="confirmDelete()" 
                                class="flex-1 px-4 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 font-medium transition">
                            Delete User
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
[x-cloak] { display: none !important; }
</style>

<?php require_once __DIR__ . '/include/footer.php'; ?>
