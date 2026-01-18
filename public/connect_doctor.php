<?php
/**
 * Connect with Doctor Page
 * Allows users to find and connect with doctors
 */

require_once __DIR__ . '/../src/helpers/Auth.php';
require_once __DIR__ . '/../src/helpers/Database.php';
require_once __DIR__ . '/../src/helpers/Utils.php';

// Check authentication - only regular users
$auth = new Auth();
$auth->requireLogin();
$auth->requireRole('user');

$currentUser = $auth->getCurrentUser();
$db = new Database();
$conn = $db->connect();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'connect_doctor') {
        try {
            $doctorId = intval($_POST['doctor_id']);
            
            // Check if connection already exists
            $checkStmt = $conn->prepare("
                SELECT id, status FROM user_doctor_connections 
                WHERE user_id = ? AND doctor_id = ?
            ");
            $checkStmt->execute([$currentUser['id'], $doctorId]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                if ($existing['status'] === 'active') {
                    echo json_encode(['success' => false, 'message' => 'You are already connected with this doctor']);
                } else {
                    // Reactivate connection
                    $updateStmt = $conn->prepare("
                        UPDATE user_doctor_connections 
                        SET status = 'active', created_at = NOW()
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$existing['id']]);
                    echo json_encode(['success' => true, 'message' => 'Connection reactivated successfully']);
                }
            } else {
                // Create new connection (assessment_id can be NULL for direct connections)
                $insertStmt = $conn->prepare("
                    INSERT INTO user_doctor_connections 
                    (user_id, doctor_id, assessment_id, status, created_at) 
                    VALUES (?, ?, NULL, 'active', NOW())
                ");
                $result = $insertStmt->execute([$currentUser['id'], $doctorId]);
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Connected successfully! The doctor can now see your profile.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to connect with doctor']);
                }
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'disconnect_doctor') {
        try {
            $doctorId = intval($_POST['doctor_id']);
            
            $stmt = $conn->prepare("
                UPDATE user_doctor_connections 
                SET status = 'cancelled'
                WHERE user_id = ? AND doctor_id = ?
            ");
            $result = $stmt->execute([$currentUser['id'], $doctorId]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Disconnected successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to disconnect']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// Get all doctors
$doctorsStmt = $conn->query("
    SELECT 
        u.id,
        u.name,
        u.email,
        u.avatar,
        d.specialty,
        d.license_number,
        d.created_at as joined_date,
        (SELECT COUNT(*) FROM user_doctor_connections WHERE doctor_id = u.id AND status = 'active') as patient_count
    FROM users u
    JOIN doctors d ON u.id = d.id
    WHERE u.role = 'doctor'
    ORDER BY u.name ASC
");
$doctors = $doctorsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's current connections
$connectionsStmt = $conn->prepare("
    SELECT doctor_id, status 
    FROM user_doctor_connections 
    WHERE user_id = ?
");
$connectionsStmt->execute([$currentUser['id']]);
$connections = $connectionsStmt->fetchAll(PDO::FETCH_ASSOC);

// Create a map of doctor_id => status
$connectionMap = [];
foreach ($connections as $conn_data) {
    $connectionMap[$conn_data['doctor_id']] = $conn_data['status'];
}

$title = "Connect with Doctors - Health Tracker";
require_once __DIR__ . '/../src/views/partials/header.php';
?>

<style>
    .doctor-card {
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .doctor-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
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
    
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(5px);
        animation: fadeIn 0.3s ease-out;
    }
    
    .modal.show {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .modal-content {
        background-color: white;
        margin: auto;
        padding: 0;
        border-radius: 20px;
        width: 90%;
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
        animation: slideInDown 0.3s ease-out;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes slideInDown {
        from {
            opacity: 0;
            transform: translateY(-50px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .close-modal {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        transition: color 0.3s;
    }
    
    .close-modal:hover,
    .close-modal:focus {
        color: #000;
    }
</style>

<div class="container mx-auto px-4 py-8 max-w-7xl">
    <!-- Header -->
    <div class="bg-white rounded-2xl shadow-lg p-8 mb-8 animate-slide-up">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h1 class="text-4xl font-bold text-gray-900 mb-2">Connect with Doctors</h1>
                <p class="text-gray-600">Find and connect with healthcare professionals who can support your wellness journey</p>
            </div>
            <div class="flex items-center gap-4">
                <div class="bg-gradient-to-r from-gray-800 to-green-400 text-white px-6 py-3 rounded-xl text-center">
                    <div class="text-3xl font-bold"><?php echo count(array_filter($connectionMap, fn($status) => $status === 'active')); ?></div>
                    <div class="text-sm">Connected Doctors</div>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($doctors)): ?>
        <!-- No Doctors -->
        <div class="bg-white rounded-2xl shadow-lg p-12 text-center animate-slide-up">
            <svg class="w-24 h-24 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h3 class="text-2xl font-bold text-gray-900 mb-2">No Doctors Available</h3>
            <p class="text-gray-600">There are currently no doctors registered in the system.</p>
        </div>
    <?php else: ?>
        <!-- Doctors Grid -->
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($doctors as $index => $doctor): 
                $isConnected = isset($connectionMap[$doctor['id']]) && $connectionMap[$doctor['id']] === 'active';
                $isCancelled = isset($connectionMap[$doctor['id']]) && $connectionMap[$doctor['id']] === 'cancelled';
            ?>
                <div class="bg-white rounded-2xl shadow-lg p-6 doctor-card animate-slide-up relative" 
                     style="animation-delay: <?php echo $index * 0.1; ?>s;"
                     onclick="showDoctorDetails(<?php echo htmlspecialchars(json_encode($doctor)); ?>, <?php echo $isConnected ? 'true' : 'false'; ?>, <?php echo $isCancelled ? 'true' : 'false'; ?>)">
                    
                    <!-- Click to View Details Indicator -->
                    <div class="absolute top-4 right-4 bg-blue-500 text-white px-3 py-1 rounded-full text-xs font-semibold flex items-center gap-1 shadow-lg">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        View Details
                    </div>
                    
                    <!-- Doctor Info -->
                    <div class="text-center mb-6">
                        <div class="w-24 h-24 mx-auto mb-4 rounded-full bg-gradient-to-r from-gray-800 to-green-400 flex items-center justify-center text-white text-3xl font-bold overflow-hidden shadow-lg">
                            <?php if (!empty($doctor['avatar']) && file_exists(__DIR__ . '/' . $doctor['avatar'])): ?>
                                <img src="<?php echo htmlspecialchars($doctor['avatar']); ?>" 
                                     alt="<?php echo htmlspecialchars($doctor['name']); ?>" 
                                     class="w-full h-full object-cover">
                            <?php else: ?>
                                <?php echo strtoupper(substr($doctor['name'], 0, 2)); ?>
                            <?php endif; ?>
                        </div>
                        
                        <h3 class="text-xl font-bold text-gray-900 mb-1">Dr. <?php echo htmlspecialchars($doctor['name']); ?></h3>
                        <p class="text-sm text-gray-600 mb-2"><?php echo htmlspecialchars($doctor['email']); ?></p>
                        
                        <?php if (!empty($doctor['specialty'])): ?>
                            <span class="inline-block px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-semibold mb-2">
                                <?php echo htmlspecialchars($doctor['specialty']); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-2 gap-3 mb-6">
                        <div class="bg-green-50 rounded-xl p-3 text-center">
                            <div class="text-2xl font-bold text-green-700"><?php echo $doctor['patient_count']; ?></div>
                            <div class="text-xs text-green-600">Patients</div>
                        </div>
                        <div class="bg-blue-50 rounded-xl p-3 text-center">
                            <div class="text-sm font-bold text-blue-700">
                                <?php echo date('Y', strtotime($doctor['joined_date'])); ?>
                            </div>
                            <div class="text-xs text-blue-600">Joined</div>
                        </div>
                    </div>

                    <!-- License Info -->
                    <?php if (!empty($doctor['license_number'])): ?>
                        <div class="bg-gray-50 rounded-xl p-3 mb-6 text-center">
                            <div class="text-xs text-gray-500 mb-1">License Number</div>
                            <div class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($doctor['license_number']); ?></div>
                        </div>
                    <?php endif; ?>

                    <!-- Connection Status / Action Button -->
                    <?php if ($isConnected): ?>
                        <div class="space-y-2">
                            <div class="px-4 py-3 bg-green-100 text-green-700 rounded-xl font-semibold text-center flex items-center justify-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Connected
                            </div>
                            <button onclick="event.stopPropagation(); startVideoCall(<?php echo $doctor['id']; ?>, '<?php echo htmlspecialchars($doctor['name']); ?>')"
                                    class="w-full px-4 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl font-semibold hover:shadow-lg transition-all text-sm flex items-center justify-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                                Video Call
                            </button>
                            <button onclick="event.stopPropagation(); disconnectDoctor(<?php echo $doctor['id']; ?>, '<?php echo htmlspecialchars($doctor['name']); ?>')"
                                    class="w-full px-4 py-2 bg-red-100 text-red-700 rounded-xl font-semibold hover:bg-red-200 transition-all text-sm">
                                Disconnect
                            </button>
                        </div>
                    <?php else: ?>
                        <button onclick="event.stopPropagation(); connectDoctor(<?php echo $doctor['id']; ?>, '<?php echo htmlspecialchars($doctor['name']); ?>')"
                                class="w-full px-4 py-3 bg-gradient-to-r from-gray-800 to-green-400 text-white rounded-xl font-semibold hover:shadow-lg transition-all">
                            <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                            </svg>
                            <?php echo $isCancelled ? 'Reconnect' : 'Connect'; ?>
                        </button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Info Section -->
    <div class="mt-8 bg-blue-50 border-2 border-blue-200 rounded-2xl p-6">
        <div class="flex items-start gap-4">
            <svg class="w-8 h-8 text-blue-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <h3 class="font-bold text-blue-900 text-lg mb-2">Why Connect with a Doctor?</h3>
                <ul class="space-y-2 text-blue-800">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-blue-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>Doctors can create personalized habit plans for you</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-blue-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>Get professional verification and feedback on your progress</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-blue-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>Receive medical insights based on your assessments</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-blue-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>Your data is only visible to doctors you connect with</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Doctor Details Modal -->
<div id="doctorModal" class="modal">
    <div class="modal-content">
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-gray-800 to-green-400 text-white p-6 rounded-t-2xl">
            <div class="flex items-center justify-between">
                <h2 class="text-2xl font-bold">Doctor Details</h2>
                <span class="close-modal text-white hover:text-gray-200" onclick="closeDoctorModal()">&times;</span>
            </div>
        </div>
        
        <!-- Modal Body -->
        <div class="p-6">
            <div id="modalContent">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>
</div>

<!-- Video Call Modal -->
<div id="videoCallModal" class="modal">
    <div class="modal-content" style="max-width: 1200px;">
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-blue-500 to-purple-600 text-white p-6 rounded-t-2xl">
            <div class="flex items-center justify-between">
                <h2 class="text-2xl font-bold flex items-center gap-3">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    Video Call
                </h2>
                <span class="close-modal text-white hover:text-gray-200" onclick="endVideoCall()">&times;</span>
            </div>
        </div>
        
        <!-- Modal Body -->
        <div class="p-6">
            <div id="videoCallContent">
                <!-- Video Call Interface -->
                <div class="relative">
                    <!-- Main Video (Remote) -->
                    <div class="bg-gray-900 rounded-2xl overflow-hidden relative" style="height: 500px;">
                        <video id="remoteVideo" autoplay playsinline class="w-full h-full object-cover"></video>
                        <div id="callStatus" class="absolute top-4 left-4 bg-black bg-opacity-50 text-white px-4 py-2 rounded-full flex items-center gap-2">
                            <div class="w-3 h-3 bg-yellow-400 rounded-full animate-pulse"></div>
                            <span class="font-semibold">Calling...</span>
                        </div>
                        <div id="callTimer" class="absolute top-4 right-4 bg-black bg-opacity-50 text-white px-4 py-2 rounded-full font-semibold hidden">
                            00:00
                        </div>
                    </div>
                    
                    <!-- Local Video (Picture in Picture) -->
                    <div class="absolute bottom-4 right-4 w-48 h-36 bg-gray-800 rounded-xl overflow-hidden shadow-2xl">
                        <video id="localVideo" autoplay muted playsinline class="w-full h-full object-cover"></video>
                    </div>
                    
                    <!-- Call Controls -->
                    <div class="absolute bottom-4 left-1/2 transform -translate-x-1/2 flex items-center gap-4">
                        <button id="toggleMicBtn" onclick="toggleMicrophone()" 
                                class="bg-gray-700 hover:bg-gray-600 text-white p-4 rounded-full transition-all shadow-lg">
                            <svg id="micOnIcon" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/>
                            </svg>
                            <svg id="micOffIcon" class="w-6 h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" clip-rule="evenodd"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"/>
                            </svg>
                        </button>
                        
                        <button id="toggleVideoBtn" onclick="toggleVideo()" 
                                class="bg-gray-700 hover:bg-gray-600 text-white p-4 rounded-full transition-all shadow-lg">
                            <svg id="videoOnIcon" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            <svg id="videoOffIcon" class="w-6 h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                            </svg>
                        </button>
                        
                        <button onclick="endVideoCall()" 
                                class="bg-red-600 hover:bg-red-700 text-white px-6 py-4 rounded-full transition-all shadow-lg flex items-center gap-2 font-semibold">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M5 3a2 2 0 00-2 2v1c0 8.284 6.716 15 15 15h1a2 2 0 002-2v-3.28a1 1 0 00-.684-.948l-4.493-1.498a1 1 0 00-1.21.502l-1.13 2.257a11.042 11.042 0 01-5.516-5.517l2.257-1.128a1 1 0 00.502-1.21L9.228 3.683A1 1 0 008.279 3H5z"/>
                            </svg>
                            End Call
                        </button>
                    </div>
                </div>
                
                <!-- Connection Info -->
                <div id="connectionInfo" class="mt-4 text-center text-gray-600 text-sm">
                    <p>Establishing secure connection...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function showDoctorDetails(doctor, isConnected, isCancelled) {
        const modal = document.getElementById('doctorModal');
        const modalContent = document.getElementById('modalContent');
        
        // Calculate years of experience
        const joinedYear = new Date(doctor.joined_date).getFullYear();
        const currentYear = new Date().getFullYear();
        const yearsOfExperience = currentYear - joinedYear;
        
        // Build avatar HTML
        let avatarHTML = '';
        if (doctor.avatar && doctor.avatar.trim() !== '') {
            avatarHTML = `<img src="${doctor.avatar}" alt="${doctor.name}" class="w-full h-full object-cover">`;
        } else {
            avatarHTML = doctor.name.substring(0, 2).toUpperCase();
        }
        
        // Build connection status badge
        let statusBadge = '';
        if (isConnected) {
            statusBadge = `
                <div class="inline-flex items-center gap-2 px-4 py-2 bg-green-100 text-green-700 rounded-full font-semibold">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Connected
                </div>
            `;
        } else {
            statusBadge = `
                <div class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-full font-semibold">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Not Connected
                </div>
            `;
        }
        
        modalContent.innerHTML = `
            <!-- Doctor Profile -->
            <div class="text-center mb-6">
                <div class="w-32 h-32 mx-auto mb-4 rounded-full bg-gradient-to-r from-gray-800 to-green-400 flex items-center justify-center text-white text-4xl font-bold overflow-hidden shadow-xl">
                    ${avatarHTML}
                </div>
                <h3 class="text-3xl font-bold text-gray-900 mb-2">Dr. ${doctor.name}</h3>
                <p class="text-gray-600 mb-3">${doctor.email}</p>
                ${statusBadge}
            </div>
            
            <!-- Specialty -->
            ${doctor.specialty ? `
                <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-4 mb-4">
                    <div class="flex items-center gap-3">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                        </svg>
                        <div>
                            <div class="text-sm text-blue-600 font-semibold">Specialization</div>
                            <div class="text-lg font-bold text-blue-900">${doctor.specialty}</div>
                        </div>
                    </div>
                </div>
            ` : ''}
            
            <!-- License Number -->
            ${doctor.license_number ? `
                <div class="bg-purple-50 border-2 border-purple-200 rounded-xl p-4 mb-4">
                    <div class="flex items-center gap-3">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <div>
                            <div class="text-sm text-purple-600 font-semibold">Medical License</div>
                            <div class="text-lg font-bold text-purple-900">${doctor.license_number}</div>
                        </div>
                    </div>
                </div>
            ` : ''}
            
            <!-- Stats Grid -->
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="bg-green-50 border-2 border-green-200 rounded-xl p-4 text-center">
                    <div class="text-3xl font-bold text-green-700 mb-1">${doctor.patient_count}</div>
                    <div class="text-sm text-green-600 font-semibold">Active Patients</div>
                </div>
                <div class="bg-orange-50 border-2 border-orange-200 rounded-xl p-4 text-center">
                    <div class="text-3xl font-bold text-orange-700 mb-1">${yearsOfExperience}+</div>
                    <div class="text-sm text-orange-600 font-semibold">Years on Platform</div>
                </div>
            </div>
            
            <!-- Joined Date -->
            <div class="bg-gray-50 rounded-xl p-4 mb-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span class="text-gray-600">Joined Platform</span>
                    </div>
                    <span class="font-bold text-gray-900">${new Date(doctor.joined_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</span>
                </div>
            </div>
            
            <!-- What This Doctor Can Do -->
            <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-4 mb-6">
                <h4 class="font-bold text-blue-900 text-lg mb-3 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    What This Doctor Can Do
                </h4>
                <ul class="space-y-2 text-blue-800">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>Create personalized habit plans tailored to your needs</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>Monitor your progress and provide feedback</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>Verify your habit completions and achievements</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>Review your health assessments and provide recommendations</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>Send you messages with health tips and guidance</span>
                    </li>
                </ul>
            </div>
            
            <!-- Action Buttons -->
            <div class="flex gap-3">
                ${isConnected ? `
                    <button onclick="event.stopPropagation(); disconnectDoctor(${doctor.id}, '${doctor.name}')" 
                            class="flex-1 px-6 py-3 bg-red-100 text-red-700 rounded-xl font-semibold hover:bg-red-200 transition-all flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                        </svg>
                        Disconnect
                    </button>
                ` : `
                    <button onclick="event.stopPropagation(); connectDoctor(${doctor.id}, '${doctor.name}')" 
                            class="flex-1 px-6 py-3 bg-gradient-to-r from-gray-800 to-green-400 text-white rounded-xl font-semibold hover:shadow-lg transition-all flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                        </svg>
                        ${isCancelled ? 'Reconnect' : 'Connect Now'}
                    </button>
                `}
                <button onclick="closeDoctorModal()" 
                        class="px-6 py-3 bg-gray-200 text-gray-700 rounded-xl font-semibold hover:bg-gray-300 transition-all">
                    Close
                </button>
            </div>
        `;
        
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    
    function closeDoctorModal() {
        const modal = document.getElementById('doctorModal');
        modal.classList.remove('show');
        document.body.style.overflow = 'auto';
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('doctorModal');
        if (event.target === modal) {
            closeDoctorModal();
        }
    }
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeDoctorModal();
        }
    });

    async function connectDoctor(doctorId, doctorName) {
        if (!confirm(`Connect with Dr. ${doctorName}?\n\nThey will be able to:\n• View your health assessments\n• Create habits for you\n• Monitor your progress\n• Send you messages`)) {
            return;
        }
        
        try {
            const formData = new FormData();
            formData.append('action', 'connect_doctor');
            formData.append('doctor_id', doctorId);
            
            const response = await fetch('connect_doctor.php', {
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
            console.error('Error:', error);
            alert('Error connecting with doctor. Please try again.');
        }
    }
    
    async function disconnectDoctor(doctorId, doctorName) {
        if (!confirm(`Disconnect from Dr. ${doctorName}?\n\nThey will no longer be able to:\n• View your assessments\n• Create habits for you\n• Monitor your progress\n\nYou can reconnect anytime.`)) {
            return;
        }
        
        try {
            const formData = new FormData();
            formData.append('action', 'disconnect_doctor');
            formData.append('doctor_id', doctorId);
            
            const response = await fetch('connect_doctor.php', {
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
            console.error('Error:', error);
            alert('Error disconnecting from doctor. Please try again.');
        }
    }

    // Video Call Functionality
    let localStream = null;
    let peerConnection = null;
    let currentCallSessionId = null;
    let currentDoctorId = null;
    let currentDoctorName = null;
    let callTimer = null;
    let callStartTime = null;
    let pollingInterval = null;
    let isMicMuted = false;
    let isVideoOff = false;

    const configuration = {
        iceServers: [
            { urls: 'stun:stun.l.google.com:19302' },
            { urls: 'stun:stun1.l.google.com:19302' }
        ]
    };

    async function startVideoCall(doctorId, doctorName) {
        currentDoctorId = doctorId;
        currentDoctorName = doctorName;
        
        // Generate unique session ID
        currentCallSessionId = 'call_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        
        // Show video call modal
        const modal = document.getElementById('videoCallModal');
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
        
        // Update status
        updateCallStatus('Initializing...', 'blue');
        
        try {
            console.log('Browser check:', {
                hasNavigator: !!navigator,
                hasMediaDevices: !!navigator.mediaDevices,
                hasGetUserMedia: !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia),
                protocol: window.location.protocol,
                hostname: window.location.hostname,
                isSecureContext: window.isSecureContext
            });
            
            // Check if getUserMedia is available
            if (!navigator.mediaDevices) {
                throw new Error('Camera/microphone access requires HTTPS.\n\nPlease access this page using:\nhttps://' + window.location.hostname + window.location.pathname);
            }
            
            if (!navigator.mediaDevices.getUserMedia) {
                throw new Error('Your browser does not support getUserMedia. Please update your browser.');
            }
            
            // Get user media
            console.log('User: Requesting camera and microphone access...');
            localStream = await navigator.mediaDevices.getUserMedia({
                video: { width: { ideal: 1280 }, height: { ideal: 720 } },
                audio: true
            });
            
            console.log('User: Media stream obtained:', localStream.getTracks().map(t => t.kind));
            
            // Display local video
            document.getElementById('localVideo').srcObject = localStream;
            
            // Create call session in database
            const response = await fetch('api/video_call.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'initiate_call',
                    doctor_id: doctorId,
                    session_id: currentCallSessionId
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                updateCallStatus(`Calling Dr. ${doctorName}...`, 'yellow');
                document.getElementById('connectionInfo').innerHTML = 
                    `<p>Waiting for Dr. ${doctorName} to answer...</p>`;
                
                // Start polling for call status
                startPolling();
            } else {
                throw new Error(result.message || 'Failed to initiate call');
            }
            
        } catch (error) {
            console.error('Error starting video call:', error);
            let errorMsg = error.message || 'Error accessing camera/microphone';
            
            if (error.name === 'NotAllowedError') {
                errorMsg = 'Camera/microphone permission denied.\n\nPlease click the camera icon in the address bar and allow access.';
            } else if (error.name === 'NotFoundError') {
                errorMsg = 'No camera or microphone found on your device.';
            } else if (error.name === 'NotReadableError') {
                errorMsg = 'Camera/microphone is already in use by another application.\n\nPlease close other apps using the camera.';
            } else if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
                errorMsg = 'Video calling requires HTTPS!\n\nPlease access:\nhttps://' + window.location.hostname + window.location.pathname;
            }
            
            alert(errorMsg);
            endVideoCall();
        }
    }

    function startPolling() {
        pollingInterval = setInterval(async () => {
            try {
                const response = await fetch('api/video_call.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'get_call_status',
                        session_id: currentCallSessionId
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Check if call was ended by the other party
                    if (result.status === 'ended' && peerConnection) {
                        console.log('Call ended by doctor');
                        alert('Call ended by Dr. ' + currentDoctorName);
                        endVideoCall();
                        return;
                    }
                    
                    handleCallStatusUpdate(result.status, result.signal);
                }
            } catch (error) {
                console.error('Polling error:', error);
            }
        }, 2000); // Poll every 2 seconds
    }

    async function handleCallStatusUpdate(status, signal) {
        if (status === 'active' && !peerConnection) {
            // Doctor answered - establish WebRTC connection
            updateCallStatus('Connecting...', 'green');
            await establishPeerConnection();
        } else if (status === 'rejected') {
            updateCallStatus('Call Rejected', 'red');
            setTimeout(() => {
                alert('Dr. ' + currentDoctorName + ' declined the call.');
                endVideoCall();
            }, 1000);
        } else if (status === 'ended') {
            updateCallStatus('Call Ended', 'gray');
            setTimeout(() => endVideoCall(), 1000);
        }
        
        // Handle WebRTC signaling
        if (signal) {
            console.log('User: Received signal:', Object.keys(signal));
            
            if (signal.answer && peerConnection) {
                console.log('User: ===== PROCESSING ANSWER =====');
                console.log('User: Answer type:', signal.answer.type);
                console.log('User: Answer SDP length:', signal.answer.sdp ? signal.answer.sdp.length : 0);
                console.log('User: Current signaling state:', peerConnection.signalingState);
                
                try {
                    if (peerConnection.signalingState === 'have-local-offer') {
                        await peerConnection.setRemoteDescription(new RTCSessionDescription(signal.answer));
                        updateCallStatus('Connected', 'green');
                        console.log('User: ✓ Answer set successfully');
                        console.log('User: New signaling state:', peerConnection.signalingState);
                    } else {
                        console.warn('User: Cannot set answer, signaling state is:', peerConnection.signalingState);
                    }
                } catch (error) {
                    console.error('User: Error setting remote description (answer):', error);
                }
            } else if (signal.answer && !peerConnection) {
                console.warn('User: Received answer but peer connection not established yet');
            } else if (signal.ice_candidate && peerConnection) {
                console.log('Received ICE candidate from doctor');
                try {
                    await peerConnection.addIceCandidate(new RTCIceCandidate(signal.ice_candidate));
                    console.log('User: ICE candidate added successfully');
                } catch (error) {
                    console.error('User: Error adding ICE candidate:', error);
                }
            }
        }
    }

    async function establishPeerConnection() {
        console.log('User: Establishing peer connection');
        peerConnection = new RTCPeerConnection(configuration);
        
        // Handle remote stream FIRST before adding local tracks
        peerConnection.ontrack = (event) => {
            console.log('User: ===== RECEIVED REMOTE TRACK =====');
            console.log('User: Track kind:', event.track.kind);
            console.log('User: Track state:', event.track.readyState);
            console.log('User: Track enabled:', event.track.enabled);
            console.log('User: Number of streams:', event.streams.length);
            
            const remoteVideo = document.getElementById('remoteVideo');
            
            if (event.streams && event.streams[0]) {
                const stream = event.streams[0];
                console.log('User: Stream details:', {
                    id: stream.id,
                    active: stream.active,
                    videoTracks: stream.getVideoTracks().length,
                    audioTracks: stream.getAudioTracks().length
                });
                
                remoteVideo.srcObject = stream;
                console.log('User: Remote video srcObject set');
                
                // Verify video element
                remoteVideo.onloadedmetadata = () => {
                    console.log('User: Remote video metadata loaded!');
                    console.log('User: Remote video dimensions:', remoteVideo.videoWidth, 'x', remoteVideo.videoHeight);
                };
                
                // Ensure video plays
                remoteVideo.play()
                    .then(() => console.log('User: Remote video playing successfully'))
                    .catch(e => console.error('User: Error playing remote video:', e));
                
                updateCallStatus('Connected', 'green');
                startCallTimer();
                document.getElementById('connectionInfo').innerHTML = 
                    `<p class="text-green-600 font-semibold">Connected with Dr. ${currentDoctorName}</p>`;
            } else {
                console.warn('User: No stream in track event');
            }
        };
        
        // Add local stream tracks AFTER setting up handlers
        localStream.getTracks().forEach(track => {
            peerConnection.addTrack(track, localStream);
            console.log('User: Added local track:', track.kind, track.label);
        });
        
        // Handle ICE candidates
        peerConnection.onicecandidate = async (event) => {
            if (event.candidate) {
                console.log('User: Sending ICE candidate');
                await fetch('api/video_call.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'send_signal',
                        session_id: currentCallSessionId,
                        signal: { ice_candidate: event.candidate }
                    })
                });
            }
        };
        
        // Monitor connection state
        peerConnection.onconnectionstatechange = () => {
            console.log('User: Connection state:', peerConnection.connectionState);
            if (peerConnection.connectionState === 'connected') {
                console.log('User: Peer connection established!');
            } else if (peerConnection.connectionState === 'failed') {
                console.error('User: Connection failed');
                updateCallStatus('Connection Failed', 'red');
            }
        };
        
        peerConnection.oniceconnectionstatechange = () => {
            console.log('User: ICE connection state:', peerConnection.iceConnectionState);
            if (peerConnection.iceConnectionState === 'connected') {
                console.log('User: ICE connection established!');
            }
        };
        
        // Verify tracks were added
        const senders = peerConnection.getSenders();
        console.log('User: Total tracks added to peer connection:', senders.length);
        senders.forEach(sender => {
            if (sender.track) {
                console.log('User: Sender track:', sender.track.kind, sender.track.enabled);
            }
        });
        
        // Create offer
        console.log('User: Creating offer');
        const offer = await peerConnection.createOffer({
            offerToReceiveAudio: true,
            offerToReceiveVideo: true
        });
        console.log('User: Offer created:', offer.type);
        
        await peerConnection.setLocalDescription(offer);
        console.log('User: Local description (offer) set successfully');
        console.log('User: Offer SDP length:', offer.sdp.length);
        
        // Send offer to doctor
        const response = await fetch('api/video_call.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'send_signal',
                session_id: currentCallSessionId,
                signal: { offer: offer }
            })
        });
        
        const result = await response.json();
        console.log('User: Offer send result:', result);
        
        if (!result.success) {
            console.error('User: Failed to send offer:', result.message);
            alert('Failed to send call signal. Please try again.');
            endVideoCall();
            return;
        }
        
        console.log('User: Offer sent successfully, waiting for answer from doctor');
    }

    function startCallTimer() {
        callStartTime = Date.now();
        const timerElement = document.getElementById('callTimer');
        timerElement.classList.remove('hidden');
        
        callTimer = setInterval(() => {
            const elapsed = Math.floor((Date.now() - callStartTime) / 1000);
            const minutes = Math.floor(elapsed / 60).toString().padStart(2, '0');
            const seconds = (elapsed % 60).toString().padStart(2, '0');
            timerElement.textContent = `${minutes}:${seconds}`;
        }, 1000);
    }

    function updateCallStatus(message, color) {
        const statusElement = document.getElementById('callStatus');
        statusElement.innerHTML = `
            <div class="w-3 h-3 bg-${color}-400 rounded-full animate-pulse"></div>
            <span class="font-semibold">${message}</span>
        `;
    }

    function toggleMicrophone() {
        if (localStream) {
            const audioTrack = localStream.getAudioTracks()[0];
            if (audioTrack) {
                audioTrack.enabled = !audioTrack.enabled;
                isMicMuted = !audioTrack.enabled;
                
                document.getElementById('micOnIcon').classList.toggle('hidden', isMicMuted);
                document.getElementById('micOffIcon').classList.toggle('hidden', !isMicMuted);
                
                const btn = document.getElementById('toggleMicBtn');
                btn.classList.toggle('bg-red-600', isMicMuted);
                btn.classList.toggle('bg-gray-700', !isMicMuted);
            }
        }
    }

    function toggleVideo() {
        if (localStream) {
            const videoTrack = localStream.getVideoTracks()[0];
            if (videoTrack) {
                videoTrack.enabled = !videoTrack.enabled;
                isVideoOff = !videoTrack.enabled;
                
                document.getElementById('videoOnIcon').classList.toggle('hidden', isVideoOff);
                document.getElementById('videoOffIcon').classList.toggle('hidden', !isVideoOff);
                
                const btn = document.getElementById('toggleVideoBtn');
                btn.classList.toggle('bg-red-600', isVideoOff);
                btn.classList.toggle('bg-gray-700', !isVideoOff);
            }
        }
    }

    async function endVideoCall() {
        console.log('User: Ending video call');
        
        // Stop polling
        if (pollingInterval) {
            clearInterval(pollingInterval);
            pollingInterval = null;
        }
        
        // Stop call timer
        if (callTimer) {
            clearInterval(callTimer);
            callTimer = null;
        }
        
        // Update call status in database first
        if (currentCallSessionId) {
            try {
                await fetch('api/video_call.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'end_call',
                        session_id: currentCallSessionId
                    })
                });
                console.log('User: Call ended in database');
            } catch (error) {
                console.error('Error ending call:', error);
            }
        }
        
        // Close peer connection
        if (peerConnection) {
            peerConnection.close();
            peerConnection = null;
        }
        
        // Stop local stream
        if (localStream) {
            localStream.getTracks().forEach(track => track.stop());
            localStream = null;
        }
        
        // Reset video elements
        document.getElementById('localVideo').srcObject = null;
        document.getElementById('remoteVideo').srcObject = null;
        
        // Close modal
        const modal = document.getElementById('videoCallModal');
        modal.classList.remove('show');
        document.body.style.overflow = 'auto';
        
        // Reset state
        currentCallSessionId = null;
        currentDoctorId = null;
        currentDoctorName = null;
        callStartTime = null;
        isMicMuted = false;
        isVideoOff = false;
        
        console.log('User: Video call ended successfully');
    }
</script>

<?php require_once __DIR__ . '/../src/views/partials/footer.php'; ?>
