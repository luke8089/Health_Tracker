/**
 * Main Application JavaScript
 * Health Tracker
 */

// Global error handler for authentication
window.addEventListener('unhandledrejection', function(event) {
    if (event.reason && event.reason.message && event.reason.message.includes('authentication')) {
        // Get base path from current location
        const pathParts = window.location.pathname.split('/');
        const healthTrackerIndex = pathParts.indexOf('health-tracker');
        const basePath = healthTrackerIndex >= 0 ? '/' + pathParts.slice(1, healthTrackerIndex + 1).join('/') : '';
        window.location.href = basePath + '/landing_page/index.php';
    }
});

// Check for session expiration on page load
document.addEventListener('DOMContentLoaded', function() {
    // Get base path from current location
    const pathParts = window.location.pathname.split('/');
    const healthTrackerIndex = pathParts.indexOf('health-tracker');
    const basePath = healthTrackerIndex >= 0 ? '/' + pathParts.slice(1, healthTrackerIndex + 1).join('/') : '';
    
    // Check if we're on a protected page
    const isProtectedPage = !window.location.pathname.includes('landing_page') && 
                           !window.location.pathname.includes('login') && 
                           !window.location.pathname.includes('register');
    
    if (isProtectedPage) {
        // Ping server to check session validity
        fetch(basePath + '/public/api.php?action=check_session', {
            method: 'POST',
            credentials: 'same-origin'
        })
        .then(response => {
            // Only process if we got a valid response
            if (!response.ok) {
                // API endpoint doesn't exist or errored - don't redirect
                console.warn('Session check endpoint not available, skipping validation');
                return null;
            }
            return response.json();
        })
        .then(data => {
            // Only redirect if we explicitly got a session invalid response
            if (data && !data.success && data.authenticated === false) {
                // Session invalid, redirect to landing page
                window.location.href = basePath + '/landing_page/index.php';
            }
        })
        .catch((error) => {
            // If check fails, don't redirect - the page already has server-side auth
            console.warn('Session check failed:', error);
        });
    }
});

class HealthTrackerApp {
    constructor() {
        // Get base path dynamically
        const pathParts = window.location.pathname.split('/');
        const healthTrackerIndex = pathParts.indexOf('health-tracker');
        const basePath = healthTrackerIndex >= 0 ? '/' + pathParts.slice(1, healthTrackerIndex + 1).join('/') : '';
        
        this.apiBase = basePath + '/public/api.php';
        this.user = null;
        this.init();
    }

    async init() {
        try {
            // Get current user if logged in
            await this.getCurrentUser();

            // Initialize components
            this.initializeNotifications();
            this.initializeFormValidation();
            this.initializeProgressBars();
            this.bindEventListeners();

            // Load page-specific functionality
            this.loadPageSpecificFeatures();
        } catch (error) {
            console.error('App initialization failed:', error);
        }
    }

    // API Methods
    async apiCall(endpoint, options = {}) {
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

        try {
            const response = await fetch(`${this.apiBase}?endpoint=${endpoint}`, config);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            console.error('API call failed:', error);
            this.showNotification('Network error. Please check your connection.', 'error');
            throw error;
        }
    }

    async getCurrentUser() {
        try {
            const response = await this.apiCall('auth/user');
            if (response.success) {
                this.user = response.data;
            }
        } catch (error) {
            // User not logged in - this is okay
        }
    }

    // Habit Management
    async loadHabits() {
        try {
            this.showLoading();
            const response = await this.apiCall('habits');

            if (response.success) {
                this.renderHabits(response.data);
            } else {
                this.showNotification('Failed to load habits', 'error');
            }
        } catch (error) {
            console.error('Failed to load habits:', error);
        } finally {
            this.hideLoading();
        }
    }

    async createHabit(habitData) {
        try {
            const response = await this.apiCall('habits', {
                method: 'POST',
                data: habitData
            });

            if (response.success) {
                this.showNotification('Habit created successfully!', 'success');
                await this.loadHabits(); // Refresh the list
                return true;
            } else {
                this.showNotification(response.message || 'Failed to create habit', 'error');
                return false;
            }
        } catch (error) {
            console.error('Failed to create habit:', error);
            return false;
        }
    }

    async incrementHabitStreak(habitId) {
        try {
            const response = await this.apiCall(`habits/increment?id=${habitId}`, {
                method: 'POST'
            });

            if (response.success) {
                this.showNotification('Great job! Streak updated!', 'success');
                await this.loadHabits(); // Refresh to show updated streak
            } else {
                this.showNotification('Failed to update streak', 'error');
            }
        } catch (error) {
            console.error('Failed to increment streak:', error);
        }
    }

