<?php
/**
 * Messages Page
 * Modern messaging interface for users to communicate with doctors and admins
 */

require_once __DIR__ . '/../src/helpers/Auth.php';
require_once __DIR__ . '/../src/helpers/Database.php';

$auth = new Auth();
$auth->requireLogin();
$currentUser = $auth->getCurrentUser();
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
            $subject = trim($_POST['subject'] ?? 'Message');
            
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
                // Get the sent message
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
                SET is_read = 1 
                WHERE recipient_id = ? AND sender_id = ? AND is_read = 0
            ");
            $updateStmt->execute([$userId, $recipientId]);
            
            echo json_encode(['success' => true, 'messages' => $messages]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'mark_read') {
        try {
            $recipientId = intval($_POST['recipient_id']);
            
            $stmt = $db->prepare("
                UPDATE messages 
                SET is_read = 1 
                WHERE recipient_id = ? AND sender_id = ?
            ");
            
            $stmt->execute([$userId, $recipientId]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

// Get all doctors and admins for the contact list
try {
    $stmt = $db->prepare("
        SELECT u.id, u.name, u.email, u.role, u.avatar,
               d.specialty as specialization,
               (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND recipient_id = ? AND is_read = 0) as unread_count,
               (SELECT MAX(created_at) FROM messages 
                WHERE (sender_id = u.id AND recipient_id = ?) 
                   OR (sender_id = ? AND recipient_id = u.id)) as last_message_time
        FROM users u
        LEFT JOIN doctors d ON u.id = d.id
        WHERE u.role IN ('doctor', 'admin') AND u.id != ?
        ORDER BY last_message_time DESC, u.name ASC
    ");
    $stmt->execute([$userId, $userId, $userId, $userId]);
    $contacts = $stmt->fetchAll();
} catch (Exception $e) {
    $contacts = [];
}

// Get pre-selected doctor if specified
$selectedDoctorId = isset($_GET['doctor']) ? intval($_GET['doctor']) : null;

$pageTitle = 'Messages';
require_once __DIR__ . '/../src/views/partials/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Health Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '#1f2937',
                        'accent': '#34d399'
                    }
                }
            }
        }
    </script>
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
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
        
        .contact-item {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }
        
        .contact-item:hover {
            transform: translateX(5px);
            background: linear-gradient(135deg, rgba(161, 209, 177, 0.1) 0%, rgba(28, 37, 41, 0.05) 100%);
        }
        
        .contact-item.active {
            background: linear-gradient(135deg, #34d399 0%, #1f2937 100%);
            color: white;
            transform: scale(1.02);
        }
        
        .message-bubble {
            animation: fadeInUp 0.3s ease-out;
            max-width: 70%;
        }
        
        .message-bubble.sent {
            background: linear-gradient(135deg, #34d399 0%, #1f2937 100%);
            color: white;
            margin-left: auto;
        }
        
        .message-bubble.received {
            background: #f3f4f6;
            color: #1f2937;
        }
        
        .messages-container {
            height: calc(100vh - 350px);
            overflow-y: auto;
            scroll-behavior: smooth;
        }
        
        .messages-container::-webkit-scrollbar {
            width: 6px;
        }
        
        .messages-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .messages-container::-webkit-scrollbar-thumb {
            background: #34d399;
            border-radius: 10px;
        }
        
        .messages-container::-webkit-scrollbar-thumb:hover {
            background: #1f2937;
        }
        
        .typing-indicator {
            display: inline-flex;
            gap: 4px;
        }
        
        .typing-indicator span {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #34d399;
            animation: typing 1.4s infinite;
        }
        
        .typing-indicator span:nth-child(2) {
            animation-delay: 0.2s;
        }
        
        .typing-indicator span:nth-child(3) {
            animation-delay: 0.4s;
        }
        
        @keyframes typing {
            0%, 60%, 100% {
                transform: translateY(0);
                opacity: 0.7;
            }
            30% {
                transform: translateY(-10px);
                opacity: 1;
            }
        }
        
        .gentle-glow {
            animation: gentleGlow 3s ease-in-out infinite;
        }
        
        @keyframes gentleGlow {
            0%, 100% {
                box-shadow: 0 0 5px rgba(239, 68, 68, 0.3);
                transform: scale(1);
            }
            50% {
                box-shadow: 0 0 15px rgba(239, 68, 68, 0.5);
                transform: scale(1.05);
            }
        }
        
        .breathe-dot {
            animation: breathe 4s ease-in-out infinite;
        }
        
        @keyframes breathe {
            0%, 100% {
                opacity: 0.8;
                transform: scale(1);
            }
            50% {
                opacity: 1;
                transform: scale(1.1);
            }
        }
        
        .avatar-online::after {
            content: '';
            position: absolute;
            bottom: 0;
            right: 0;
            width: 12px;
            height: 12px;
            background: #10b981;
            border: 2px solid white;
            border-radius: 50%;
            animation: breathe 4s ease-in-out infinite;
        }
        
        .empty-state {
            opacity: 0.6;
            transition: opacity 0.3s ease;
        }
        
        .empty-state:hover {
            opacity: 1;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen pt-20">
    <div class="container mx-auto px-4 py-8 max-w-7xl">
        <!-- Header -->
        <div class="mb-8 animate-fade-in-up">
            <h1 class="text-4xl font-bold text-gray-900 mb-2">Messages</h1>
            <p class="text-gray-600">Connect with healthcare professionals and get support</p>
        </div>

        <div class="grid md:grid-cols-3 gap-6">
            <!-- Contacts Sidebar -->
            <div class="md:col-span-1">
                <div class="glass-card rounded-2xl p-6 animate-slide-in-left">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-gray-900">Healthcare Team</h2>
                        <span class="px-3 py-1 bg-green-400/20 text-green-400 rounded-full text-sm font-semibold">
                            <?php echo count($contacts); ?>
                        </span>
                    </div>

                    <!-- Search Bar -->
                    <div class="mb-4">
                        <div class="relative">
                            <input 
                                type="text" 
                                id="searchContacts" 
                                placeholder="Search contacts..." 
                                class="w-full px-4 py-3 pl-10 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent transition-all"
                            >
                            <svg class="w-5 h-5 text-gray-400 absolute left-3 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                    </div>

                    <!-- Contacts List -->
                    <div class="space-y-2 max-h-[600px] overflow-y-auto" id="contactsList">
                        <?php if (empty($contacts)): ?>
                            <div class="text-center py-8 empty-state">
                                <svg class="w-16 h-16 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                                <p class="text-gray-500 font-medium">No contacts available</p>
                                <p class="text-sm text-gray-400 mt-1">Healthcare professionals will appear here</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($contacts as $contact): ?>
                                <div 
                                    class="contact-item p-4 rounded-xl border border-gray-100 hover:border-green-400/50 <?php echo $selectedDoctorId === $contact['id'] ? 'active' : ''; ?>" 
                                    data-contact-id="<?php echo $contact['id']; ?>"
                                    data-contact-name="<?php echo htmlspecialchars($contact['name']); ?>"
                                    data-contact-role="<?php echo htmlspecialchars($contact['role']); ?>"
                                    data-contact-specialty="<?php echo htmlspecialchars($contact['specialization'] ?? 'General'); ?>"
                                    onclick="selectContact(<?php echo $contact['id']; ?>)"
                                >
                                    <div class="flex items-center gap-3">
                                        <div class="relative avatar-online">
                                            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-accent to-primary flex items-center justify-center text-white font-bold text-lg">
                                                <?php echo strtoupper(substr($contact['name'], 0, 2)); ?>
                                            </div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between">
                                                <h3 class="font-semibold text-gray-900 truncate"><?php echo htmlspecialchars($contact['name']); ?></h3>
                                                <?php if ($contact['unread_count'] > 0): ?>
                                                    <span class="px-2 py-0.5 bg-red-500 text-white text-xs font-bold rounded-full gentle-glow">
                                                        <?php echo $contact['unread_count']; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex items-center gap-2 mt-1">
                                                <?php if ($contact['role'] === 'doctor'): ?>
                                                    <span class="px-2 py-0.5 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full">
                                                        Doctor
                                                    </span>
                                                <?php else: ?>
                                                    <span class="px-2 py-0.5 bg-purple-100 text-purple-700 text-xs font-semibold rounded-full">
                                                        Admin
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($contact['specialization']): ?>
                                                    <span class="text-xs text-gray-500 truncate">
                                                        <?php echo htmlspecialchars($contact['specialization']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Chat Area -->
            <div class="md:col-span-2">
                <div class="glass-card rounded-2xl animate-slide-in-right flex flex-col" style="height: calc(100vh - 200px);">
                    <!-- Chat Header -->
                    <div id="chatHeader" class="p-6 border-b border-gray-200">
                        <div class="empty-state text-center py-8">
                            <svg class="w-20 h-20 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                            <h3 class="text-xl font-bold text-gray-400 mb-2">Select a Contact</h3>
                            <p class="text-gray-400">Choose a healthcare professional to start messaging</p>
                        </div>
                    </div>

                    <!-- Messages Container -->
                    <div id="messagesContainer" class="messages-container flex-1 p-6 hidden">
                        <!-- Messages will be loaded here -->
                    </div>

                    <!-- Message Input -->
                    <div id="messageInput" class="p-6 border-t border-gray-200 hidden">
                        <form id="messageForm" class="flex gap-3">
                            <input type="hidden" id="recipientId" value="">
                            <div class="flex-1">
                                <textarea 
                                    id="messageText" 
                                    rows="2" 
                                    placeholder="Type your message..." 
                                    class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent resize-none transition-all"
                                    required
                                ></textarea>
                            </div>
                            <button 
                                type="submit" 
                                class="px-6 py-3 bg-gradient-to-r from-accent to-primary text-white font-semibold rounded-xl hover:shadow-lg transition-all duration-300 transform hover:scale-105 flex items-center gap-2"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                </svg>
                                Send
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentRecipientId = null;
        let messagePollingInterval = null;

        // Select contact
        function selectContact(contactId) {
            currentRecipientId = contactId;
            
            // Update active state
            document.querySelectorAll('.contact-item').forEach(item => {
                item.classList.remove('active');
                if (parseInt(item.dataset.contactId) === contactId) {
                    item.classList.add('active');
                }
            });
            
            // Get contact info
            const contactElement = document.querySelector(`[data-contact-id="${contactId}"]`);
            const contactName = contactElement.dataset.contactName;
            const contactRole = contactElement.dataset.contactRole;
            const contactSpecialty = contactElement.dataset.contactSpecialty;
            
            // Update chat header
            document.getElementById('chatHeader').innerHTML = `
                <div class="flex items-center justify-between animate-fade-in-up">
                    <div class="flex items-center gap-4">
                        <div class="relative avatar-online">
                            <div class="w-14 h-14 rounded-full bg-gradient-to-br from-accent to-primary flex items-center justify-center text-white font-bold text-xl">
                                ${contactName.substring(0, 2).toUpperCase()}
                            </div>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900">${contactName}</h2>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="px-2 py-0.5 ${contactRole === 'doctor' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'} text-xs font-semibold rounded-full">
                                    ${contactRole === 'doctor' ? 'Doctor' : 'Admin'}
                                </span>
                                ${contactSpecialty !== 'null' ? `<span class="text-sm text-gray-500">${contactSpecialty}</span>` : ''}
                                <span class="flex items-center gap-1 text-sm text-green-600">
                                    <span class="w-2 h-2 bg-green-500 rounded-full breathe-dot"></span>
                                    Online
                                </span>
                            </div>
                        </div>
                    </div>
                    <button onclick="refreshMessages()" class="p-2 hover:bg-gray-100 rounded-lg transition-all">
                        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                    </button>
                </div>
            `;
            
            // Show message area
            document.getElementById('messagesContainer').classList.remove('hidden');
            document.getElementById('messageInput').classList.remove('hidden');
            document.getElementById('recipientId').value = contactId;
            
            // Load messages
            loadMessages(contactId);
            
            // Start polling for new messages
            if (messagePollingInterval) {
                clearInterval(messagePollingInterval);
            }
            messagePollingInterval = setInterval(() => loadMessages(contactId, true), 3000);
        }

        // Load messages
        async function loadMessages(recipientId, silent = false) {
            try {
                const formData = new FormData();
                formData.append('action', 'get_messages');
                formData.append('recipient_id', recipientId);
                
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
                        <p class="text-sm text-gray-400 mt-1">Start the conversation!</p>
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
                        <div class="message-bubble ${isSent ? 'sent' : 'received'} inline-block px-5 py-3 rounded-2xl shadow-sm">
                            ${!isSent ? `<p class="text-xs font-semibold mb-1 opacity-75">${message.sender_name}</p>` : ''}
                            <p class="text-sm leading-relaxed whitespace-pre-wrap">${escapeHtml(message.body)}</p>
                            <p class="text-xs mt-2 opacity-75">${time}</p>
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
                formData.append('subject', 'Message');
                
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

        // Refresh messages
        function refreshMessages() {
            if (currentRecipientId) {
                loadMessages(currentRecipientId);
            }
        }

        // Search contacts
        document.getElementById('searchContacts').addEventListener('input', (e) => {
            const searchTerm = e.target.value.toLowerCase();
            document.querySelectorAll('.contact-item').forEach(item => {
                const name = item.dataset.contactName.toLowerCase();
                const role = item.dataset.contactRole.toLowerCase();
                const specialty = item.dataset.contactSpecialty.toLowerCase();
                
                if (name.includes(searchTerm) || role.includes(searchTerm) || specialty.includes(searchTerm)) {
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

        // Auto-select doctor if specified in URL
        <?php if ($selectedDoctorId): ?>
            document.addEventListener('DOMContentLoaded', () => {
                selectContact(<?php echo $selectedDoctorId; ?>);
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
</body>
</html>

<?php require_once __DIR__ . '/../src/views/partials/footer.php'; ?>
