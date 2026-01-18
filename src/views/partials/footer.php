    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-auto">
        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <!-- Company Info -->
                <div class="col-span-1 md:col-span-2">
                    <div class="flex items-center space-x-2 mb-4">
                        <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                        </div>
                        <span class="text-xl gradient-text" style="font-family: 'Pacifico', cursive; font-weight: 400;">Health Tracker</span>
                    </div>
                    <p class="text-gray-600 text-sm mb-4">
                        Empowering your wellness journey with personalized health insights, habit tracking, and professional guidance.
                    </p>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-400 hover:text-accent transition-colors duration-200">
                            <span class="sr-only">Facebook</span>
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M20 10C20 4.477 15.523 0 10 0S0 4.477 0 10c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V10h2.54V7.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V10h2.773l-.443 2.89h-2.33v6.988C16.343 19.128 20 14.991 20 10z" clip-rule="evenodd"/>
                            </svg>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-accent transition-colors duration-200">
                            <span class="sr-only">Twitter</span>
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M6.29 18.251c7.547 0 11.675-6.253 11.675-11.675 0-.178 0-.355-.012-.53A8.348 8.348 0 0020 3.92a8.19 8.19 0 01-2.357.646 4.118 4.118 0 001.804-2.27 8.224 8.224 0 01-2.605.996 4.107 4.107 0 00-6.993 3.743 11.65 11.65 0 01-8.457-4.287 4.106 4.106 0 001.27 5.477A4.073 4.073 0 01.8 7.713v.052a4.105 4.105 0 003.292 4.022 4.095 4.095 0 01-1.853.07 4.108 4.108 0 003.834 2.85A8.233 8.233 0 010 16.407a11.616 11.616 0 006.29 1.84"/>
                            </svg>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-accent transition-colors duration-200">
                            <span class="sr-only">LinkedIn</span>
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.338 16.338H13.67V12.16c0-.995-.017-2.277-1.387-2.277-1.39 0-1.601 1.086-1.601 2.207v4.248H8.014v-8.59h2.559v1.174h.037c.356-.675 1.227-1.387 2.526-1.387 2.703 0 3.203 1.778 3.203 4.092v4.711zM5.005 6.575a1.548 1.548 0 11-.003-3.096 1.548 1.548 0 01.003 3.096zm-1.337 9.763H6.34v-8.59H3.667v8.59zM17.668 1H2.328C1.595 1 1 1.581 1 2.298v15.403C1 18.418 1.595 19 2.328 19h15.34c.734 0 1.332-.582 1.332-1.299V2.298C19 1.581 18.402 1 17.668 1z" clip-rule="evenodd"/>
                            </svg>
                        </a>
                    </div>
                </div>

                <!-- Quick Links -->
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 tracking-wider uppercase mb-4">Quick Links</h3>
                    <ul class="space-y-3">
                        <?php if ($auth->isLoggedIn()): ?>
                            <li><a href="/dashboard.php" class="text-sm text-gray-600 hover:text-accent transition-colors duration-200">Dashboard</a></li>
                            <li><a href="/assessment.php" class="text-sm text-gray-600 hover:text-accent transition-colors duration-200">Health Assessment</a></li>
                            <li><a href="/habits.php" class="text-sm text-gray-600 hover:text-accent transition-colors duration-200">My Habits</a></li>
                            <li><a href="/activities.php" class="text-sm text-gray-600 hover:text-accent transition-colors duration-200">Activities</a></li>
                        <?php else: ?>
                            <li><a href="/index.php" class="text-sm text-gray-600 hover:text-accent transition-colors duration-200">Home</a></li>
                            <li><a href="/register.php" class="text-sm text-gray-600 hover:text-accent transition-colors duration-200">Get Started</a></li>
                            <li><a href="/login.php" class="text-sm text-gray-600 hover:text-accent transition-colors duration-200">Sign In</a></li>
                        <?php endif; ?>
                        <li><a href="#" class="text-sm text-gray-600 hover:text-accent transition-colors duration-200">About Us</a></li>
                    </ul>
                </div>

                <!-- Support -->
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 tracking-wider uppercase mb-4">Support</h3>
                    <ul class="space-y-3">
                        <li><a href="#" class="text-sm text-gray-600 hover:text-accent transition-colors duration-200">Help Center</a></li>
                        <li><a href="#" class="text-sm text-gray-600 hover:text-accent transition-colors duration-200">Privacy Policy</a></li>
                        <li><a href="#" class="text-sm text-gray-600 hover:text-accent transition-colors duration-200">Terms of Service</a></li>
                        <li><a href="#" class="text-sm text-gray-600 hover:text-accent transition-colors duration-200">Contact Us</a></li>
                        <?php if ($auth->isLoggedIn()): ?>
                            <li><a href="/messages.php" class="text-sm text-gray-600 hover:text-accent transition-colors duration-200">Send Message</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <!-- Bottom Bar -->
            <div class="mt-8 pt-8 border-t border-gray-200">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <p class="text-sm text-gray-500">
                        © <?php echo date('Y'); ?> Health Tracker. All rights reserved.
                    </p>
                    <div class="mt-4 md:mt-0 flex items-center space-x-6">
                        <span class="text-sm text-gray-500">
                            Made with ❤️ for better health
                        </span>
                        <?php if ($auth->isLoggedIn() && $currentUser['role'] === 'user'): ?>
                            <div class="flex items-center space-x-2 text-sm text-gray-500">
                                <span>Your Status:</span>
                                <span class="px-2 py-1 bg-success text-white text-xs rounded-full">
                                    Active
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Loading Spinner (Hidden by default) -->
    <div id="loadingSpinner" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-6 flex items-center space-x-4">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-accent"></div>
            <span class="text-gray-700">Loading...</span>
        </div>
    </div>

    <?php 
    // Only show AI Chatbox for regular users (not for doctors)
    // Check if user is logged in and is NOT a doctor
    $showChatbot = !$auth->isLoggedIn() || ($auth->isLoggedIn() && $currentUser['role'] !== 'doctor');
    if ($showChatbot): 
    ?>
    <!-- AI Chatbox -->
    <div id="aiChatboxContainer" class="fixed bottom-6 right-6 z-50">
        <!-- Chat Toggle Button -->
        <button id="aiChatToggleBtn" class="bg-gradient-to-r from-primary to-accent hover:from-primary-light hover:to-accent text-white rounded-full w-16 h-16 flex items-center justify-center shadow-2xl hover:shadow-3xl transition-all duration-300 transform hover:scale-110 hover:rotate-12 relative group">
            <svg id="aiChatIcon" class="w-7 h-7 transition-transform duration-300 group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
            </svg>
            <svg id="aiCloseIcon" class="w-7 h-7 hidden transition-transform duration-300 group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            <span class="absolute -top-1 -right-1 w-4 h-4 bg-green-500 rounded-full border-2 border-white animate-pulse"></span>
        </button>

        <!-- Chat Window -->
        <div id="aiChatWindow" class="hidden absolute bottom-20 right-0 w-96 bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden transition-all duration-300 transform origin-bottom-right">
            <!-- Chat Header -->
            <div class="bg-gradient-to-r from-primary to-accent text-white p-4 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center backdrop-blur-sm">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-lg">Health Assistant</h3>
                        <p class="text-xs text-white/80 flex items-center">
                            <span class="w-2 h-2 bg-green-400 rounded-full mr-1 animate-pulse"></span>
                            Online • AI Powered
                        </p>
                    </div>
                </div>
                <button id="aiChatMinimizeBtn" class="text-white hover:bg-white/10 rounded-lg p-1 transition-colors duration-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                    </svg>
                </button>
            </div>

            <!-- Chat Messages -->
            <div id="aiChatMessages" class="h-96 overflow-y-auto p-4 space-y-4 bg-gray-50">
                <!-- Welcome Message -->
                <div class="flex items-start space-x-2 animate-fade-in">
                    <div class="w-8 h-8 bg-gradient-to-r from-primary to-accent rounded-full flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                        </svg>
                    </div>
                    <div class="bg-white rounded-2xl rounded-tl-none p-3 shadow-sm max-w-xs">
                        <p class="text-sm text-gray-800">👋 Hello! I'm your AI Health Assistant. I can help answer questions about health, wellness, and this platform. How can I assist you today?</p>
                    </div>
                </div>
            </div>

            <!-- Chat Input -->
            <div class="p-4 bg-white border-t border-gray-200">
                <div class="flex items-end space-x-2">
                    <div class="flex-1 relative">
                        <textarea 
                            id="aiChatInput" 
                            rows="1" 
                            placeholder="Type your message..." 
                            class="w-full resize-none border border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent transition-all duration-200 text-sm"
                            style="max-height: 120px;"
                        ></textarea>
                        <div id="aiTypingIndicator" class="hidden absolute bottom-2 left-3 text-xs text-gray-400 italic">
                            AI is typing...
                        </div>
                    </div>
                    <button 
                        id="aiChatSendBtn" 
                        class="bg-gradient-to-r from-primary to-accent hover:from-primary-light hover:to-accent text-white rounded-xl p-3 transition-all duration-300 transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed flex-shrink-0"
                        disabled
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                    </button>
                </div>
                <p class="text-xs text-gray-400 mt-2 text-center">Powered by AI • Free Service</p>
            </div>
        </div>
    </div>

    <style>
        /* Chatbox Custom Styles */
        #aiChatMessages::-webkit-scrollbar {
            width: 6px;
        }
        
        #aiChatMessages::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        #aiChatMessages::-webkit-scrollbar-thumb {
            background: #A1D1B1;
            border-radius: 10px;
        }
        
        #aiChatMessages::-webkit-scrollbar-thumb:hover {
            background: #1C2529;
        }

        #aiChatInput::-webkit-scrollbar {
            width: 4px;
        }
        
        #aiChatInput::-webkit-scrollbar-track {
            background: transparent;
        }
        
        #aiChatInput::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 10px;
        }

        .ai-chat-message {
            animation: aiSlideInUp 0.3s ease-out;
        }

        @keyframes aiSlideInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in {
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .typing-dots {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .typing-dots span {
            width: 6px;
            height: 6px;
            background: #1C2529;
            border-radius: 50%;
            animation: typing-bounce 1.4s infinite ease-in-out;
        }

        .typing-dots span:nth-child(1) {
            animation-delay: -0.32s;
        }

        .typing-dots span:nth-child(2) {
            animation-delay: -0.16s;
        }

        @keyframes typing-bounce {
            0%, 80%, 100% {
                transform: scale(0);
            }
            40% {
                transform: scale(1);
            }
        }
    </style>
    <?php endif; // End chatbot display condition ?>

    <!-- Global JavaScript -->
    <script src="<?php echo publicUrl('assets/js/app.js'); ?>"></script>
    
    <?php if ($showChatbot): ?>
    <!-- AI Chatbot JavaScript -->
    <script src="<?php echo publicUrl('assets/js/chatbot.js'); ?>"></script>
    <?php endif; ?>
    
    <!-- Page-specific JavaScript (if exists) -->
    <?php if (isset($pageJs)): ?>
        <script src="<?php echo $pageJs; ?>"></script>
    <?php endif; ?>

    <script>
        // Global utility functions
        window.HealthTracker = {
            // Show loading spinner
            showLoading: function() {
                document.getElementById('loadingSpinner').classList.remove('hidden');
            },

            // Hide loading spinner
            hideLoading: function() {
                document.getElementById('loadingSpinner').classList.add('hidden');
            },

            // Show notification
            showNotification: function(message, type = 'info', duration = 5000) {
                const notification = document.createElement('div');
                notification.className = `notification ${type}`;
                notification.innerHTML = `
                    <div class="flex items-center justify-between">
                        <p class="font-medium">${message}</p>
                        <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                `;
                
                document.body.appendChild(notification);
                
                // Auto-remove after duration
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, duration);
            },

            // Format date for display
            formatDate: function(dateString) {
                const options = { 
                    year: 'numeric', 
                    month: 'short', 
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                };
                return new Date(dateString).toLocaleDateString('en-US', options);
            },

            // Format number with commas
            formatNumber: function(num) {
                return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            },

            // Confirm action
            confirm: function(message, callback) {
                if (confirm(message)) {
                    callback();
                }
            },

            // AJAX helper
            ajax: function(url, options = {}) {
                const defaultOptions = {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                };

                const config = { ...defaultOptions, ...options };

                if (config.data && config.method !== 'GET') {
                    config.body = JSON.stringify(config.data);
                }

                return fetch(url, config)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .catch(error => {
                        console.error('Ajax error:', error);
                        this.showNotification('An error occurred. Please try again.', 'error');
                        throw error;
                    });
            }
        };

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape key closes modals and dropdowns
            if (e.key === 'Escape') {
                // Close any open dropdowns
                const dropdowns = document.querySelectorAll('[x-data]');
                dropdowns.forEach(dropdown => {
                    if (dropdown.__x && dropdown.__x.$data.open) {
                        dropdown.__x.$data.open = false;
                    }
                });
            }
        });

        // Auto-resize textareas
        document.addEventListener('input', function(e) {
            if (e.target.tagName === 'TEXTAREA') {
                e.target.style.height = 'auto';
                e.target.style.height = e.target.scrollHeight + 'px';
            }
        });

        // Form validation helper
        function validateForm(formId) {
            const form = document.getElementById(formId);
            if (!form) return false;

            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('border-red-500');
                    isValid = false;
                } else {
                    field.classList.remove('border-red-500');
                }
            });

            return isValid;
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Add smooth scrolling to anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        });
    </script>

    <?php if (isset($additionalFooterContent)): ?>
        <?php echo $additionalFooterContent; ?>
    <?php endif; ?>

</body>
</html>