<?php
require_once __DIR__ . '/../src/helpers/Database.php';
require_once __DIR__ . '/../src/helpers/Utils.php';

$db = (new Database())->connect();

// Handle AJAX actions BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'delete':
                $doctorId = $_POST['doctor_id'] ?? 0;
                $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role = 'doctor'");
                $stmt->execute([$doctorId]);
                $stmt = $db->prepare("DELETE FROM doctors WHERE id = ?");
                $stmt->execute([$doctorId]);
                Utils::setFlashMessage('Doctor deleted successfully', 'success');
                break;
                
            case 'toggle_status':
                $doctorId = $_POST['doctor_id'] ?? 0;
                $status = $_POST['status'] ?? 'offline';
                $newStatus = $status === 'online' ? 'offline' : 'online';
                $stmt = $db->prepare("UPDATE doctors SET availability = ? WHERE id = ?");
                $stmt->execute([$newStatus, $doctorId]);
                Utils::setFlashMessage('Doctor status updated', 'success');
                break;
                
            case 'update_doctor':
                // Set JSON header
                header('Content-Type: application/json');
                
                $doctorId = intval($_POST['doctor_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $specialty = trim($_POST['specialty'] ?? '');
                $licenseNumber = trim($_POST['license_number'] ?? '');
                $availability = $_POST['availability'] ?? 'offline';
                
                if (!$doctorId || !$name || !$email || !$specialty || !$licenseNumber) {
                    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                    exit;
                }
                
                // Validate email
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
                    exit;
                }
                
                // Validate availability
                $validAvailability = ['available', 'busy', 'offline'];
                if (!in_array($availability, $validAvailability)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid availability status']);
                    exit;
                }
                
                // Check if email is already used by another user
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $doctorId]);
                if ($stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Email address is already in use']);
                    exit;
                }
                
                // Begin transaction
                $db->beginTransaction();
                
                try {
                    // Update users table
                    $stmt = $db->prepare("
                        UPDATE users 
                        SET name = ?, email = ?, phone = ?, updated_at = CURRENT_TIMESTAMP 
                        WHERE id = ? AND role = 'doctor'
                    ");
                    $stmt->execute([$name, $email, $phone, $doctorId]);
                    
                    // Update doctors table
                    $stmt = $db->prepare("
                        UPDATE doctors 
                        SET specialty = ?, license_number = ?, availability = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$specialty, $licenseNumber, $availability, $doctorId]);
                    
                    // Commit transaction
                    $db->commit();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Doctor profile updated successfully',
                        'data' => [
                            'doctor_id' => $doctorId,
                            'name' => $name,
                            'email' => $email
                        ]
                    ]);
                    exit;
                    
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $db->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                    exit;
                }
                break;
        }
        // Only redirect for non-AJAX requests
        header('Location: doctors.php');
        exit;
    } catch (Exception $e) {
        // Check if it's an AJAX request
        if (isset($_POST['action']) && $_POST['action'] === 'update_doctor') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
        Utils::setFlashMessage('Error: ' . $e->getMessage(), 'error');
        header('Location: doctors.php');
        exit;
    }
}

// Include header after POST handling to prevent "headers already sent" error
$pageTitle = "Doctors Management";
require_once __DIR__ . '/include/header.php';

// Get filters
$search = $_GET['search'] ?? '';
$specialty = $_GET['specialty'] ?? '';
$page = max(1, $_GET['page'] ?? 1);
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Build query
$where = ["u.role = 'doctor'"];
$params = [];

if ($search) {
    $where[] = "(u.name LIKE ? OR u.email LIKE ? OR d.license_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($specialty) {
    $where[] = "d.specialty = ?";
    $params[] = $specialty;
}

$whereClause = implode(' AND ', $where);

// Get total count
$stmt = $db->prepare("SELECT COUNT(*) as total FROM users u LEFT JOIN doctors d ON u.id = d.id WHERE $whereClause");
$stmt->execute($params);
$totalDoctors = $stmt->fetch()['total'];
$totalPages = ceil($totalDoctors / $perPage);

