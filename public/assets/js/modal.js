/**
 * Reusable Modal System
 * Provides confirmation, alert, and custom modal dialogs
 */

class Modal {
    constructor() {
        this.modals = new Map();
        this.init();
    }

    init() {
        // Create modal container if it doesn't exist
        if (!document.getElementById('modal-container')) {
            const container = document.createElement('div');
            container.id = 'modal-container';
            document.body.appendChild(container);
        }
    }

    /**
     * Show a confirmation dialog
     * @param {Object} options - Configuration options
     * @param {string} options.title - Modal title
     * @param {string} options.message - Modal message
     * @param {string} options.confirmText - Confirm button text (default: "Confirm")
     * @param {string} options.cancelText - Cancel button text (default: "Cancel")
     * @param {string} options.type - Modal type: 'danger', 'warning', 'info', 'success' (default: 'info')
     * @param {Function} options.onConfirm - Callback when confirmed
     * @param {Function} options.onCancel - Callback when cancelled
     * @returns {Promise} - Resolves to true if confirmed, false if cancelled
     */
    confirm(options) {
        return new Promise((resolve) => {
            const {
                title = 'Confirm Action',
                message = 'Are you sure?',
                confirmText = 'Confirm',
                cancelText = 'Cancel',
                type = 'info',
                onConfirm = null,
                onCancel = null
            } = options;

            const modalId = 'modal-' + Date.now();
            
            // Color schemes based on type
            const colorSchemes = {
                danger: {
                    gradient: 'from-red-500 to-pink-600',
                    confirmBtn: 'bg-red-600 hover:bg-red-700',
                    icon: `<svg class="w-16 h-16 mx-auto mb-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>`
                },
                warning: {
                    gradient: 'from-orange-500 to-yellow-600',
                    confirmBtn: 'bg-orange-600 hover:bg-orange-700',
                    icon: `<svg class="w-16 h-16 mx-auto mb-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>`
                },
                info: {
                    gradient: 'from-blue-500 to-indigo-600',
                    confirmBtn: 'bg-blue-600 hover:bg-blue-700',
                    icon: `<svg class="w-16 h-16 mx-auto mb-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>`
                },
                success: {
                    gradient: 'from-green-500 to-emerald-600',
                    confirmBtn: 'bg-green-600 hover:bg-green-700',
                    icon: `<svg class="w-16 h-16 mx-auto mb-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>`
                }
            };

            const scheme = colorSchemes[type] || colorSchemes.info;

            const modalHTML = `
                <div id="${modalId}" class="fixed inset-0 z-[9999] flex items-center justify-center" style="display: none;">
                    <!-- Backdrop -->
                    <div class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm transition-opacity modal-backdrop"></div>
                    
                    <!-- Modal -->
                    <div class="relative bg-white rounded-3xl shadow-2xl max-w-md w-full mx-4 transform transition-all modal-content" style="opacity: 0; transform: scale(0.9);">
                        <!-- Header -->
                        <div class="bg-gradient-to-r ${scheme.gradient} text-white p-8 rounded-t-3xl text-center">
                            ${scheme.icon}
                            <h3 class="text-2xl font-bold">${this.escapeHtml(title)}</h3>
                        </div>
                        
                        <!-- Body -->
                        <div class="p-8">
                            <p class="text-gray-700 text-center text-lg mb-6">${this.escapeHtml(message)}</p>
                            
                            <!-- Buttons -->
                            <div class="flex gap-3">
                                <button class="modal-cancel flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-3 rounded-xl font-semibold transition-all">
                                    ${this.escapeHtml(cancelText)}
                                </button>
                                <button class="modal-confirm flex-1 ${scheme.confirmBtn} text-white px-6 py-3 rounded-xl font-semibold transition-all">
                                    ${this.escapeHtml(confirmText)}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Insert modal into container
            const container = document.getElementById('modal-container');
            container.insertAdjacentHTML('beforeend', modalHTML);

            const modalElement = document.getElementById(modalId);
            const backdrop = modalElement.querySelector('.modal-backdrop');
            const content = modalElement.querySelector('.modal-content');
            const confirmBtn = modalElement.querySelector('.modal-confirm');
            const cancelBtn = modalElement.querySelector('.modal-cancel');

            // Show modal with animation
            modalElement.style.display = 'flex';
            setTimeout(() => {
                content.style.opacity = '1';
                content.style.transform = 'scale(1)';
            }, 10);

            // Handle confirm
            const handleConfirm = () => {
                this.closeModal(modalElement);
                if (onConfirm) onConfirm();
                resolve(true);
            };

            // Handle cancel
            const handleCancel = () => {
                this.closeModal(modalElement);
                if (onCancel) onCancel();
                resolve(false);
            };

            // Event listeners
            confirmBtn.addEventListener('click', handleConfirm);
            cancelBtn.addEventListener('click', handleCancel);
            backdrop.addEventListener('click', handleCancel);

            // ESC key to close
            const escHandler = (e) => {
                if (e.key === 'Escape') {
                    handleCancel();
                    document.removeEventListener('keydown', escHandler);
                }
            };
            document.addEventListener('keydown', escHandler);

            // Store modal reference
            this.modals.set(modalId, { element: modalElement, escHandler });
        });
    }

    /**
     * Show an alert dialog
     * @param {Object} options - Configuration options
     * @param {string} options.title - Modal title
     * @param {string} options.message - Modal message
     * @param {string} options.buttonText - Button text (default: "OK")
     * @param {string} options.type - Modal type: 'danger', 'warning', 'info', 'success'
     * @param {Function} options.onClose - Callback when closed
     * @returns {Promise}
     */
    alert(options) {
        return new Promise((resolve) => {
            const {
                title = 'Alert',
                message = '',
                buttonText = 'OK',
                type = 'info',
                onClose = null
            } = options;

            const modalId = 'modal-' + Date.now();
            
            const colorSchemes = {
                danger: { gradient: 'from-red-500 to-pink-600', btn: 'bg-red-600 hover:bg-red-700' },
                warning: { gradient: 'from-orange-500 to-yellow-600', btn: 'bg-orange-600 hover:bg-orange-700' },
                info: { gradient: 'from-blue-500 to-indigo-600', btn: 'bg-blue-600 hover:bg-blue-700' },
                success: { gradient: 'from-green-500 to-emerald-600', btn: 'bg-green-600 hover:bg-green-700' }
            };

            const scheme = colorSchemes[type] || colorSchemes.info;

            const modalHTML = `
                <div id="${modalId}" class="fixed inset-0 z-[9999] flex items-center justify-center" style="display: none;">
                    <div class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm modal-backdrop"></div>
                    <div class="relative bg-white rounded-3xl shadow-2xl max-w-md w-full mx-4 modal-content" style="opacity: 0; transform: scale(0.9);">
                        <div class="bg-gradient-to-r ${scheme.gradient} text-white p-6 rounded-t-3xl">
                            <h3 class="text-xl font-bold text-center">${this.escapeHtml(title)}</h3>
                        </div>
                        <div class="p-6">
                            <p class="text-gray-700 text-center mb-6">${this.escapeHtml(message)}</p>
                            <button class="modal-ok w-full ${scheme.btn} text-white px-6 py-3 rounded-xl font-semibold transition-all">
                                ${this.escapeHtml(buttonText)}
                            </button>
                        </div>
                    </div>
                </div>
            `;

            const container = document.getElementById('modal-container');
            container.insertAdjacentHTML('beforeend', modalHTML);

            const modalElement = document.getElementById(modalId);
            const content = modalElement.querySelector('.modal-content');
            const okBtn = modalElement.querySelector('.modal-ok');

            modalElement.style.display = 'flex';
            setTimeout(() => {
                content.style.opacity = '1';
                content.style.transform = 'scale(1)';
            }, 10);

            const handleClose = () => {
                this.closeModal(modalElement);
                if (onClose) onClose();
                resolve();
            };

            okBtn.addEventListener('click', handleClose);
        });
    }

    /**
     * Close a modal with animation
     */
    closeModal(modalElement) {
        const content = modalElement.querySelector('.modal-content');
        content.style.opacity = '0';
        content.style.transform = 'scale(0.9)';
        
        setTimeout(() => {
            modalElement.remove();
        }, 300);
    }

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Create global instance
window.AppModal = new Modal();

// Export for use in modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = Modal;
}
