<?php
/**
 * Doctor Video Calls Page
 * Allows doctors to receive and manage video calls from patients
 */

require_once __DIR__ . '/../src/helpers/Auth.php';
require_once __DIR__ . '/../src/helpers/Database.php';

// Check authentication - only doctors
$auth = new Auth();
$auth->requireLogin();
$auth->requireRole('doctor');

$currentUser = $auth->getCurrentUser();
$db = new Database();
$conn = $db->connect();

// Get call history
$historyStmt = $conn->prepare("
    SELECT vc.*, u.name as user_name, u.avatar as user_avatar
    FROM video_calls vc
    JOIN users u ON vc.user_id = u.id
    WHERE vc.doctor_id = ?
    ORDER BY vc.started_at DESC
    LIMIT 50
");
$historyStmt->execute([$currentUser['id']]);
$callHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<!-- Include Modal System -->
<script src="../public/assets/js/modal.js"></script>

<style>
    .call-card {
        transition: all 0.3s ease;
    }
    
    .call-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }
    
    @keyframes pulse-ring {
        0% {
            transform: scale(0.9);
            opacity: 1;
        }
        100% {
            transform: scale(1.3);
            opacity: 0;
        }
    }
    
    .incoming-call-ring {
        animation: pulse-ring 1.5s cubic-bezier(0.215, 0.61, 0.355, 1) infinite;
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
        background-color: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(10px);
    }
    
    .modal.show {
        display: flex;
        align-items: center;
        justify-content: center;
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
    
    .modal-content {
        animation: slideInDown 0.3s ease-out;
    }
</style>

<div class="container-fluid px-4 py-8">
    <!-- Header -->
    <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Video Calls</h1>
                <p class="text-gray-600">Manage incoming video calls from your patients</p>
            </div>
            <div class="flex items-center gap-4">
                <div id="incomingCallsCount" class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-6 py-3 rounded-xl text-center">
                    <div class="text-3xl font-bold">0</div>
                    <div class="text-sm">Incoming Calls</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Incoming Calls Section -->
    <div id="incomingCallsSection" class="mb-6 hidden">
        <div class="bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-2xl shadow-lg p-6">
            <h2 class="text-2xl font-bold mb-4 flex items-center gap-3">
                <svg class="w-8 h-8 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                </svg>
                Incoming Calls
            </h2>
            <div id="incomingCallsList" class="space-y-4">
                <!-- Incoming calls will be populated here -->
            </div>
        </div>
    </div>

    <!-- Call History -->
    <div class="bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
                <svg class="w-7 h-7 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Call History
            </h2>
            
            <?php if (!empty($callHistory)): ?>
                <button onclick="clearAllHistory()" 
                        class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-semibold transition-all flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Clear All History
                </button>
            <?php endif; ?>
        </div>

        <?php if (empty($callHistory)): ?>
            <div class="text-center py-12">
                <svg class="w-20 h-20 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                <h3 class="text-xl font-bold text-gray-900 mb-2">No Call History</h3>
                <p class="text-gray-600">You haven't received any video calls yet.</p>
            </div>
        <?php else: ?>
            <div class="grid gap-4">
                <?php foreach ($callHistory as $call): 
                    $statusColors = [
                        'active' => 'green',
                        'ended' => 'gray',
                        'rejected' => 'red',
                        'missed' => 'orange',
                        'ringing' => 'yellow'
                    ];
                    $color = $statusColors[$call['status']] ?? 'gray';
                    
                    $statusIcons = [
                        'active' => 'M5 13l4 4L19 7',
                        'ended' => 'M5 13l4 4L19 7',
                        'rejected' => 'M6 18L18 6M6 6l12 12',
                        'missed' => 'M6 18L18 6M6 6l12 12',
                        'ringing' => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9'
                    ];
                    $icon = $statusIcons[$call['status']] ?? 'M5 13l4 4L19 7';
                ?>
                    <div class="call-card bg-gray-50 rounded-xl p-4 flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <!-- Avatar -->
                            <div class="w-14 h-14 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center text-white text-xl font-bold overflow-hidden">
                                <?php if (!empty($call['user_avatar'])): ?>
                                    <img src="../public/<?php echo htmlspecialchars($call['user_avatar']); ?>" 
                                         alt="<?php echo htmlspecialchars($call['user_name']); ?>" 
                                         class="w-full h-full object-cover">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($call['user_name'], 0, 2)); ?>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Call Info -->
                            <div>
                                <h4 class="font-bold text-gray-900 text-lg"><?php echo htmlspecialchars($call['user_name']); ?></h4>
                                <p class="text-sm text-gray-600">
                                    <?php echo date('M d, Y \a\t g:i A', strtotime($call['started_at'])); ?>
                                </p>
                                <?php if ($call['duration_seconds'] > 0): ?>
                                    <p class="text-sm text-gray-500">
                                        Duration: <?php echo gmdate('i:s', $call['duration_seconds']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Status Badge and Delete Button -->
                        <div class="flex items-center gap-3">
                            <span class="px-4 py-2 bg-<?php echo $color; ?>-100 text-<?php echo $color; ?>-700 rounded-full font-semibold text-sm flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo $icon; ?>"/>
                                </svg>
                                <?php echo ucfirst($call['status']); ?>
                            </span>
                            
                            <button onclick="deleteCallHistory(event, <?php echo $call['id']; ?>)" 
                                    class="text-red-600 hover:text-red-700 hover:bg-red-50 p-2 rounded-lg transition-all"
                                    title="Delete this call">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Incoming Call Modal -->
<div id="incomingCallModal" class="modal">
    <div class="modal-content bg-white rounded-3xl shadow-2xl max-w-md w-full mx-4">
        <div class="bg-gradient-to-r from-blue-500 to-purple-600 text-white p-8 rounded-t-3xl text-center relative overflow-hidden">
            <!-- Animated rings -->
            <div class="absolute inset-0 flex items-center justify-center">
                <div class="w-32 h-32 bg-white bg-opacity-20 rounded-full incoming-call-ring"></div>
            </div>
            <div class="absolute inset-0 flex items-center justify-center" style="animation-delay: 0.5s;">
                <div class="w-32 h-32 bg-white bg-opacity-20 rounded-full incoming-call-ring"></div>
            </div>
            
            <div class="relative z-10">
                <svg class="w-16 h-16 mx-auto mb-4 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                </svg>
                <h3 class="text-2xl font-bold mb-2">Incoming Video Call</h3>
                <p id="callerName" class="text-xl font-semibold">Patient Name</p>
            </div>
        </div>
        
        <div class="p-8">
            <div class="flex gap-4 justify-center">
                <button onclick="rejectCall()" 
                        class="flex-1 bg-red-600 hover:bg-red-700 text-white px-6 py-4 rounded-2xl font-bold text-lg flex items-center justify-center gap-2 transition-all">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Decline
                </button>
                
                <button onclick="answerCall()" 
                        class="flex-1 bg-green-600 hover:bg-green-700 text-white px-6 py-4 rounded-2xl font-bold text-lg flex items-center justify-center gap-2 transition-all">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                    </svg>
                    Answer
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Active Video Call Modal -->
<div id="activeCallModal" class="modal">
    <div class="modal-content bg-gray-900 rounded-3xl shadow-2xl max-w-6xl w-full mx-4">
        <div class="relative">
            <!-- Remote Video -->
            <div class="bg-black rounded-t-3xl overflow-hidden relative" style="height: 600px;">
                <video id="remoteVideo" autoplay playsinline class="w-full h-full object-cover"></video>
                
                <!-- Call Info Overlay -->
                <div class="absolute top-4 left-4 bg-black bg-opacity-50 text-white px-4 py-2 rounded-full flex items-center gap-2">
                    <div class="w-3 h-3 bg-green-400 rounded-full"></div>
                    <span class="font-semibold" id="activeCallerName">Patient Name</span>
                </div>
                
                <div class="absolute top-4 right-4 bg-black bg-opacity-50 text-white px-4 py-2 rounded-full font-semibold" id="activeCallTimer">
                    00:00
                </div>
            </div>
            
            <!-- Local Video (PiP) -->
            <div class="absolute bottom-24 right-4 w-64 h-48 bg-gray-800 rounded-xl overflow-hidden shadow-2xl">
                <video id="localVideo" autoplay muted playsinline class="w-full h-full object-cover"></video>
            </div>
            
            <!-- Controls -->
            <div class="bg-gray-800 p-6 rounded-b-3xl flex items-center justify-center gap-4">
                <button id="toggleMicBtn" onclick="toggleMicrophone()" 
                        class="bg-gray-700 hover:bg-gray-600 text-white p-4 rounded-full transition-all">
                    <svg id="micOnIcon" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/>
                    </svg>
                    <svg id="micOffIcon" class="w-6 h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"/>
                    </svg>
                </button>
                
                <button id="toggleVideoBtn" onclick="toggleVideo()" 
                        class="bg-gray-700 hover:bg-gray-600 text-white p-4 rounded-full transition-all">
                    <svg id="videoOnIcon" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    <svg id="videoOffIcon" class="w-6 h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                    </svg>
                </button>
                
                <button onclick="endCall()" 
                        class="bg-red-600 hover:bg-red-700 text-white px-8 py-4 rounded-full transition-all flex items-center gap-2 font-semibold text-lg">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M5 3a2 2 0 00-2 2v1c0 8.284 6.716 15 15 15h1a2 2 0 002-2v-3.28a1 1 0 00-.684-.948l-4.493-1.498a1 1 0 00-1.21.502l-1.13 2.257a11.042 11.042 0 01-5.516-5.517l2.257-1.128a1 1 0 00.502-1.21L9.228 3.683A1 1 0 008.279 3H5z"/>
                    </svg>
                    End Call
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    let pollingInterval = null;
    let currentIncomingCall = null;
    let localStream = null;
    let peerConnection = null;
    let currentSessionId = null;
    let callTimer = null;
    let callStartTime = null;
    let isMicMuted = false;
    let isVideoOff = false;

    const configuration = {
        iceServers: [
            { urls: 'stun:stun.l.google.com:19302' },
            { urls: 'stun:stun1.l.google.com:19302' }
        ]
    };

    // Start polling for incoming calls
    function startPolling() {
        checkIncomingCalls();
        pollingInterval = setInterval(checkIncomingCalls, 3000); // Check every 3 seconds
    }

    async function checkIncomingCalls() {
        try {
            const response = await fetch('../public/api/video_call.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_incoming_calls' })
            });
            
            const result = await response.json();
            
            if (result.success && result.calls.length > 0) {
                displayIncomingCalls(result.calls);
                
                // Show modal for first incoming call if not already shown
                if (!currentIncomingCall && result.calls.length > 0) {
                    showIncomingCallModal(result.calls[0]);
                }
            } else {
                document.getElementById('incomingCallsSection').classList.add('hidden');
                document.getElementById('incomingCallsCount').querySelector('.text-3xl').textContent = '0';
            }
        } catch (error) {
            console.error('Error checking incoming calls:', error);
        }
    }

    function displayIncomingCalls(calls) {
        document.getElementById('incomingCallsCount').querySelector('.text-3xl').textContent = calls.length;
        document.getElementById('incomingCallsSection').classList.remove('hidden');
        
        const listElement = document.getElementById('incomingCallsList');
        listElement.innerHTML = calls.map(call => `
            <div class="bg-white rounded-xl p-4 flex items-center justify-between shadow-lg">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center text-white text-xl font-bold overflow-hidden">
                        ${call.user_avatar ? 
                            `<img src="../public/${call.user_avatar}" alt="${call.user_name}" class="w-full h-full object-cover">` :
                            call.user_name.substring(0, 2).toUpperCase()
                        }
                    </div>
                    <div>
                        <h4 class="font-bold text-gray-900 text-lg">${call.user_name}</h4>
                        <p class="text-sm text-gray-600">Incoming call...</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button onclick="answerCallFromList('${call.session_id}', '${call.user_name}')" 
                            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-semibold">
                        Answer
                    </button>
                    <button onclick="rejectCallFromList('${call.session_id}')" 
                            class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-semibold">
                        Decline
                    </button>
                </div>
            </div>
        `).join('');
    }

    function showIncomingCallModal(call) {
        currentIncomingCall = call;
        document.getElementById('callerName').textContent = call.user_name;
        document.getElementById('incomingCallModal').classList.add('show');
        
        // Play ringtone (optional - would need audio file)
        // const ringtone = new Audio('path/to/ringtone.mp3');
        // ringtone.loop = true;
        // ringtone.play();
    }

    function closeIncomingCallModal() {
        document.getElementById('incomingCallModal').classList.remove('show');
        currentIncomingCall = null;
    }

    async function answerCall() {
        if (!currentIncomingCall) return;
        await answerCallFromList(currentIncomingCall.session_id, currentIncomingCall.user_name);
        closeIncomingCallModal();
    }

    async function answerCallFromList(sessionId, userName) {
        currentSessionId = sessionId;
        
        console.log('Doctor: Attempting to answer call', sessionId);
        
        try {
            // Answer the call
            const response = await fetch('../public/api/video_call.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'answer_call',
                    session_id: sessionId
                })
            });
            
            const result = await response.json();
            console.log('Doctor: Answer call response:', result);
            
            if (result.success) {
                console.log('Browser check:', {
                    hasNavigator: !!navigator,
                    hasMediaDevices: !!navigator.mediaDevices,
                    hasGetUserMedia: !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia),
                    protocol: window.location.protocol,
                    hostname: window.location.hostname
                });
                
                // Check if getUserMedia is available
                if (!navigator.mediaDevices) {
                    alert('Camera/microphone access requires HTTPS.\n\nPlease access this page using:\nhttps://' + window.location.hostname + window.location.pathname + '\n\nCurrent URL: ' + window.location.href);
                    return;
                }
                
                if (!navigator.mediaDevices.getUserMedia) {
                    alert('Your browser does not support getUserMedia. Please update your browser to the latest version.');
                    return;
                }
                
                // Get user media with more flexible constraints
                console.log('Requesting camera and microphone access...');
                
                try {
                    // First, enumerate devices to check what's available
                    const devices = await navigator.mediaDevices.enumerateDevices();
                    console.log('Available devices:', devices);
                    
                    const hasVideo = devices.some(device => device.kind === 'videoinput');
                    const hasAudio = devices.some(device => device.kind === 'audioinput');
                    
                    console.log('Device availability:', { hasVideo, hasAudio });
                    
                    if (!hasVideo && !hasAudio) {
                        alert('No camera or microphone detected.\n\nPlease ensure:\n1. A camera/microphone is connected\n2. Drivers are installed\n3. Other apps are not using them');
                        return;
                    }
                    
                    // Request media with flexible constraints
                    const constraints = {
                        video: hasVideo ? { 
                            width: { ideal: 1280 },
                            height: { ideal: 720 },
                            facingMode: 'user'
                        } : false,
                        audio: hasAudio ? {
                            echoCancellation: true,
                            noiseSuppression: true
                        } : false
                    };
                    
                    if (!hasVideo && !hasAudio) {
                        alert('No camera or microphone found. Please connect a device and try again.');
                        return;
                    }
                    
                    console.log('Requesting with constraints:', constraints);
                    localStream = await navigator.mediaDevices.getUserMedia(constraints);
                    
                    console.log('Media stream obtained:', localStream.getTracks().map(t => ({ kind: t.kind, label: t.label, enabled: t.enabled })));
                    
                } catch (mediaError) {
                    console.error('Media error:', mediaError);
                    
                    // Try again with minimal constraints as fallback
                    console.log('Trying with minimal constraints...');
                    try {
                        localStream = await navigator.mediaDevices.getUserMedia({ 
                            video: true, 
                            audio: true 
                        });
                        console.log('Success with minimal constraints');
                    } catch (fallbackError) {
                        console.error('Fallback also failed:', fallbackError);
                        throw mediaError; // Throw original error
                    }
                }
                
                const localVideo = document.getElementById('localVideo');
                localVideo.srcObject = localStream;
                console.log('Doctor: Local video stream set', {
                    videoTracks: localStream.getVideoTracks().length,
                    audioTracks: localStream.getAudioTracks().length,
                    active: localStream.active
                });
                
                // Verify local video is playing
                localVideo.onloadedmetadata = () => {
                    console.log('Doctor: Local video metadata loaded, dimensions:', localVideo.videoWidth, 'x', localVideo.videoHeight);
                };
                
                // Show active call modal
                document.getElementById('activeCallerName').textContent = userName;
                document.getElementById('activeCallModal').classList.add('show');
                closeIncomingCallModal();
                
                // Establish peer connection first
                await establishPeerConnection();
                
                // Start polling for signals (to receive the offer)
                startSignalPolling();
                
                // Start call timer
                startCallTimer();
            } else {
                alert('Failed to answer call: ' + result.message);
            }
        } catch (error) {
            console.error('Error answering call:', error);
            let errorMsg = 'Error accessing camera/microphone: ' + error.message;
            
            if (error.name === 'NotAllowedError') {
                errorMsg += '\n\nPlease grant camera and microphone permissions to use video calling.';
            } else if (error.name === 'NotFoundError') {
                errorMsg += '\n\nNo camera or microphone found on your device.';
            } else if (error.name === 'NotReadableError') {
                errorMsg += '\n\nCamera/microphone is already in use by another application.';
            } else if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
                errorMsg += '\n\nIMPORTANT: You must use HTTPS!\nPlease access: https://' + window.location.hostname + window.location.pathname;
            }
            
            alert(errorMsg);
        }
    }

    async function rejectCall() {
        if (!currentIncomingCall) return;
        await rejectCallFromList(currentIncomingCall.session_id);
        closeIncomingCallModal();
    }

    async function rejectCallFromList(sessionId) {
        try {
            const response = await fetch('../public/api/video_call.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'reject_call',
                    session_id: sessionId
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                checkIncomingCalls(); // Refresh the list
            }
        } catch (error) {
            console.error('Error rejecting call:', error);
        }
    }

    async function establishPeerConnection() {
        console.log('Doctor: Establishing peer connection');
        peerConnection = new RTCPeerConnection(configuration);
        
        // Handle remote tracks FIRST before adding local tracks
        peerConnection.ontrack = (event) => {
            console.log('Doctor: ===== RECEIVED REMOTE TRACK =====');
            console.log('Doctor: Track kind:', event.track.kind);
            console.log('Doctor: Track state:', event.track.readyState);
            console.log('Doctor: Track enabled:', event.track.enabled);
            console.log('Doctor: Number of streams:', event.streams.length);
            
            const remoteVideo = document.getElementById('remoteVideo');
            
            if (event.streams && event.streams[0]) {
                const stream = event.streams[0];
                console.log('Doctor: Stream details:', {
                    id: stream.id,
                    active: stream.active,
                    videoTracks: stream.getVideoTracks().length,
                    audioTracks: stream.getAudioTracks().length
                });
                
                remoteVideo.srcObject = stream;
                console.log('Doctor: Remote video srcObject set');
                
                // Verify video element
                remoteVideo.onloadedmetadata = () => {
                    console.log('Doctor: Remote video metadata loaded!');
                    console.log('Doctor: Remote video dimensions:', remoteVideo.videoWidth, 'x', remoteVideo.videoHeight);
                };
                
                // Ensure video plays
                remoteVideo.play()
                    .then(() => console.log('Doctor: Remote video playing successfully'))
                    .catch(e => console.error('Doctor: Error playing remote video:', e));
            } else {
                console.warn('Doctor: No stream in track event');
            }
        };
        
        // Add local tracks AFTER setting up handlers
        localStream.getTracks().forEach(track => {
            peerConnection.addTrack(track, localStream);
            console.log('Doctor: Added local track:', track.kind, track.label);
        });
        
        // Handle ICE candidates
        peerConnection.onicecandidate = async (event) => {
            if (event.candidate) {
                console.log('Doctor: Sending ICE candidate');
                await fetch('../public/api/video_call.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'send_signal',
                        session_id: currentSessionId,
                        signal: { ice_candidate: event.candidate }
                    })
                });
            }
        };
        
        // Monitor connection state
        peerConnection.onconnectionstatechange = () => {
            console.log('Doctor: Connection state:', peerConnection.connectionState);
            if (peerConnection.connectionState === 'connected') {
                console.log('Doctor: Peer connection established!');
            } else if (peerConnection.connectionState === 'failed') {
                console.error('Doctor: Connection failed');
            }
        };
        
        peerConnection.oniceconnectionstatechange = () => {
            console.log('Doctor: ICE connection state:', peerConnection.iceConnectionState);
            if (peerConnection.iceConnectionState === 'connected') {
                console.log('Doctor: ICE connection established!');
            }
        };
        
        // Verify tracks were added
        const senders = peerConnection.getSenders();
        console.log('Doctor: Total tracks added to peer connection:', senders.length);
        senders.forEach(sender => {
            if (sender.track) {
                console.log('Doctor: Sender track:', sender.track.kind, sender.track.enabled);
            }
        });
        
        console.log('Doctor: Peer connection established, waiting for offer');
    }

    let signalPollingInterval = null;

    function startSignalPolling() {
        signalPollingInterval = setInterval(async () => {
            try {
                const response = await fetch('../public/api/video_call.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'get_call_status',
                        session_id: currentSessionId
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    if (result.status === 'ended' && peerConnection) {
                        console.log('Call ended by patient');
                        alert('Call ended by patient');
                        endCall();
                        return;
                    } else if (result.signal) {
                        await handleSignal(result.signal);
                    }
                }
            } catch (error) {
                console.error('Signal polling error:', error);
            }
        }, 2000);
    }

    async function handleSignal(signal) {
        console.log('Doctor: Received signal type:', Object.keys(signal));
        
        if (signal.offer && peerConnection) {
            console.log('Doctor: ===== PROCESSING OFFER =====');
            console.log('Doctor: Offer type:', signal.offer.type);
            console.log('Doctor: Offer SDP length:', signal.offer.sdp ? signal.offer.sdp.length : 0);
            console.log('Doctor: Current connection state:', peerConnection.connectionState);
            console.log('Doctor: Current signaling state:', peerConnection.signalingState);
            
            try {
                if (peerConnection.signalingState === 'stable' || peerConnection.signalingState === 'have-remote-offer') {
                    await peerConnection.setRemoteDescription(new RTCSessionDescription(signal.offer));
                    console.log('Doctor: ✓ Remote description (offer) set successfully');
                    console.log('Doctor: Remote tracks expected:', peerConnection.getReceivers().length);
                } else {
                    console.warn('Doctor: Cannot set offer, signaling state is:', peerConnection.signalingState);
                    return;
                }
                
                const answer = await peerConnection.createAnswer();
                console.log('Doctor: Answer created:', answer.type);
                console.log('Doctor: Answer SDP length:', answer.sdp.length);
                
                await peerConnection.setLocalDescription(answer);
                console.log('Doctor: Local description (answer) set successfully');
                
                // Verify local tracks in answer
                const senders = peerConnection.getSenders();
                console.log('Doctor: Tracks in answer:', senders.length);
                senders.forEach(sender => {
                    if (sender.track) {
                        console.log('Doctor: Including track:', sender.track.kind, sender.track.label);
                    }
                });
                
                const response = await fetch('../public/api/video_call.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'send_signal',
                        session_id: currentSessionId,
                        signal: { answer: answer }
                    })
                });
                
                const result = await response.json();
                console.log('Doctor: Answer sent result:', result);
                
                if (result.success) {
                    console.log('Doctor: Answer sent successfully to patient');
                } else {
                    console.error('Doctor: Failed to send answer:', result.message);
                }
            } catch (error) {
                console.error('Doctor: Error handling offer:', error);
                console.error('Doctor: Error details:', error.stack);
            }
        } else if (signal.offer && !peerConnection) {
            console.error('Doctor: ===== RECEIVED OFFER BUT NO PEER CONNECTION =====');
            console.error('Doctor: Peer connection must be established before receiving offer!');
        } else if (signal.ice_candidate && peerConnection) {
            console.log('Doctor: Received ICE candidate from patient');
            try {
                await peerConnection.addIceCandidate(new RTCIceCandidate(signal.ice_candidate));
                console.log('Doctor: ICE candidate added successfully');
            } catch (error) {
                console.error('Doctor: Error adding ICE candidate:', error);
            }
        }
    }

    function startCallTimer() {
        callStartTime = Date.now();
        callTimer = setInterval(() => {
            const elapsed = Math.floor((Date.now() - callStartTime) / 1000);
            const minutes = Math.floor(elapsed / 60).toString().padStart(2, '0');
            const seconds = (elapsed % 60).toString().padStart(2, '0');
            document.getElementById('activeCallTimer').textContent = `${minutes}:${seconds}`;
        }, 1000);
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

    async function endCall() {
        console.log('Doctor: Ending call');
        
        // Stop signal polling
        if (signalPollingInterval) {
            clearInterval(signalPollingInterval);
            signalPollingInterval = null;
        }
        
        // Stop call timer
        if (callTimer) {
            clearInterval(callTimer);
            callTimer = null;
        }
        
        // Update call status in database first
        if (currentSessionId) {
            try {
                await fetch('../public/api/video_call.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'end_call',
                        session_id: currentSessionId
                    })
                });
                console.log('Doctor: Call ended in database');
            } catch (error) {
                console.error('Doctor: Error ending call:', error);
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
        
        // Close modal
        document.getElementById('activeCallModal').classList.remove('show');
        
        // Reset state
        currentSessionId = null;
        isMicMuted = false;
        isVideoOff = false;
        
        console.log('Doctor: Call ended successfully');
        
        // Reload to update history
        setTimeout(() => location.reload(), 1000);
    }

    // Start polling when page loads
    document.addEventListener('DOMContentLoaded', () => {
        // Log browser capabilities
        console.log('Page loaded. Browser capabilities:', {
            userAgent: navigator.userAgent,
            hasMediaDevices: !!navigator.mediaDevices,
            hasGetUserMedia: !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia),
            protocol: window.location.protocol,
            hostname: window.location.hostname,
            isSecureContext: window.isSecureContext
        });
        
        // Check browser compatibility - but don't block, just warn
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            console.error('getUserMedia is not supported in this browser or requires HTTPS');
            
            if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
                const httpsUrl = 'https://' + window.location.host + window.location.pathname;
                const warning = document.createElement('div');
                warning.style.cssText = 'position:fixed;top:0;left:0;right:0;background:#ff6b6b;color:white;padding:15px;text-align:center;z-index:9999;';
                warning.innerHTML = `<strong>⚠️ Video calling requires HTTPS!</strong><br>Please access: <a href="${httpsUrl}" style="color:yellow;text-decoration:underline;">${httpsUrl}</a>`;
                document.body.insertBefore(warning, document.body.firstChild);
            }
        }
        
        startPolling();
    });

    // Stop polling when page unloads
    window.addEventListener('beforeunload', () => {
        if (pollingInterval) {
            clearInterval(pollingInterval);
        }
    });

    // Delete individual call history
    async function deleteCallHistory(event, callId) {
        const confirmed = await AppModal.confirm({
            title: 'Delete Call History',
            message: 'Are you sure you want to delete this call from history? This action cannot be undone.',
            confirmText: 'Delete',
            cancelText: 'Cancel',
            type: 'danger'
        });

        if (!confirmed) return;

        try {
            console.log('Deleting call ID:', callId);
            
            const response = await fetch('../public/api/video_call.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete_call',
                    call_id: callId
                })
            });

            console.log('Response status:', response.status);
            const result = await response.json();
            console.log('Delete response:', result);

            if (result.success) {
                // Remove the card with animation
                const callCard = event.target.closest('.call-card');
                callCard.style.opacity = '0';
                callCard.style.transform = 'translateX(100px)';
                
                await AppModal.alert({
                    title: 'Success',
                    message: 'Call history deleted successfully!',
                    type: 'success',
                    buttonText: 'OK'
                });
                
                setTimeout(() => {
                    callCard.remove();
                    // Reload if no more calls
                    if (document.querySelectorAll('.call-card').length === 0) {
                        location.reload();
                    }
                }, 300);
            } else {
                await AppModal.alert({
                    title: 'Error',
                    message: 'Failed to delete call: ' + result.message,
                    type: 'danger',
                    buttonText: 'OK'
                });
            }
        } catch (error) {
            console.error('Error deleting call:', error);
            await AppModal.alert({
                title: 'Error',
                message: 'Failed to delete call. Please try again.',
                type: 'danger',
                buttonText: 'OK'
            });
        }
    }

    // Clear all call history
    async function clearAllHistory() {
        const confirmed = await AppModal.confirm({
            title: 'Clear All History',
            message: 'Are you sure you want to clear ALL call history? This action cannot be undone and will permanently delete all your call records.',
            confirmText: 'Clear All',
            cancelText: 'Cancel',
            type: 'danger'
        });

        if (!confirmed) return;

        try {
            const response = await fetch('../public/api/video_call.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'clear_all_history'
                })
            });

            const result = await response.json();

            if (result.success) {
                await AppModal.alert({
                    title: 'Success',
                    message: 'All call history cleared successfully!',
                    type: 'success',
                    buttonText: 'OK'
                });
                location.reload();
            } else {
                await AppModal.alert({
                    title: 'Error',
                    message: 'Failed to clear history: ' + result.message,
                    type: 'danger',
                    buttonText: 'OK'
                });
            }
        } catch (error) {
            console.error('Error clearing history:', error);
            await AppModal.alert({
                title: 'Error',
                message: 'Failed to clear history. Please try again.',
                type: 'danger',
                buttonText: 'OK'
            });
        }
    }
</script>

<?php require_once __DIR__ . '/../src/views/partials/footer.php'; ?>