    renderHabits(habits) {
        const container = document.getElementById('habits-container');
        if (!container) return;

        if (habits.length === 0) {
            container.innerHTML = `
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No habits yet</h3>
                    <p class="mt-1 text-sm text-gray-500">Get started by creating your first habit.</p>
                    <div class="mt-6">
                        <button onclick="openHabitModal()" class="btn-primary">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            Add Habit
                        </button>
                    </div>
                </div>
            `;
            return;
        }

        container.innerHTML = habits.map(habit => `
            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow duration-200">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">${this.escapeHtml(habit.name)}</h3>
                    <span class="px-3 py-1 text-sm rounded-full ${this.getFrequencyClass(habit.frequency)}">
                        ${this.capitalizeFirst(habit.frequency)}
                    </span>
                </div>
                
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center space-x-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-accent">${habit.streak}</div>
                            <div class="text-xs text-gray-500">Day Streak</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-success">${habit.points}</div>
                            <div class="text-xs text-gray-500">Points</div>
                        </div>
                    </div>
                    
                    <button 
                        onclick="app.incrementHabitStreak(${habit.id})"
                        class="btn-primary flex items-center space-x-2"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>Mark Done</span>
                    </button>
                </div>
                
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-gradient-to-r from-blue-500 to-purple-600 h-2 rounded-full transition-all duration-300" 
                         style="width: ${Math.min((habit.streak / 30) * 100, 100)}%"></div>
                </div>
                <div class="text-xs text-gray-500 mt-1">${habit.streak}/30 days to gold streak</div>
            </div>
        `).join('');
    }

    // Activity Management
    async loadActivities() {
        try {
            this.showLoading();
            const response = await this.apiCall('activities?limit=10');

            if (response.success) {
                this.renderActivities(response.data);
            } else {
                this.showNotification('Failed to load activities', 'error');
            }
        } catch (error) {
            console.error('Failed to load activities:', error);
        } finally {
            this.hideLoading();
        }
    }

    async logActivity(activityData) {
        try {
            const response = await this.apiCall('activities', {
                method: 'POST',
                data: activityData
            });

            if (response.success) {
                this.showNotification('Activity logged successfully!', 'success');
                await this.loadActivities(); // Refresh the list
                return true;
            } else {
                this.showNotification(response.message || 'Failed to log activity', 'error');
                return false;
            }
        } catch (error) {
            console.error('Failed to log activity:', error);
            return false;
        }
    }

    renderActivities(activities) {
        const container = document.getElementById('activities-container');
        if (!container) return;

        if (activities.length === 0) {
            container.innerHTML = `
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No activities yet</h3>
                    <p class="mt-1 text-sm text-gray-500">Start logging your physical activities.</p>
                    <div class="mt-6">
                        <button onclick="openActivityModal()" class="btn-primary">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            Log Activity
                        </button>
                    </div>
                </div>
            `;
            return;
        }

        container.innerHTML = activities.map(activity => `
            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow duration-200">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-lg font-semibold text-gray-900">${this.escapeHtml(activity.type)}</h3>
                    <span class="text-sm text-gray-500">${this.formatDate(activity.created_at)}</span>
                </div>
                
                <div class="grid grid-cols-2 gap-4 mt-4">
                    <div class="text-center p-3 bg-blue-50 rounded-lg">
                        <div class="text-2xl font-bold text-blue-600">${activity.duration_minutes}</div>
                        <div class="text-xs text-blue-600">Minutes</div>
                    </div>
                    <div class="text-center p-3 bg-red-50 rounded-lg">
                        <div class="text-2xl font-bold text-red-600">${activity.calories_burned}</div>
                        <div class="text-xs text-red-600">Calories</div>
                    </div>
                </div>
            </div>
        `).join('');
    }

    // Assessment Management
    async loadAssessmentQuestions() {
        try {
            const response = await this.apiCall('assessments/questions');
            if (response.success) {
                return response.data;
            }
            return {};
        } catch (error) {
            console.error('Failed to load assessment questions:', error);
            return {};
        }
    }

    async submitAssessment(responses) {
        try {
            this.showLoading();
            const response = await this.apiCall('assessments', {
                method: 'POST',
                data: { responses }
            });

            if (response.success) {
                this.showNotification('Assessment completed successfully!', 'success');
                return response.data;
            } else {
                this.showNotification(response.message || 'Failed to submit assessment', 'error');
                return null;
            }
        } catch (error) {
            console.error('Failed to submit assessment:', error);
            return null;
        } finally {
            this.hideLoading();
        }
    }

    // Dashboard Stats
    async loadDashboardStats() {
        try {
            const response = await this.apiCall('dashboard/stats');
            if (response.success) {
                this.renderDashboardStats(response.data);
            }
        } catch (error) {
            console.error('Failed to load dashboard stats:', error);
        }
    }

