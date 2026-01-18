/**
 * AI Chatbot JavaScript Handler
 * Manages chat interface, message handling, and API communication
 */

class HealthChatbot {
    constructor() {
        console.log('🔧 HealthChatbot constructor called');
        console.log('🔍 Looking for elements...');
        
        this.chatWindow = document.getElementById('aiChatWindow');
        this.chatMessages = document.getElementById('aiChatMessages');
        this.chatInput = document.getElementById('aiChatInput');
        this.chatSendBtn = document.getElementById('aiChatSendBtn');
        this.chatToggleBtn = document.getElementById('aiChatToggleBtn');
        this.chatMinimizeBtn = document.getElementById('aiChatMinimizeBtn');
        this.chatIcon = document.getElementById('aiChatIcon');
        this.closeIcon = document.getElementById('aiCloseIcon');
        this.typingIndicator = document.getElementById('aiTypingIndicator');
        
        console.log('📋 Elements found:');
        console.log('  - chatWindow:', !!this.chatWindow);
        console.log('  - chatMessages:', !!this.chatMessages);
        console.log('  - chatInput:', !!this.chatInput);
        console.log('  - chatSendBtn:', !!this.chatSendBtn);
        console.log('  - chatToggleBtn:', !!this.chatToggleBtn);
        
        this.isOpen = false;
        this.conversationHistory = [];
        // Build absolute API URL
        const currentPath = window.location.pathname;
        const publicIndex = currentPath.indexOf('/public/');
        let basePath = '';
        if (publicIndex !== -1) {
            basePath = currentPath.substring(0, publicIndex);
        }
        this.apiUrl = basePath + '/public/api/chat.php';
        
        this.init();
    }
    
    init() {
        // Check if elements exist
        if (!this.chatWindow || !this.chatToggleBtn) {
            console.error('AI Chatbot: Required elements not found!');
            console.error('chatWindow:', this.chatWindow);
            console.error('chatToggleBtn:', this.chatToggleBtn);
            console.error('Make sure the footer.php is included in the page.');
            return;
        }
        
        console.log('✅ AI Chatbot: All elements found, initializing...');
        console.log('📍 API URL:', this.apiUrl);
        
        // Event listeners
        this.chatToggleBtn.addEventListener('click', () => {
            console.log('🖱️ Chat button clicked');
            this.toggleChat();
        });
        this.chatMinimizeBtn.addEventListener('click', () => this.toggleChat());
        this.chatSendBtn.addEventListener('click', () => this.sendMessage());
        
        // Input handling
        this.chatInput.addEventListener('input', () => this.handleInputChange());
        this.chatInput.addEventListener('keydown', (e) => this.handleKeyPress(e));
        
        // Auto-resize textarea
        this.chatInput.addEventListener('input', () => this.autoResizeTextarea());
        
        // Load conversation from sessionStorage
        this.loadConversation();
        
        console.log('Health Chatbot initialized');
    }
    
    toggleChat() {
        this.isOpen = !this.isOpen;
        console.log('🔄 Toggle chat - isOpen:', this.isOpen);
        
        if (this.isOpen) {
            console.log('📂 Opening chat window...');
            this.chatWindow.classList.remove('hidden');
            this.chatWindow.style.transform = 'scale(1)';
            this.chatWindow.style.opacity = '1';
            this.chatIcon.classList.add('hidden');
            this.closeIcon.classList.remove('hidden');
            this.chatInput.focus();
            
            // Mark messages as read
            this.scrollToBottom();
            console.log('✅ Chat window opened');
        } else {
            console.log('📁 Closing chat window...');
            this.chatWindow.style.transform = 'scale(0.95)';
            this.chatWindow.style.opacity = '0';
            setTimeout(() => {
                this.chatWindow.classList.add('hidden');
                console.log('✅ Chat window closed');
            }, 200);
            this.chatIcon.classList.remove('hidden');
            this.closeIcon.classList.add('hidden');
        }
    }
    