// Get doctors
$stmt = $db->prepare("
    SELECT u.*, d.specialty, d.license_number, d.availability,
           (SELECT COUNT(*) FROM user_doctor_connections WHERE doctor_id = u.id AND status = 'active') as patient_count,
           (SELECT COUNT(*) FROM doctor_recommendations WHERE doctor_id = u.id) as recommendation_count
    FROM users u
    LEFT JOIN doctors d ON u.id = d.id
    WHERE $whereClause
    ORDER BY u.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$doctors = $stmt->fetchAll();

// Get specialty counts
$stmt = $db->query("SELECT specialty, COUNT(*) as count FROM doctors WHERE specialty IS NOT NULL AND specialty != '' GROUP BY specialty");
$specialtyCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<!-- Header with Search and Filters -->
<div class="bg-white rounded-xl shadow-md p-6 mb-6 animate-fade">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Doctors Management</h2>
            <p class="text-sm text-gray-600 mt-1">Total: <?php echo number_format($totalDoctors); ?> doctors</p>
        </div>
        
        <!-- Search and Filters -->
        <form method="GET" class="flex flex-col sm:flex-row gap-3">
            <div class="relative">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Search doctors..." 
                       class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-400 focus:border-transparent w-full sm:w-64">
                <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            
            <select name="specialty" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-400 focus:border-transparent">
                <option value="">All Specialties</option>
                <?php foreach ($specialtyCounts as $spec => $count): ?>
                    <option value="<?php echo htmlspecialchars($spec); ?>" <?php echo $specialty === $spec ? 'selected' : ''; ?>>
                        <?php echo ucfirst(htmlspecialchars($spec)); ?> (<?php echo $count; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            
            <button type="submit" class="px-6 py-2 bg-gradient-to-r from-gray-800 to-green-400 text-white rounded-lg hover:shadow-lg transition-all transform hover:-translate-y-0.5">
                Filter
            </button>
            
            <?php if ($search || $specialty): ?>
                <a href="doctors.php" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Clear
                </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Doctors Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
    <?php if (empty($doctors)): ?>
        <div class="col-span-full bg-white rounded-xl shadow-md p-12 text-center">
            <svg class="w-20 h-20 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
            <p class="text-xl font-semibold text-gray-900 mb-2">No doctors found</p>
            <p class="text-gray-600">Try adjusting your filters</p>
        </div>
    <?php else: ?>
        <?php foreach ($doctors as $doctor): ?>
            <div class="bg-white rounded-xl shadow-md overflow-hidden hover:shadow-xl transition-all transform hover:-translate-y-1 animate-fade">
                <!-- Doctor Header -->
                <div class="bg-gradient-to-r from-gray-800 to-green-400 p-6 text-white">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center overflow-hidden">
                            <?php if (!empty($doctor['avatar']) && file_exists(__DIR__ . '/../' . $doctor['avatar'])): ?>
                                <img src="../<?php echo htmlspecialchars($doctor['avatar']); ?>" alt="Avatar" class="w-full h-full object-cover">
                            <?php else: ?>
                                <span class="text-2xl font-bold text-gray-800"><?php echo strtoupper(substr($doctor['name'], 0, 2)); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-xl font-bold truncate"><?php echo htmlspecialchars($doctor['name']); ?></h3>
                            <p class="text-sm text-white/80 truncate"><?php echo htmlspecialchars($doctor['email']); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Doctor Info -->
                <div class="p-6 space-y-4">
                    <!-- Specialty -->
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Specialty</p>
                            <p class="font-semibold text-gray-900 capitalize"><?php echo htmlspecialchars($doctor['specialty'] ?? 'Not specified'); ?></p>
                        </div>
                    </div>
                    
                    <!-- License -->
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">License Number</p>
                            <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($doctor['license_number'] ?? 'N/A'); ?></p>
                        </div>
                    </div>
                    
                    <!-- Stats -->
                    <div class="grid grid-cols-2 gap-4 pt-4 border-t border-gray-200">
                        <div class="text-center">
                            <p class="text-2xl font-bold text-gray-900"><?php echo $doctor['patient_count']; ?></p>
                            <p class="text-xs text-gray-600">Patients</p>
                        </div>
                        <div class="text-center">
                            <p class="text-2xl font-bold text-gray-900"><?php echo $doctor['recommendation_count']; ?></p>
                            <p class="text-xs text-gray-600">Reviews</p>
                        </div>
                    </div>
                    
                    <!-- Status -->
                    <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                        <span class="text-sm font-medium text-gray-700">Status:</span>
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                            <input type="hidden" name="status" value="<?php echo $doctor['availability']; ?>">
                            <button type="submit" class="flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold transition <?php echo $doctor['availability'] === 'online' ? 'bg-green-100 text-green-800 hover:bg-green-200' : 'bg-gray-100 text-gray-800 hover:bg-gray-200'; ?>">
                                <span class="w-2 h-2 rounded-full <?php echo $doctor['availability'] === 'online' ? 'bg-green-500' : 'bg-gray-500'; ?>"></span>
                                <?php echo ucfirst($doctor['availability'] ?? 'offline'); ?>
                            </button>
                        </form>
                    </div>
                    
                    <!-- Actions -->
                    <div class="flex gap-2 pt-4 border-t border-gray-200">
                        <button @click="$dispatch('open-doctor-modal', { doctorId: <?php echo $doctor['id']; ?> })" 
                                class="flex-1 px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition text-center text-sm font-medium">
                            View Profile
                        </button>
                        <button @click="$dispatch('open-delete-modal', { doctorId: <?php echo $doctor['id']; ?>, doctorName: '<?php echo addslashes(htmlspecialchars($doctor['name'])); ?>' })" 
                                class="flex-1 px-4 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition text-sm font-medium">
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
    <div class="bg-white rounded-xl shadow-md px-6 py-4">
        <div class="flex items-center justify-between">
            <p class="text-sm text-gray-600">
                Showing <?php echo min($offset + 1, $totalDoctors); ?> to <?php echo min($offset + $perPage, $totalDoctors); ?> of <?php echo $totalDoctors; ?> results
            </p>
            
            <div class="flex gap-2">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&specialty=<?php echo urlencode($specialty); ?>" 
                       class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition">Previous</a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&specialty=<?php echo urlencode($specialty); ?>" 
                       class="px-4 py-2 border rounded-lg transition <?php echo $i === $page ? 'bg-green-500 text-white border-green-500' : 'border-gray-300 hover:bg-gray-100'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&specialty=<?php echo urlencode($specialty); ?>" 
                       class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition">Next</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Doctor Profile Modal -->
<div x-data="doctorModal()" 
     @open-doctor-modal.window="openModal($event.detail.doctorId)" 
     x-show="isOpen" 
     x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" 
     @click.self="closeModal()">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden animate-scale" @click.stop>
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-gray-800 to-green-400 px-6 py-4 flex justify-between items-center">
            <h2 class="text-xl font-bold text-white" x-text="editMode ? 'Edit Doctor Profile' : 'Doctor Profile'"></h2>
            <div class="flex items-center gap-3">
                <button x-show="!editMode" @click="toggleEditMode()" 
                        class="px-4 py-2 bg-white/20 hover:bg-white/30 text-white rounded-lg transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Edit
                </button>
                <button @click="closeModal()" class="text-white hover:text-gray-200 text-2xl">&times;</button>
            </div>
        </div>
        
        <!-- Loading State -->
        <div x-show="loading" class="p-8 text-center">
            <svg class="animate-spin h-12 w-12 mx-auto text-green-500" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="mt-4 text-gray-600">Loading doctor details...</p>
        </div>
        
        <!-- Modal Content -->
        <div x-show="!loading && doctorData" class="overflow-y-auto max-h-[calc(90vh-80px)]">
            <div class="p-6 space-y-6">
                
                <!-- Edit Form (Only visible in edit mode) -->
                <div x-show="editMode" class="bg-blue-50 border-2 border-blue-200 rounded-lg p-6">
                    <form @submit.prevent="saveChanges()">
                        <div class="space-y-6">
                            <!-- Basic Information -->
                            <div>
                                <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                    Basic Information
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                                        <input type="text" x-model="editData.name" required
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Email Address *</label>
                                        <input type="email" x-model="editData.email" required
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Phone</label>
                                        <input type="tel" x-model="editData.phone"
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Professional Information -->
                            <div>
                                <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    Professional Details
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Specialty *</label>
                                        <input type="text" x-model="editData.specialty" required
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                               placeholder="e.g., Cardiology">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">License Number *</label>
                                        <input type="text" x-model="editData.license_number" required
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                               placeholder="e.g., MD12345">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Availability *</label>
                                        <select x-model="editData.availability" required
                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                            <option value="available">Available</option>
                                            <option value="busy">Busy</option>
                                            <option value="offline">Offline</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="flex gap-3 pt-4 border-t border-blue-200">
                                <button type="button" @click="cancelEdit()" 
                                        class="flex-1 px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-medium transition">
                                    Cancel
                                </button>
                                <button type="submit" :disabled="saving"
                                        class="flex-1 px-6 py-3 bg-gradient-to-r from-gray-800 to-green-400 text-white rounded-lg hover:shadow-lg font-medium transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                                    <svg x-show="saving" class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span x-text="saving ? 'Saving...' : 'Save Changes'"></span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Doctor Profile Section (Read-only view) -->
                <div x-show="!editMode">
                <!-- Doctor Profile Section -->
                <div class="flex items-start gap-6 pb-6 border-b border-gray-200">
                    <div class="w-24 h-24 bg-gradient-to-br from-gray-800 to-green-400 rounded-full flex items-center justify-center text-white text-3xl font-bold flex-shrink-0 overflow-hidden">
                        <template x-if="doctorData.avatar && doctorData.avatar.trim() !== ''">
                            <img :src="'../' + doctorData.avatar" alt="Avatar" class="w-full h-full object-cover">
                        </template>
                        <template x-if="!doctorData.avatar || doctorData.avatar.trim() === ''">
                            <span x-text="doctorData.name ? doctorData.name.substring(0, 2).toUpperCase() : 'D'"></span>
                        </template>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-2xl font-bold text-gray-900" x-text="doctorData.name"></h3>
                        <p class="text-gray-600" x-text="doctorData.email"></p>
                        <div class="flex gap-2 mt-3">
                            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                Doctor
                            </span>
                            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                Joined <span x-text="new Date(doctorData.created_at).toLocaleDateString()"></span>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Statistics Grid -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-blue-50 rounded-lg p-4">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-blue-500 rounded-lg flex items-center justify-center text-white">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900" x-text="doctorData.stats?.assessment_count || 0"></p>
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
                                <p class="text-2xl font-bold text-gray-900" x-text="doctorData.stats?.habit_count || 0"></p>
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
                                <p class="text-2xl font-bold text-gray-900" x-text="doctorData.stats?.message_count || 0"></p>
                                <p class="text-sm text-gray-600">Messages</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-orange-50 rounded-lg p-4">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-orange-500 rounded-lg flex items-center justify-center text-white">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900" x-text="doctorData.stats?.patient_count || 0"></p>
                                <p class="text-sm text-gray-600">Patients</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Doctor Professional Information -->
                <div x-show="doctorData.doctor_info" class="bg-gradient-to-r from-green-50 to-blue-50 rounded-lg p-6 border-2 border-green-200">
                    <h4 class="font-bold text-gray-900 mb-4 flex items-center gap-2 text-lg">
                        <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-blue-500 rounded-lg flex items-center justify-center text-white">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        Professional Information
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
                            <p class="font-bold text-gray-900 text-lg capitalize" x-text="doctorData.doctor_info?.specialty || 'N/A'"></p>
                        </div>
                        
                        <!-- License Number -->
                        <div class="bg-white rounded-lg p-4 shadow-sm">
                            <div class="flex items-center gap-2 mb-2">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <p class="text-gray-500 font-medium text-sm">License Number</p>
                            </div>
                            <p class="font-bold text-gray-900 text-lg" x-text="doctorData.doctor_info?.license_number || 'N/A'"></p>
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
                                      'bg-green-100 text-green-800': doctorData.doctor_info?.availability === 'available',
                                      'bg-yellow-100 text-yellow-800': doctorData.doctor_info?.availability === 'busy',
                                      'bg-gray-100 text-gray-800': doctorData.doctor_info?.availability === 'offline'
                                  }"
                                  x-text="doctorData.doctor_info?.availability ? doctorData.doctor_info.availability.charAt(0).toUpperCase() + doctorData.doctor_info.availability.slice(1) : 'N/A'"></span>
                        </div>
                        
                        <!-- Patient Count -->
                        <div class="bg-white rounded-lg p-4 shadow-sm">
                            <div class="flex items-center gap-2 mb-2">
                                <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                                <p class="text-gray-500 font-medium text-sm">Active Patients</p>
                            </div>
                            <p class="font-bold text-gray-900 text-lg" x-text="doctorData.stats?.patient_count || 0"></p>
                        </div>
                    </div>
                    
                    <!-- Recommendations Count -->
                    <template x-if="doctorData.stats?.recommendation_count !== undefined">
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
                                        <p class="font-bold text-gray-900 text-2xl" x-text="doctorData.stats.recommendation_count"></p>
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
                                <p class="font-semibold text-gray-900" x-text="doctorData.name || 'N/A'"></p>
                            </div>
                            <div>
                                <p class="text-gray-500">Email Address</p>
                                <p class="font-semibold text-gray-900" x-text="doctorData.email || 'N/A'"></p>
                            </div>
                            <div>
                                <p class="text-gray-500">Phone</p>
                                <p class="font-semibold text-gray-900" x-text="doctorData.phone || 'N/A'"></p>
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
                                <p class="font-semibold text-gray-900" x-text="doctorData.id || 'N/A'"></p>
                            </div>
                            <div>
                                <p class="text-gray-500">Role</p>
                                <p class="font-semibold text-gray-900">Doctor</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Member Since</p>
                                <p class="font-semibold text-gray-900" x-text="doctorData.created_at ? new Date(doctorData.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A'"></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Assessments -->
                <div x-show="doctorData.recent_assessments && doctorData.recent_assessments.length > 0" class="bg-gray-50 rounded-lg p-4">
                    <h4 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        Recent Assessments
                    </h4>
                    <div class="space-y-2">
                        <template x-for="assessment in doctorData.recent_assessments" :key="assessment.id">
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
                </div>
                <!-- End of read-only view -->
                
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div x-data="deleteDoctorModal()" 
     @keydown.escape.window="closeModal()"
     @open-delete-modal.window="openModal($event.detail)"
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
                        <h4 class="text-lg font-semibold text-gray-900 mb-2">Delete Doctor?</h4>
                        <p class="text-gray-600 mb-4">
                            Are you sure you want to delete 
                            <span class="font-semibold text-gray-900" x-text="doctorName"></span>?
                        </p>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                            <p class="text-sm text-red-800 flex items-center gap-2">
                                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                <span><strong>Warning:</strong> This action cannot be undone. All doctor data, patient connections, and recommendations will be permanently deleted.</span>
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
                            Delete Doctor
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function doctorModal() {
        return {
            isOpen: false,
            loading: false,
            doctorData: null,
            editMode: false,
            saving: false,
            editData: {
                name: '',
                email: '',
                phone: '',
                specialty: '',
                license_number: '',
                availability: 'offline'
            },
            
            async openModal(doctorId) {
                this.isOpen = true;
                this.loading = true;
                this.doctorData = null;
                this.editMode = false;
                
                try {
                    console.log('Fetching doctor details for ID:', doctorId);
                    const response = await fetch(`api.php?action=get_user_details&user_id=${doctorId}`);
                    const data = await response.json();
                    
                    console.log('API Response:', data);
                    
                    if (data.success && data.data && data.data.user) {
                        this.doctorData = data.data.user;
                        console.log('Doctor data loaded:', this.doctorData);
                    } else {
                        console.error('Failed to load doctor data:', data.message);
                        alert('Error: ' + (data.message || 'Failed to load doctor data'));
                    }
                } catch (error) {
                    console.error('Error fetching doctor data:', error);
                    alert('Network error: ' + error.message);
                } finally {
                    this.loading = false;
                }
            },
            
            toggleEditMode() {
                this.editMode = true;
                // Populate edit form with current data
                this.editData = {
                    name: this.doctorData.name || '',
                    email: this.doctorData.email || '',
                    phone: this.doctorData.phone || '',
                    specialty: this.doctorData.doctor_info?.specialty || '',
                    license_number: this.doctorData.doctor_info?.license_number || '',
                    availability: this.doctorData.doctor_info?.availability || 'offline'
                };
            },
            
            cancelEdit() {
                this.editMode = false;
                // Reset edit data
                this.editData = {
                    name: '',
                    email: '',
                    phone: '',
                    specialty: '',
                    license_number: '',
                    availability: 'offline'
                };
            },
            
            async saveChanges() {
                this.saving = true;
                
                try {
                    const formData = new FormData();
                    formData.append('action', 'update_doctor');
                    formData.append('doctor_id', this.doctorData.id);
                    formData.append('name', this.editData.name);
                    formData.append('email', this.editData.email);
                    formData.append('phone', this.editData.phone);
                    formData.append('specialty', this.editData.specialty);
                    formData.append('license_number', this.editData.license_number);
                    formData.append('availability', this.editData.availability);
                    
                    console.log('Sending update request:', Object.fromEntries(formData));
                    
                    const response = await fetch('doctors.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        // Update local data
                        this.doctorData.name = this.editData.name;
                        this.doctorData.email = this.editData.email;
                        this.doctorData.phone = this.editData.phone;
                        this.doctorData.doctor_info.specialty = this.editData.specialty;
                        this.doctorData.doctor_info.license_number = this.editData.license_number;
                        this.doctorData.doctor_info.availability = this.editData.availability;
                        
                        this.editMode = false;
                        
                        // Show success notification
                        window.dispatchEvent(new CustomEvent('show-notification', {
                            detail: {
                                type: 'success',
                                title: 'Success!',
                                message: 'Doctor profile updated successfully!'
                            }
                        }));
                        
                        // Reload page after notification
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        window.dispatchEvent(new CustomEvent('show-notification', {
                            detail: {
                                type: 'error',
                                title: 'Error!',
                                message: data.message || 'Failed to update doctor profile'
                            }
                        }));
                    }
                } catch (error) {
                    console.error('Error saving changes:', error);
                    window.dispatchEvent(new CustomEvent('show-notification', {
                        detail: {
                            type: 'error',
                            title: 'Network Error!',
                            message: error.message
                        }
                    }));
                } finally {
                    this.saving = false;
                }
            },
            
            closeModal() {
                this.isOpen = false;
                this.doctorData = null;
                this.editMode = false;
            }
        };
    }

    function deleteDoctorModal() {
        return {
            isOpen: false,
            doctorId: null,
            doctorName: '',

            init() {
                this.$watch('isOpen', value => {
                    if (value) {
                        document.body.style.overflow = 'hidden';
                    } else {
                        document.body.style.overflow = 'auto';
                    }
                });
            },

            openModal(detail) {
                this.doctorId = detail.doctorId;
                this.doctorName = detail.doctorName;
                this.isOpen = true;
            },

            confirmDelete() {
                // Create and submit form
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="doctor_id" value="${this.doctorId}">
                `;
                document.body.appendChild(form);
                form.submit();
            },

            closeModal() {
                this.isOpen = false;
            }
        };
    }

    function notification() {
        return {
            show: false,
            type: 'success', // success, error, info
            title: '',
            message: '',
            progress: 100,
            timer: null,

            init() {
                window.addEventListener('show-notification', (e) => {
                    this.showNotification(e.detail);
                });
            },

            showNotification(detail) {
                this.type = detail.type || 'success';
                this.title = detail.title || 'Notification';
                this.message = detail.message || '';
                this.progress = 100;
                this.show = true;

                // Clear any existing timer
                if (this.timer) {
                    clearInterval(this.timer);
                }

                // Progress bar animation
                const duration = 3000; // 3 seconds
                const interval = 30; // Update every 30ms
                const step = (interval / duration) * 100;

                this.timer = setInterval(() => {
                    this.progress -= step;
                    if (this.progress <= 0) {
                        this.hide();
                    }
                }, interval);
            },

            hide() {
                this.show = false;
                if (this.timer) {
                    clearInterval(this.timer);
                    this.timer = null;
                }
            }
        };
    }
</script>

<!-- Success Notification -->
<div x-data="notification()" 
     x-show="show" 
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0 transform translate-y-2"
     x-transition:enter-end="opacity-100 transform translate-y-0"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100 transform translate-y-0"
     x-transition:leave-end="opacity-0 transform translate-y-2"
     class="fixed top-4 right-4 z-[60] max-w-md"
     x-cloak>
    <div class="bg-white rounded-lg shadow-2xl border-l-4 overflow-hidden"
         :class="{
             'border-green-500': type === 'success',
             'border-red-500': type === 'error',
             'border-blue-500': type === 'info'
         }">
        <div class="p-4 flex items-start gap-3">
            <!-- Icon -->
            <div class="flex-shrink-0">
                <div class="w-10 h-10 rounded-full flex items-center justify-center"
                     :class="{
                         'bg-green-100': type === 'success',
                         'bg-red-100': type === 'error',
                         'bg-blue-100': type === 'info'
                     }">
                    <!-- Success Icon -->
                    <svg x-show="type === 'success'" class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <!-- Error Icon -->
                    <svg x-show="type === 'error'" class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    <!-- Info Icon -->
                    <svg x-show="type === 'info'" class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            
            <!-- Content -->
            <div class="flex-1 min-w-0">
                <h3 class="text-sm font-bold text-gray-900 mb-1" x-text="title"></h3>
                <p class="text-sm text-gray-600" x-text="message"></p>
            </div>
            
            <!-- Close Button -->
            <button @click="hide()" class="flex-shrink-0 text-gray-400 hover:text-gray-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        
        <!-- Progress Bar -->
        <div class="h-1 bg-gray-100">
            <div class="h-full transition-all duration-[3000ms] ease-linear"
                 :class="{
                     'bg-green-500': type === 'success',
                     'bg-red-500': type === 'error',
                     'bg-blue-500': type === 'info'
                 }"
                 :style="{ width: progress + '%' }"></div>
        </div>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }
    
    @keyframes scale {
        from {
            transform: scale(0.95);
            opacity: 0;
        }
        to {
            transform: scale(1);
            opacity: 1;
        }
    }
    
    .animate-scale {
        animation: scale 0.3s ease-out;
    }
</style>

<?php require_once __DIR__ . '/include/footer.php'; ?>