    renderDashboardStats(stats) {
        const elements = {
            'habits-count': stats.habits || 0,
            'activities-count': stats.activities || 0,
            'points-count': stats.points || 0,
            'tier-badge': stats.tier || 'bronze'
        };

        Object.entries(elements).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                if (id === 'tier-badge') {
                    element.textContent = this.capitalizeFirst(value);
                    element.className = `px-3 py-1 text-sm rounded-full ${this.getTierClass(value)}`;
                } else {
                    element.textContent = this.formatNumber(value);
                }
            }
        });
    }

    // UI Helper Methods
    showLoading() {
        document.getElementById('loadingSpinner')?.classList.remove('hidden');
    }

    hideLoading() {
        document.getElementById('loadingSpinner')?.classList.add('hidden');
    }

    showNotification(message, type = 'info', duration = 5000) {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <div class="flex items-center justify-between">
                <p class="font-medium">${this.escapeHtml(message)}</p>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, duration);
    }

    // Utility Methods
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    capitalizeFirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    formatDate(dateString) {
        const options = {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };
        return new Date(dateString).toLocaleDateString('en-US', options);
    }

    formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    getFrequencyClass(frequency) {
        const classes = {
            daily: 'bg-green-100 text-green-800',
            weekly: 'bg-blue-100 text-blue-800',
            monthly: 'bg-purple-100 text-purple-800'
        };
        return classes[frequency] || 'bg-gray-100 text-gray-800';
    }

    getTierClass(tier) {
        const classes = {
            bronze: 'bg-orange-100 text-orange-800',
            silver: 'bg-gray-100 text-gray-800',
            gold: 'bg-yellow-100 text-yellow-800'
        };
        return classes[tier] || 'bg-gray-100 text-gray-800';
    }

    // Form Validation
    initializeFormValidation() {
        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (form.classList.contains('validate-form')) {
                e.preventDefault();
                if (this.validateForm(form)) {
                    form.submit();
                }
            }
        });
    }

    validateForm(form) {
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('border-red-500');
                isValid = false;
            } else {
                field.classList.remove('border-red-500');
                field.classList.add('border-green-500');
            }
        });

        if (!isValid) {
            this.showNotification('Please fill in all required fields', 'error');
        }

        return isValid;
    }

    // Progress Bars Animation
    initializeProgressBars() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    const progressBar = entry.target.querySelector('.progress-bar');
                    if (progressBar) {
                        const width = progressBar.dataset.width || '0';
                        progressBar.style.width = width + '%';
                    }
                }
            });
        });

        document.querySelectorAll('.progress-container').forEach((el) => {
            observer.observe(el);
        });
    }

    // Event Listeners
    bindEventListeners() {
        // Global keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.metaKey) {
                switch (e.key) {
                    case 'k': // Ctrl+K for search
                        e.preventDefault();
                        const searchInput = document.querySelector('input[type="search"]');
                        if (searchInput) searchInput.focus();
                        break;
                }
            }
        });

        // Auto-resize textareas
        document.addEventListener('input', (e) => {
            if (e.target.tagName === 'TEXTAREA') {
                e.target.style.height = 'auto';
                e.target.style.height = e.target.scrollHeight + 'px';
            }
        });

        // Click outside to close dropdowns
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.classList.add('hidden');
                });
            }
        });
    }

    // Page-specific features
    loadPageSpecificFeatures() {
        const pageName = document.body.dataset.page;

        switch (pageName) {
            case 'dashboard':
                this.loadDashboardStats();
                break;
            case 'habits':
                this.loadHabits();
                break;
            case 'activities':
                this.loadActivities();
                break;
            case 'assessment':
                this.initializeAssessmentForm();
                break;
        }
    }

    async initializeAssessmentForm() {
        const questions = await this.loadAssessmentQuestions();
        const container = document.getElementById('assessment-questions');

        if (container && questions) {
            this.renderAssessmentForm(questions, container);
        }
    }

    renderAssessmentForm(questions, container) {
        const questionsHtml = Object.entries(questions).map(([key, question], index) => `
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    ${index + 1}. ${question.question}
                </h3>
                
                ${question.type === 'scale' ? `
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm text-gray-500">
                            <span>${question.labels[0]}</span>
                            <span>${question.labels[1]}</span>
                        </div>
                        <input type="range" name="${key}" min="${question.min}" max="${question.max}" 
                               value="${Math.floor((question.max + question.min) / 2)}" 
                               class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                        <div class="text-center">
                            <span class="text-lg font-medium text-accent" data-range-value="${key}">
                                ${Math.floor((question.max + question.min) / 2)}
                            </span>
                        </div>
                    </div>
                ` : `
                    <input type="number" name="${key}" min="${question.min}" max="${question.max}" 
                           class="input-field" placeholder="Enter number" required>
                `}
            </div>
        `).join('');

        container.innerHTML = questionsHtml;

        // Add range input listeners
        container.querySelectorAll('input[type="range"]').forEach(input => {
            const valueDisplay = container.querySelector(`[data-range-value="${input.name}"]`);
            input.addEventListener('input', () => {
                if (valueDisplay) {
                    valueDisplay.textContent = input.value;
                }
            });
        });
    }
}

// Initialize app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.app = new HealthTrackerApp();
});

// Global utility functions for backward compatibility
window.HealthTracker = {
    showLoading: () => window.app?.showLoading(),
    hideLoading: () => window.app?.hideLoading(),
    showNotification: (message, type, duration) => window.app?.showNotification(message, type, duration),
    formatDate: (date) => window.app?.formatDate(date),
    formatNumber: (num) => window.app?.formatNumber(num)
};