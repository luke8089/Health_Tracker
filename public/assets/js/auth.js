/**
 * Authentication JavaScript
 * Health Tracker
 */

class AuthManager {
    constructor() {
        // Get base path dynamically
        const pathParts = window.location.pathname.split('/');
        const healthTrackerIndex = pathParts.indexOf('health-tracker');
        const basePath = healthTrackerIndex >= 0 ? '/' + pathParts.slice(1, healthTrackerIndex + 1).join('/') : '';
        
        this.apiBase = basePath + '/public/api.php';
        this.init();
    }

    init() {
        this.bindLoginForm();
        this.bindRegisterForm();
        this.bindPasswordToggle();
        this.initializeFormValidation();
    }

    bindLoginForm() {
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleLogin(e.target);
            });
        }
    }

    bindRegisterForm() {
        const registerForm = document.getElementById('registerForm');
        if (registerForm) {
            registerForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleRegister(e.target);
            });
        }
    }

    bindPasswordToggle() {
        document.querySelectorAll('[data-toggle-password]').forEach(button => {
            button.addEventListener('click', (e) => {
                const input = document.getElementById(e.target.dataset.togglePassword);
                if (input) {
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);

                    // Toggle icon
                    const icon = button.querySelector('svg');
                    if (type === 'text') {
                        icon.innerHTML = `
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"/>
                        `;
                    } else {
                        icon.innerHTML = `
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        `;
                    }
                }
            });
        });
    }

    async handleLogin(form) {
        try {
            this.showLoading();
            this.clearErrors();

            const formData = new FormData(form);
            const data = {
                email: formData.get('email'),
                password: formData.get('password')
            };

            // Basic validation
            if (!data.email || !data.password) {
                this.showError('Please fill in all fields');
                return;
            }

            if (!this.validateEmail(data.email)) {
                this.showError('Please enter a valid email address');
                return;
            }

            const response = await fetch(`${this.apiBase}?endpoint=auth/login`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                this.showSuccess('Login successful! Redirecting...');

                // Determine redirect URL based on user role
                let redirectUrl = 'dashboard.php';
                if (result.data && result.data.role) {
                    switch (result.data.role) {
                        case 'admin':
                            redirectUrl = '../admin/dashboard.php';
                            break;
                        case 'doctor':
                            redirectUrl = '../doctor/dashboard.php';
                            break;
                        case 'user':
                        default:
                            redirectUrl = 'dashboard.php';
                            break;
                    }
                }

                // Redirect after short delay
                setTimeout(() => {
                    window.location.href = redirectUrl;
                }, 1500);
            } else {
                this.showError(result.message || 'Login failed');
            }

        } catch (error) {
            console.error('Login error:', error);
            this.showError('Network error. Please check your connection.');
        } finally {
            this.hideLoading();
        }
    }

    async handleRegister(form) {
        try {
            this.showLoading();
            this.clearErrors();

            const formData = new FormData(form);
            const data = {
                name: formData.get('name'),
                email: formData.get('email'),
                password: formData.get('password'),
                password_confirmation: formData.get('password_confirmation'),
                role: formData.get('role') || 'user',
                phone: formData.get('phone'),
                bio: formData.get('bio')
            };

            // Additional fields for doctors
            if (data.role === 'doctor') {
                data.specialty = formData.get('specialty');
                data.license_number = formData.get('license_number');
            }

            // Validation
            const validation = this.validateRegistration(data);
            if (!validation.isValid) {
                this.showError(validation.message);
                return;
            }

            const response = await fetch(`${this.apiBase}?endpoint=auth/register`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                this.showSuccess('Registration successful! Please log in with your credentials.');

                // Redirect to login page
                setTimeout(() => {
                    window.location.href = 'login.php';
                }, 2000);
            } else {
                this.showError(result.message || 'Registration failed');
            }

        } catch (error) {
            console.error('Registration error:', error);
            this.showError('Network error. Please check your connection.');
        } finally {
            this.hideLoading();
        }
    }

    validateRegistration(data) {
        // Required fields
        if (!data.name || !data.email || !data.password) {
            return { isValid: false, message: 'Please fill in all required fields' };
        }

        // Name validation
        if (data.name.length < 2) {
            return { isValid: false, message: 'Name must be at least 2 characters long' };
        }

        // Email validation
        if (!this.validateEmail(data.email)) {
            return { isValid: false, message: 'Please enter a valid email address' };
        }

        // Password validation
        if (data.password.length < 6) {
            return { isValid: false, message: 'Password must be at least 6 characters long' };
        }

        // Password confirmation
        if (data.password !== data.password_confirmation) {
            return { isValid: false, message: 'Passwords do not match' };
        }

        // Password strength check
        if (!this.validatePasswordStrength(data.password)) {
            return {
                isValid: false,
                message: 'Password should contain at least one uppercase letter, one lowercase letter, and one number'
            };
        }

        // Doctor-specific validation
        if (data.role === 'doctor') {
            if (!data.specialty || !data.license_number) {
                return { isValid: false, message: 'Specialty and license number are required for doctors' };
            }
        }

        return { isValid: true };
    }

    validateEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    validatePasswordStrength(password) {
        // At least one uppercase, one lowercase, one number
        const strengthRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/;
        return strengthRegex.test(password);
    }

    initializeFormValidation() {
        // Real-time validation feedback
        document.addEventListener('input', (e) => {
            if (e.target.type === 'email') {
                this.validateEmailField(e.target);
            } else if (e.target.name === 'password') {
                this.validatePasswordField(e.target);
            } else if (e.target.name === 'password_confirmation') {
                this.validatePasswordConfirmation(e.target);
            }
        });

        // Role selection handler
        const roleSelect = document.getElementById('role');
        if (roleSelect) {
            roleSelect.addEventListener('change', (e) => {
                this.toggleDoctorFields(e.target.value === 'doctor');
            });
        }
    }

    validateEmailField(field) {
        const isValid = this.validateEmail(field.value);
        this.updateFieldValidation(field, isValid, 'Please enter a valid email address');
    }

    validatePasswordField(field) {
        const isStrong = this.validatePasswordStrength(field.value);
        const isLongEnough = field.value.length >= 6;
        const isValid = isStrong && isLongEnough;

        let message = '';
        if (!isLongEnough) {
            message = 'Password must be at least 6 characters long';
        } else if (!isStrong) {
            message = 'Password should contain uppercase, lowercase, and numbers';
        }

        this.updateFieldValidation(field, isValid, message);
    }

    validatePasswordConfirmation(field) {
        const passwordField = document.querySelector('input[name="password"]');
        const isValid = passwordField && field.value === passwordField.value;
        this.updateFieldValidation(field, isValid, 'Passwords do not match');
    }

    updateFieldValidation(field, isValid, errorMessage) {
        field.classList.remove('border-red-500', 'border-green-500');

        // Remove existing error message
        const existingError = field.parentNode.querySelector('.field-error');
        if (existingError) {
            existingError.remove();
        }

        if (field.value.length > 0) {
            if (isValid) {
                field.classList.add('border-green-500');
            } else {
                field.classList.add('border-red-500');

                // Add error message
                const errorDiv = document.createElement('div');
                errorDiv.className = 'field-error text-sm text-red-600 mt-1';
                errorDiv.textContent = errorMessage;
                field.parentNode.appendChild(errorDiv);
            }
        }
    }

    toggleDoctorFields(show) {
        const doctorFields = document.getElementById('doctorFields');
        if (doctorFields) {
            doctorFields.classList.toggle('hidden', !show);

            // Update required attributes
            const inputs = doctorFields.querySelectorAll('input, select');
            inputs.forEach(input => {
                if (show) {
                    input.setAttribute('required', 'required');
                } else {
                    input.removeAttribute('required');
                }
            });
        }
    }

    showLoading() {
        const submitBtn = document.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            const originalText = submitBtn.innerHTML;
            submitBtn.dataset.originalText = originalText;
            submitBtn.innerHTML = `
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Processing...
            `;
        }
    }

    hideLoading() {
        const submitBtn = document.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = false;
            if (submitBtn.dataset.originalText) {
                submitBtn.innerHTML = submitBtn.dataset.originalText;
            }
        }
    }

    showError(message) {
        this.showMessage(message, 'error');
    }

    showSuccess(message) {
        this.showMessage(message, 'success');
    }

    showMessage(message, type) {
        // Remove existing messages
        this.clearMessages();

        const messageDiv = document.createElement('div');
        messageDiv.className = `auth-message p-4 rounded-lg mb-4 ${type === 'error' ? 'bg-red-50 text-red-800 border border-red-200' :
            'bg-green-50 text-green-800 border border-green-200'
            }`;
        messageDiv.innerHTML = `
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 ${type === 'error' ? 'text-red-400' : 'text-green-400'}" fill="currentColor" viewBox="0 0 20 20">
                        ${type === 'error' ?
                '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>' :
                '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>'}
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium">${message}</p>
                </div>
            </div>
        `;

        const form = document.querySelector('form');
        if (form) {
            form.insertBefore(messageDiv, form.firstChild);
        }

        // Auto-remove success messages
        if (type === 'success') {
            setTimeout(() => {
                messageDiv.remove();
            }, 5000);
        }
    }

    clearMessages() {
        document.querySelectorAll('.auth-message').forEach(msg => msg.remove());
    }

    clearErrors() {
        this.clearMessages();
        document.querySelectorAll('.field-error').forEach(error => error.remove());
        document.querySelectorAll('.border-red-500').forEach(field => {
            field.classList.remove('border-red-500');
        });
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new AuthManager();
});