    handleInputChange() {
        const hasText = this.chatInput.value.trim().length > 0;
        this.chatSendBtn.disabled = !hasText;
    }
    
    handleKeyPress(e) {
        // Send on Enter (without Shift)
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (this.chatInput.value.trim()) {
                this.sendMessage();
            }
        }
    }
    
    autoResizeTextarea() {
        this.chatInput.style.height = 'auto';
        this.chatInput.style.height = Math.min(this.chatInput.scrollHeight, 120) + 'px';
    }
    
    async sendMessage() {
        const message = this.chatInput.value.trim();
        if (!message) return;
        
        // Disable input while processing
        this.chatInput.disabled = true;
        this.chatSendBtn.disabled = true;
        
        // Add user message to chat
        this.addMessage(message, 'user');
        
        // Clear input
        this.chatInput.value = '';
        this.chatInput.style.height = 'auto';
        
        // Show typing indicator
        this.showTypingIndicator();
        
        try {
            // Send to API
            const response = await this.callChatAPI(message);
            
            // Hide typing indicator
            this.hideTypingIndicator();
            
            // Add bot response
            if (response.success) {
                this.addMessage(response.message, 'bot');
            } else {
                console.error('❌ API returned error:', response);
                this.addMessage('Sorry, I encountered an error. Please try again.', 'bot', true);
            }
        } catch (error) {
            console.error('❌ Chat error:', error);
            console.error('Error details:', {
                message: error.message,
                apiUrl: this.apiUrl,
                currentPath: window.location.pathname
            });
            this.hideTypingIndicator();
            this.addMessage('Sorry, I\'m having trouble connecting to the chat service. The API might not be accessible at: ' + this.apiUrl, 'bot', true);
        }
        
        // Re-enable input
        this.chatInput.disabled = false;
        this.chatInput.focus();
    }
    
    async callChatAPI(message) {
        console.log('📡 Calling API:', this.apiUrl);
        console.log('📤 Sending message:', message);
        
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    message: message,
                    history: this.conversationHistory.slice(-5) // Send last 5 messages for context
                })
            });
            
            console.log('📥 Response status:', response.status);
            
            if (!response.ok) {
                console.error('❌ HTTP error! status:', response.status);
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            console.log('✅ API Response:', data);
            
            return data;
        } catch (error) {
            console.error('❌ API Error:', error);
            throw error;
        }
    }
    
    addMessage(text, sender, isError = false) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `flex items-start space-x-2 ai-chat-message ${sender === 'user' ? 'justify-end' : ''}`;
        
        if (sender === 'bot') {
            // Bot message
            messageDiv.innerHTML = `
                <div class="w-8 h-8 bg-gradient-to-r from-primary to-accent rounded-full flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                    </svg>
                </div>
                <div class="bg-white rounded-2xl rounded-tl-none p-3 shadow-sm max-w-xs ${isError ? 'border border-red-300' : ''}">
                    <p class="text-sm text-gray-800 whitespace-pre-wrap">${this.escapeHtml(text)}</p>
                    <span class="text-xs text-gray-400 mt-1 block">${this.getCurrentTime()}</span>
                </div>
            `;
        } else {
            // User message
            messageDiv.innerHTML = `
                <div class="bg-gradient-to-r from-primary to-accent rounded-2xl rounded-tr-none p-3 shadow-sm max-w-xs">
                    <p class="text-sm text-white whitespace-pre-wrap">${this.escapeHtml(text)}</p>
                    <span class="text-xs text-white/70 mt-1 block text-right">${this.getCurrentTime()}</span>
                </div>
            `;
        }
        
        this.chatMessages.appendChild(messageDiv);
        this.scrollToBottom();
        
        // Store in conversation history
        this.conversationHistory.push({
            sender: sender,
            text: text,
            timestamp: Date.now()
        });
        
        // Save to sessionStorage
        this.saveConversation();
    }
    
    showTypingIndicator() {
        const typingDiv = document.createElement('div');
        typingDiv.id = 'aiTypingIndicatorMessage';
        typingDiv.className = 'flex items-start space-x-2 ai-chat-message';
        typingDiv.innerHTML = `
            <div class="w-8 h-8 bg-gradient-to-r from-primary to-accent rounded-full flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                </svg>
            </div>
            <div class="bg-white rounded-2xl rounded-tl-none p-3 shadow-sm">
                <div class="typing-dots">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        `;
        
        this.chatMessages.appendChild(typingDiv);
        this.scrollToBottom();
    }
    
    hideTypingIndicator() {
        const typingDiv = document.getElementById('aiTypingIndicatorMessage');
        if (typingDiv) {
            typingDiv.remove();
        }
    }
    
    scrollToBottom() {
        setTimeout(() => {
            this.chatMessages.scrollTop = this.chatMessages.scrollHeight;
        }, 100);
    }
    
    getCurrentTime() {
        const now = new Date();
        return now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    saveConversation() {
        try {
            // Keep only last 20 messages
            const recentHistory = this.conversationHistory.slice(-20);
            sessionStorage.setItem('aiHealthChatHistory', JSON.stringify(recentHistory));
        } catch (e) {
            console.warn('Failed to save conversation:', e);
        }
    }
    
    loadConversation() {
        try {
            const saved = sessionStorage.getItem('aiHealthChatHistory');
            if (saved) {
                this.conversationHistory = JSON.parse(saved);
                
                // Restore messages (limit to last 10)
                const recentMessages = this.conversationHistory.slice(-10);
                recentMessages.forEach(msg => {
                    if (msg.sender && msg.text) {
                        this.addMessageFromHistory(msg.text, msg.sender);
                    }
                });
            }
        } catch (e) {
            console.warn('Failed to load conversation:', e);
        }
    }
    
    addMessageFromHistory(text, sender) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `flex items-start space-x-2 ${sender === 'user' ? 'justify-end' : ''}`;
        
        if (sender === 'bot') {
            messageDiv.innerHTML = `
                <div class="w-8 h-8 bg-gradient-to-r from-primary to-accent rounded-full flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                    </svg>
                </div>
                <div class="bg-white rounded-2xl rounded-tl-none p-3 shadow-sm max-w-xs">
                    <p class="text-sm text-gray-800 whitespace-pre-wrap">${this.escapeHtml(text)}</p>
                </div>
            `;
        } else {
            messageDiv.innerHTML = `
                <div class="bg-gradient-to-r from-primary to-accent rounded-2xl rounded-tr-none p-3 shadow-sm max-w-xs">
                    <p class="text-sm text-white whitespace-pre-wrap">${this.escapeHtml(text)}</p>
                </div>
            `;
        }
        
        this.chatMessages.appendChild(messageDiv);
    }
    
    clearChat() {
        // Keep only the welcome message
        const welcomeMessage = this.chatMessages.querySelector('.animate-fade-in');
        this.chatMessages.innerHTML = '';
        if (welcomeMessage) {
            this.chatMessages.appendChild(welcomeMessage);
        }
        this.conversationHistory = [];
        this.saveConversation();
    }
}

// Initialize chatbot when DOM is ready
console.log('🤖 AI Chatbot script loaded');

if (document.readyState === 'loading') {
    console.log('⏳ Document still loading, waiting for DOMContentLoaded...');
    document.addEventListener('DOMContentLoaded', () => {
        console.log('✅ DOMContentLoaded fired');
        try {
            window.aiHealthChatbot = new HealthChatbot();
            console.log('✅ AI Health Chatbot initialized successfully');
        } catch (error) {
            console.error('❌ Failed to initialize AI Health Chatbot:', error);
        }
    });
} else {
    console.log('✅ Document already loaded, initializing immediately');
    try {
        window.aiHealthChatbot = new HealthChatbot();
        console.log('✅ AI Health Chatbot initialized successfully');
    } catch (error) {
        console.error('❌ Failed to initialize AI Health Chatbot:', error);
    }
}
