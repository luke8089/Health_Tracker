<?php
/**
 * Doctor Messages Page
 * Messaging interface for doctors to communicate with patients
 */

require_once __DIR__ . '/../src/helpers/Auth.php';
require_once __DIR__ . '/../src/helpers/Database.php';
require_once __DIR__ . '/../src/helpers/Utils.php';

$auth = new Auth();
$auth->requireLogin();

// Verify doctor role
$currentUser = $auth->getCurrentUser();
if ($currentUser['role'] !== 'doctor') {
    Utils::redirect('../public/dashboard.php', 'Access denied. Doctors only.', 'error');
}

$userId = $currentUser['id'];
$database = new Database();
$db = $database->connect();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'send_message') {
        try {
            $recipientId = intval($_POST['recipient_id']);
            $message = trim($_POST['message']);
            $subject = trim($_POST['subject'] ?? 'Message from Dr. ' . $currentUser['name']);
            
            if (empty($message)) {
                echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
                exit;
            }
            
            $stmt = $db->prepare("
                INSERT INTO messages (sender_id, recipient_id, subject, body, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            $result = $stmt->execute([$userId, $recipientId, $subject, $message]);
            
            if ($result) {
                $messageId = $db->lastInsertId();
                $stmt = $db->prepare("
                    SELECT m.*, u.name as sender_name, u.avatar 
                    FROM messages m 
                    JOIN users u ON m.sender_id = u.id 
                    WHERE m.id = ?
                ");
                $stmt->execute([$messageId]);
                $sentMessage = $stmt->fetch();
                
                echo json_encode([
                    'success' => true, 
                    'message' => $sentMessage,
                    'timestamp' => date('g:i A', strtotime($sentMessage['created_at']))
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to send message']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'get_messages') {
        try {
            $recipientId = intval($_POST['recipient_id']);
            
            $stmt = $db->prepare("
                SELECT m.*, 
                       sender.name as sender_name, 
                       sender.avatar as sender_avatar,
                       sender.role as sender_role,
                       recipient.name as recipient_name
                FROM messages m
                JOIN users sender ON m.sender_id = sender.id
                JOIN users recipient ON m.recipient_id = recipient.id
                WHERE (m.sender_id = ? AND m.recipient_id = ?)
                   OR (m.sender_id = ? AND m.recipient_id = ?)
                ORDER BY m.created_at ASC
            ");
            
            $stmt->execute([$userId, $recipientId, $recipientId, $userId]);
            $messages = $stmt->fetchAll();
            
            // Mark messages as read
            $updateStmt = $db->prepare("
                UPDATE messages 
                SET is_read = 1, read_at = NOW()
                WHERE recipient_id = ? AND sender_id = ? AND is_read = 0
            ");
            $updateStmt->execute([$userId, $recipientId]);
            
            echo json_encode(['success' => true, 'messages' => $messages]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'get_patient_info') {
        try {
            $patientId = intval($_POST['patient_id']);
            
            // SECURITY: First verify doctor is connected to this patient
            $connectionCheck = $db->prepare("
                SELECT COUNT(*) as is_connected 
                FROM user_doctor_connections 
                WHERE user_id = ? AND doctor_id = ? AND status = 'active'
            ");
            $connectionCheck->execute([$patientId, $userId]);
            $isConnected = $connectionCheck->fetch()['is_connected'];
            
            if (!$isConnected) {
                echo json_encode(['success' => false, 'error' => 'Access denied. Not connected to this patient.']);
                exit;
            }
            
            // Get patient details with latest assessment
            $stmt = $db->prepare("
                SELECT u.id, u.name, u.email, u.phone, u.avatar, u.created_at,
                       a.severity as latest_severity,
                       a.score as latest_score,
                       a.created_at as assessment_date,
                       (SELECT COUNT(*) FROM assessments WHERE user_id = u.id) as total_assessments,
                       (SELECT COUNT(*) FROM habits WHERE user_id = u.id AND status = 'active') as active_habits
                FROM users u
                LEFT JOIN assessments a ON u.id = a.user_id
                WHERE u.id = ?
                ORDER BY a.created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$patientId]);
            $patient = $stmt->fetch();
            
            echo json_encode(['success' => true, 'patient' => $patient]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

// Get all patients (users with role='user') who have messaged this doctor or are connected
try {
    $stmt = $db->prepare("
        SELECT DISTINCT u.id, u.name, u.email, u.avatar, u.created_at,
               (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND recipient_id = ? AND is_read = 0) as unread_count,
               (SELECT MAX(created_at) FROM messages 
                WHERE (sender_id = u.id AND recipient_id = ?) 
                   OR (sender_id = ? AND recipient_id = u.id)) as last_message_time,
               (SELECT severity FROM assessments WHERE user_id = u.id ORDER BY created_at DESC LIMIT 1) as latest_severity,
               (SELECT score FROM assessments WHERE user_id = u.id ORDER BY created_at DESC LIMIT 1) as latest_score,
               (SELECT COUNT(*) FROM user_doctor_connections WHERE user_id = u.id AND doctor_id = ? AND status = 'active') as is_connected
        FROM users u
        WHERE u.role = 'user' 
        AND (
            EXISTS (SELECT 1 FROM messages WHERE (sender_id = u.id AND recipient_id = ?) OR (sender_id = ? AND recipient_id = u.id))
            OR EXISTS (SELECT 1 FROM user_doctor_connections WHERE user_id = u.id AND doctor_id = ? AND status = 'active')
        )
        ORDER BY last_message_time DESC, u.name ASC
    ");
    $stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId, $userId]);
    $patients = $stmt->fetchAll();
} catch (Exception $e) {
    $patients = [];
}

// Get pre-selected patient if specified
$selectedPatientId = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

// Get unread message count
$unreadStmt = $db->prepare("
    SELECT COUNT(*) as total_unread 
    FROM messages 
    WHERE recipient_id = ? AND is_read = 0
");
$unreadStmt->execute([$userId]);
$totalUnread = $unreadStmt->fetch()['total_unread'];

$title = "Messages - Doctor Portal";
require_once __DIR__ . '/includes/header.php';
?>

<div class="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-100 pt-4 pb-8">
    <div class="max-w-7xl mx-auto px-4">
        
        <!-- Header -->
        <div class="mb-6 animate-fade-in-up">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl sm:text-4xl font-bold bg-gradient-to-r from-primary to-accent bg-clip-text text-transparent mb-2">
                        Patient Messages
                    </h1>
                    <p class="text-gray-600 text-sm sm:text-base">Communicate with your patients and provide support</p>
                </div>
                <?php if ($totalUnread > 0): ?>
                    <div class="px-4 sm:px-6 py-2.5 sm:py-3 bg-gradient-to-r from-red-500 to-pink-500 text-white rounded-xl shadow-lg animate-pulse flex-shrink-0">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                                <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                            </svg>
                            <span class="font-bold text-sm sm:text-base"><?php echo $totalUnread; ?> Unread Message<?php echo $totalUnread > 1 ? 's' : ''; ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-4 sm:gap-6">
            <!-- Patients Sidebar -->
            <div class="lg:col-span-1 order-2 lg:order-1">
                <div class="bg-white rounded-2xl shadow-xl overflow-hidden animate-slide-in-left">
                    <div class="p-4 sm:p-6 border-b border-gray-200 bg-gradient-to-r from-primary to-accent flex-shrink-0">
                        <div class="flex items-center justify-between text-white">
                            <h2 class="text-xl font-bold">My Patients</h2>
                            <span class="px-3 py-1 bg-white/20 backdrop-blur-sm rounded-full text-sm font-semibold">
                                <?php echo count($patients); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Search Bar -->
                    <div class="p-3 sm:p-4 border-b border-gray-200 flex-shrink-0">
                        <div class="relative">
                            <input 
                                type="text" 
                                id="searchPatients" 
                                placeholder="Search patients..." 
                                class="w-full px-4 py-2.5 sm:py-3 pl-10 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent transition-all text-sm"
                            >
                            <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5 sm:top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                    </div>

                    <!-- Patients List -->
                    <div class="overflow-y-auto" style="max-height: 450px;" id="patientsList">
                        <?php if (empty($patients)): ?>
                            <div class="text-center py-12 px-6 empty-state">
                                <svg class="w-16 h-16 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                                <p class="text-gray-500 font-medium">No patients yet</p>
                                <p class="text-sm text-gray-400 mt-1">Patients will appear here when they contact you</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($patients as $patient): ?>
                                <div 
                                    class="patient-item p-4 border-b border-gray-100 hover:bg-gradient-to-r hover:from-accent/5 hover:to-primary/5 transition-all duration-300 cursor-pointer <?php echo $selectedPatientId === $patient['id'] ? 'bg-gradient-to-r from-accent/10 to-primary/10' : ''; ?>" 
                                    data-patient-id="<?php echo $patient['id']; ?>"
                                    data-patient-name="<?php echo htmlspecialchars($patient['name']); ?>"
                                    data-patient-email="<?php echo htmlspecialchars($patient['email']); ?>"
                                    onclick="selectPatient(<?php echo $patient['id']; ?>)"
                                >
                                    <div class="flex items-start gap-3">
                                        <div class="relative">
                                            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-accent to-primary flex items-center justify-center text-white font-bold text-lg shadow-lg">
                                                <?php if ($patient['avatar']): ?>
                                                    <img src="/health-tracker/public/<?php echo htmlspecialchars($patient['avatar']); ?>" 
                                                         alt="<?php echo htmlspecialchars($patient['name']); ?>"
                                                         class="w-full h-full rounded-full object-cover">
                                                <?php else: ?>
                                                    <?php echo strtoupper(substr($patient['name'], 0, 2)); ?>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($patient['is_connected']): ?>
                                                <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-500 border-2 border-white rounded-full" title="Connected patient"></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-start justify-between gap-2">
                                                <h3 class="font-semibold text-gray-900 truncate"><?php echo htmlspecialchars($patient['name']); ?></h3>
                                                <?php if ($patient['unread_count'] > 0): ?>
                                                    <span class="px-2 py-0.5 bg-red-500 text-white text-xs font-bold rounded-full animate-pulse flex-shrink-0">
                                                        <?php echo $patient['unread_count']; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="flex items-center gap-2 mt-1">
                                                <?php if ($patient['latest_severity']): ?>
                                                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full <?php
                                                        echo $patient['latest_severity'] === 'critical' ? 'bg-red-100 text-red-700' :
                                                            ($patient['latest_severity'] === 'poor' ? 'bg-orange-100 text-orange-700' :
                                                            ($patient['latest_severity'] === 'fair' ? 'bg-yellow-100 text-yellow-700' :
                                                            ($patient['latest_severity'] === 'good' ? 'bg-blue-100 text-blue-700' :
                                                            'bg-green-100 text-green-700')));
                                                    ?>">
                                                        <?php echo ucfirst($patient['latest_severity']); ?>
                                                    </span>
                                                    <span class="text-xs text-gray-500"><?php echo $patient['latest_score']; ?>%</span>
                                                <?php else: ?>
                                                    <span class="text-xs text-gray-400">No assessment</span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <?php if ($patient['last_message_time']): ?>
                                                <p class="text-xs text-gray-400 mt-1">
                                                    Last message: <?php echo date('M j, g:i A', strtotime($patient['last_message_time'])); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Chat Area -->
            <div class="lg:col-span-2 order-1 lg:order-2">
                <div class="bg-white rounded-2xl shadow-xl animate-slide-in-right flex flex-col" style="height: 600px; max-height: calc(100vh - 180px);">
                    <!-- Chat Header -->
                    <div id="chatHeader" class="p-3 sm:p-4 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-white flex-shrink-0">
                        <div class="empty-state text-center py-8">
                            <svg class="w-20 h-20 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                            <h3 class="text-xl font-bold text-gray-400 mb-2">Select a Patient</h3>
                            <p class="text-gray-400">Choose a patient to view conversation and provide support</p>
                        </div>
                    </div>

                    <!-- Messages Container -->
                    <div id="messagesContainer" class="messages-container flex-1 p-4 sm:p-6 overflow-y-auto hidden" style="scroll-behavior: smooth; min-height: 0;">
                        <!-- Messages will be loaded here -->
                    </div>

                    <!-- Message Input -->
                    <div id="messageInput" class="p-3 sm:p-4 border-t border-gray-200 bg-gradient-to-r from-gray-50 to-white hidden flex-shrink-0">
                        <form id="messageForm" class="space-y-2">
                            <input type="hidden" id="recipientId" value="">
                            <div>
                                <textarea 
                                    id="messageText" 
                                    rows="2" 
                                    placeholder="Type your message to the patient..." 
                                    class="w-full px-3 py-2 sm:px-4 sm:py-2 bg-white border-2 border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-accent focus:border-accent resize-none transition-all text-sm"
                                    required
                                ></textarea>
                                <p class="text-xs text-gray-500 mt-1 hidden sm:block">Press Enter to send, Shift+Enter for new line</p>
                            </div>
                            <div class="flex flex-wrap gap-1.5 sm:gap-2">
                                <button 
                                    type="button"
                                    onclick="insertTemplate('followup')"
                                    class="px-2 py-1.5 sm:px-3 bg-gray-100 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-200 transition-all"
                                >
                                    <span class="hidden sm:inline">📋 Follow-up</span>
                                    <span class="sm:hidden">📋</span>
                                </button>
                                <button 
                                    type="button"
                                    onclick="insertTemplate('encouragement')"
                                    class="px-2 py-1.5 sm:px-3 bg-gray-100 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-200 transition-all"
                                >
                                    <span class="hidden sm:inline">⭐ Encouragement</span>
                                    <span class="sm:hidden">⭐</span>
                                </button>
                                <button 
                                    type="button"
                                    onclick="insertTemplate('reminder')"
                                    class="px-2 py-1.5 sm:px-3 bg-gray-100 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-200 transition-all"
                                >
                                    <span class="hidden sm:inline">🔔 Reminder</span>
                                    <span class="sm:hidden">🔔</span>
                                </button>
                                <div class="flex-1"></div>
                                <button 
                                    type="submit" 
                                    class="px-4 py-2 sm:px-6 bg-gradient-to-r from-primary to-accent text-white font-semibold rounded-xl hover:shadow-xl transition-all duration-300 flex items-center gap-2 text-xs sm:text-sm"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                    </svg>
                                    <span class="hidden sm:inline">Send</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes slideInLeft {
    from {
        opacity: 0;
        transform: translateX(-30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-slide-in-left {
    animation: slideInLeft 0.5s ease-out;
}

.animate-slide-in-right {
    animation: slideInRight 0.5s ease-out;
}

.animate-fade-in-up {
    animation: fadeInUp 0.4s ease-out;
}

.patient-item {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.patient-item:hover {
    transform: translateX(5px);
}

.message-bubble {
    animation: fadeInUp 0.3s ease-out;
    max-width: 80%;
    font-size: 0.9rem;
    line-height: 1.5;
}

.message-bubble.sent {
    background: linear-gradient(135deg, #1C2529 0%, #A1D1B1 100%);
    color: white;
    margin-left: auto;
}

.message-bubble.received {
    background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
    color: #1f2937;
}

.messages-container::-webkit-scrollbar {
    width: 6px;
}

.messages-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.messages-container::-webkit-scrollbar-thumb {
    background: #A1D1B1;
    border-radius: 10px;
}

.messages-container::-webkit-scrollbar-thumb:hover {
    background: #1C2529;
}

.empty-state {
    opacity: 0.6;
    transition: opacity 0.3s ease;
}

.empty-state:hover {
    opacity: 1;
}
</style>

<script>
let currentPatientId = null;
let messagePollingInterval = null;

const messageTemplates = {
    followup: "Hello! I'm checking in to see how you're feeling today. Have you noticed any improvements since we last spoke?",
    encouragement: "Great progress! Keep up the excellent work. Remember, small consistent steps lead to big changes. I'm here to support you!",
    reminder: "This is a friendly reminder to complete your daily health assessments and maintain your healthy habits. Consistency is key!"
};

// Select patient
function selectPatient(patientId) {
    currentPatientId = patientId;
    
    // Update active state
    document.querySelectorAll('.patient-item').forEach(item => {
        item.classList.remove('bg-gradient-to-r', 'from-accent/10', 'to-primary/10');
        if (parseInt(item.dataset.patientId) === patientId) {
            item.classList.add('bg-gradient-to-r', 'from-accent/10', 'to-primary/10');
        }
    });
    
    // Get patient info
    const patientElement = document.querySelector(`[data-patient-id="${patientId}"]`);
    const patientName = patientElement.dataset.patientName;
    const patientEmail = patientElement.dataset.patientEmail;
    
    // Fetch detailed patient info
    fetchPatientInfo(patientId, patientName, patientEmail);
    
    // Show message area
    document.getElementById('messagesContainer').classList.remove('hidden');
    document.getElementById('messageInput').classList.remove('hidden');
    document.getElementById('recipientId').value = patientId;
    
    // Load messages
    loadMessages(patientId);
    
    // Start polling for new messages
    if (messagePollingInterval) {
        clearInterval(messagePollingInterval);
    }
    messagePollingInterval = setInterval(() => loadMessages(patientId, true), 5000);
}

// Fetch patient details
async function fetchPatientInfo(patientId, patientName, patientEmail) {
    try {
        const formData = new FormData();
        formData.append('action', 'get_patient_info');
        formData.append('patient_id', patientId);
        
        const response = await fetch('messages.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success && result.patient) {
            updateChatHeader(result.patient);
        } else {
            // Use basic info if detailed fetch fails
            updateChatHeaderBasic(patientName, patientEmail);
        }
    } catch (error) {
        console.error('Error fetching patient info:', error);
        updateChatHeaderBasic(patientName, patientEmail);
    }
}

// Update chat header with detailed info
function updateChatHeader(patient) {
    let statusBadge = '';
    if (patient.latest_severity) {
        const severityColors = {
            critical: 'bg-red-100 text-red-700',
            poor: 'bg-orange-100 text-orange-700',
            fair: 'bg-yellow-100 text-yellow-700',
            good: 'bg-blue-100 text-blue-700',
            excellent: 'bg-green-100 text-green-700'
        };
        statusBadge = `
            <span class="px-2 py-0.5 ${severityColors[patient.latest_severity]} text-xs font-bold rounded-full">
                ${patient.latest_severity.toUpperCase()} - ${patient.latest_score}%
            </span>
        `;
    }
    
    document.getElementById('chatHeader').innerHTML = `
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-4 animate-fade-in-up">
            <div class="flex items-center gap-3 min-w-0 flex-1">
                <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-gradient-to-br from-accent to-primary flex items-center justify-center text-white font-bold text-base sm:text-lg shadow-lg flex-shrink-0">
                    ${patient.name.substring(0, 2).toUpperCase()}
                </div>
                <div class="min-w-0 flex-1">
                    <h2 class="text-base sm:text-lg font-bold text-gray-900 truncate">${patient.name}</h2>
                    <div class="flex items-center gap-2 flex-wrap">
                        ${statusBadge}
                        <span class="text-xs text-gray-500">${patient.total_assessments || 0} assessments</span>
                        <span class="text-xs text-gray-500">🎯 ${patient.active_habits || 0} habits</span>
                    </div>
                </div>
            </div>
            <div class="flex gap-2 flex-shrink-0 w-full sm:w-auto">
                <button onclick="viewPatientHistory(${patient.id})" class="flex-1 sm:flex-none px-3 py-2 bg-blue-100 text-blue-700 rounded-lg font-semibold hover:bg-blue-200 transition-all text-xs" title="View History">
                    <span class="hidden sm:inline">📊 History</span>
                    <span class="sm:hidden">📊</span>
                </button>
                <button onclick="refreshMessages()" class="px-3 py-2 bg-gray-100 text-gray-700 rounded-lg font-semibold hover:bg-gray-200 transition-all" title="Refresh">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                </button>
            </div>
        </div>
    `;
}

// Update chat header with basic info
function updateChatHeaderBasic(name, email) {
    document.getElementById('chatHeader').innerHTML = `
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-4 animate-fade-in-up">
            <div class="flex items-center gap-3 min-w-0 flex-1">
                <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-gradient-to-br from-accent to-primary flex items-center justify-center text-white font-bold text-base sm:text-lg shadow-lg flex-shrink-0">
                    ${name.substring(0, 2).toUpperCase()}
                </div>
                <div class="min-w-0 flex-1">
                    <h2 class="text-base sm:text-lg font-bold text-gray-900 truncate">${name}</h2>
                    <p class="text-xs text-gray-600 truncate">${email}</p>
                </div>
            </div>
            <button onclick="refreshMessages()" class="px-3 py-2 bg-gray-100 text-gray-700 rounded-lg font-semibold hover:bg-gray-200 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
            </button>
        </div>
    `;
}

// Load messages
async function loadMessages(patientId, silent = false) {
    try {
        const formData = new FormData();
        formData.append('action', 'get_messages');
        formData.append('recipient_id', patientId);
        
        const response = await fetch('messages.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            displayMessages(result.messages, silent);
        }
    } catch (error) {
        console.error('Error loading messages:', error);
    }
}

// Display messages
function displayMessages(messages, silent = false) {
    const container = document.getElementById('messagesContainer');
    const currentScrollHeight = container.scrollHeight;
    const currentScrollTop = container.scrollTop;
    const isScrolledToBottom = currentScrollTop + container.clientHeight >= currentScrollHeight - 50;
    
    if (messages.length === 0) {
        container.innerHTML = `
            <div class="text-center py-12 empty-state">
                <svg class="w-16 h-16 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
                <p class="text-gray-400 font-medium">No messages yet</p>
                <p class="text-sm text-gray-400 mt-1">Start the conversation with your patient!</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    let currentDate = '';
    
    messages.forEach(message => {
        const messageDate = new Date(message.created_at);
        const messageDateStr = messageDate.toLocaleDateString();
        
        // Add date separator
        if (messageDateStr !== currentDate) {
            currentDate = messageDateStr;
            html += `
                <div class="text-center my-4">
                    <span class="px-4 py-2 bg-gray-200 text-gray-600 text-xs font-semibold rounded-full">
                        ${messageDateStr === new Date().toLocaleDateString() ? 'Today' : messageDateStr}
                    </span>
                </div>
            `;
        }
        
        const isSent = message.sender_id == <?php echo $userId; ?>;
        const time = messageDate.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
        
        html += `
            <div class="mb-4 ${isSent ? 'text-right' : 'text-left'}">
                <div class="message-bubble ${isSent ? 'sent' : 'received'} inline-block px-5 py-3 rounded-2xl shadow-md">
                    ${!isSent ? `<p class="text-xs font-semibold mb-1 opacity-75">${escapeHtml(message.sender_name)}</p>` : ''}
                    <p class="text-sm leading-relaxed whitespace-pre-wrap">${escapeHtml(message.body)}</p>
                    <p class="text-xs mt-2 opacity-75 flex items-center gap-1 ${isSent ? 'justify-end' : 'justify-start'}">
                        <span>${time}</span>
                        ${isSent && message.is_read ? '<svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/><path fill-rule="evenodd" d="M14.707 5.293a1 1 0 010 1.414l-6 6a1 1 0 01-1.414 0l-2-2a1 1 0 011.414-1.414L8 10.586l5.293-5.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>' : ''}
                    </p>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    
    // Scroll to bottom if user was already at bottom or this is first load
    if (!silent || isScrolledToBottom) {
        container.scrollTop = container.scrollHeight;
    }
}

// Send message
document.getElementById('messageForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const recipientId = document.getElementById('recipientId').value;
    const messageText = document.getElementById('messageText').value.trim();
    
    if (!messageText || !recipientId) return;
    
    try {
        const formData = new FormData();
        formData.append('action', 'send_message');
        formData.append('recipient_id', recipientId);
        formData.append('message', messageText);
        
        const response = await fetch('messages.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            document.getElementById('messageText').value = '';
            loadMessages(recipientId);
        } else {
            alert('Failed to send message: ' + (result.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error sending message:', error);
        alert('Failed to send message');
    }
});

// Insert template
function insertTemplate(type) {
    const textarea = document.getElementById('messageText');
    textarea.value = messageTemplates[type];
    textarea.focus();
}

// Refresh messages
function refreshMessages() {
    if (currentPatientId) {
        loadMessages(currentPatientId);
    }
}

// View patient history
function viewPatientHistory(patientId) {
    window.open(`/health-tracker/doctor/assessments.php?tab=patients&patient=${patientId}`, '_blank');
}

// Search patients
document.getElementById('searchPatients').addEventListener('input', (e) => {
    const searchTerm = e.target.value.toLowerCase();
    document.querySelectorAll('.patient-item').forEach(item => {
        const name = item.dataset.patientName.toLowerCase();
        const email = item.dataset.patientEmail.toLowerCase();
        
        if (name.includes(searchTerm) || email.includes(searchTerm)) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
});

// Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Auto-select patient if specified in URL
<?php if ($selectedPatientId): ?>
    document.addEventListener('DOMContentLoaded', () => {
        selectPatient(<?php echo $selectedPatientId; ?>);
    });
<?php endif; ?>

// Handle Enter key for sending messages (Shift+Enter for new line)
document.getElementById('messageText').addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        document.getElementById('messageForm').dispatchEvent(new Event('submit'));
    }
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (messagePollingInterval) {
        clearInterval(messagePollingInterval);
    }
});
</script>

<?php require_once __DIR__ . '/../src/views/partials/footer.php'; ?>
