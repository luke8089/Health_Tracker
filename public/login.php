<?php
/**
 * Login Page
 * Health Tracker Application
 */

require_once __DIR__ . '/../src/helpers/Bootstrap.php';
require_once __DIR__ . '/../src/helpers/Auth.php';
require_once __DIR__ . '/../src/helpers/Utils.php';

$auth = new Auth();

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    $currentUser = $auth->getCurrentUser();
    switch ($currentUser['role']) {
        case 'admin':
            header('Location: ' . APP_BASE_PATH . '/admin/dashboard.php');
            break;
        case 'doctor':
            header('Location: ' . APP_BASE_PATH . '/doctor/dashboard.php');
            break;
        case 'user':
        default:
            header('Location: ' . APP_BASE_PATH . '/public/dashboard.php');
            break;
    }
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    $email = Utils::sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validation
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!Utils::validateEmail($email)) {
        $errors[] = 'Please enter a valid email address';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    }
    
    // If no errors, attempt login
    if (empty($errors)) {
        $result = $auth->login($email, $password);
        
        if ($result['success']) {
            // Clear any cache
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Cache-Control: post-check=0, pre-check=0", false);
            header("Pragma: no-cache");
            
            // Redirect based on user role
            $currentUser = $auth->getCurrentUser();
            switch ($currentUser['role']) {
                case 'admin':
                    header('Location: ' . APP_BASE_PATH . '/admin/dashboard.php');
                    break;
                case 'doctor':
                    header('Location: ' . APP_BASE_PATH . '/doctor/dashboard.php');
                    break;
                case 'user':
                default:
                    header('Location: ' . APP_BASE_PATH . '/public/dashboard.php');
                    break;
            }
            exit;
        } else {
            $errors[] = $result['message'];
        }
    }
}

// Prevent caching of login page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$title = "Sign In - Health Tracker";
require_once __DIR__ . '/../src/views/partials/header.php';
?>

<div class="min-h-screen pt-24 bg-gradient-to-br from-gray-100 via-gray-200 to-gray-300 flex items-center justify-center px-4 sm:px-6 lg:px-8 relative overflow-hidden">
    <!-- Animated background elements -->
    <div class="absolute inset-0 overflow-hidden">
        <div class="absolute -top-40 -right-32 w-80 h-80 opacity-10 rounded-full animate-pulse" style="background-color: #1f2937;"></div>
        <div class="absolute -bottom-40 -left-32 w-96 h-96 opacity-10 rounded-full animate-bounce" style="background-color: #34d399; animation-duration: 3s;"></div>
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-64 h-64 opacity-5 rounded-full animate-ping" style="background-color: #1f2937; animation-duration: 4s;"></div>
    </div>

    <!-- Login Card -->
    <div class="relative z-10 w-full max-w-md">
        <div class="login-card bg-white rounded-3xl shadow-2xl border-2 border-gray-300 transition-shadow duration-300 hover:shadow-3xl">
            <!-- Header -->
            <div class="px-8 pt-8 pb-2">
                <div class="flex items-center justify-center mb-8">
                    <div class="w-16 h-16 rounded-2xl flex items-center justify-center transform transition-transform duration-300 hover:rotate-12" style="background: linear-gradient(135deg, #1f2937 0%, #34d399 100%);">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                    </div>
                </div>
                <h1 class="text-4xl font-bold text-center mb-2 animate-fade-in" style="color: #1f2937;">Health Tracker</h1>
                <h2 class="text-2xl font-semibold text-center mb-6 animate-fade-in" style="color: #1f2937; animation-delay: 0.2s;">Welcome Back</h2>
                <p class="text-center text-base text-gray-700 mb-8 animate-fade-in" style="animation-delay: 0.4s;">
                    Don't have an account?
                    <a href="register.php" class="font-semibold hover:text-gray-600 transition-colors duration-200 underline decoration-2 underline-offset-4" style="color: #1f2937;">
                        Sign up for free
                    </a>
                </p>
            </div>

            <!-- Form -->
            <div class="px-8 pb-8">
                <form id="loginForm" method="POST" action="login.php" class="space-y-6">
                    <div class="animate-fade-in" style="animation-delay: 0.6s;">
                        <label for="email" class="block text-base font-semibold mb-3" style="color: #1f2937;">
                            Email Address
                        </label>
                        <div class="relative">
                            <input 
                                id="email" 
                                name="email" 
                                type="email" 
                                autocomplete="email" 
                                required 
                                value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                class="w-full px-4 py-4 bg-gray-50 border-2 border-gray-300 rounded-xl placeholder-gray-500 focus:outline-none focus:border-gray-900 focus:bg-white transition-all duration-300 text-base font-medium"
                                style="color: #1f2937;"
                                placeholder="Enter your email address"
                            >
                            <div class="absolute inset-y-0 right-0 pr-4 flex items-center">
                                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="animate-fade-in" style="animation-delay: 0.8s;">
                        <label for="password" class="block text-base font-semibold mb-3" style="color: #1f2937;">
                            Password
                        </label>
                        <div class="relative">
                            <input 
                                id="password" 
                                name="password" 
                                type="password" 
                                autocomplete="current-password" 
                                required 
                                class="w-full px-4 py-4 bg-gray-50 border-2 border-gray-300 rounded-xl placeholder-gray-500 focus:outline-none focus:border-gray-900 focus:bg-white transition-all duration-300 text-base font-medium pr-12"
                                style="color: #1f2937;"
                                placeholder="Enter your password"
                            >
                            <button 
                                type="button" 
                                class="absolute inset-y-0 right-0 pr-4 flex items-center transition-colors duration-200 hover:text-gray-700 focus:outline-none"
                                data-toggle-password="password"
                                id="togglePassword"
                            >
                                <svg id="eyeOpen" class="h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg id="eyeClosed" class="h-5 w-5 text-gray-500 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L5.64 5.64m4.242 4.242L5.64 5.64m0 0L5 5m.64.64l12.72 12.72"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between animate-fade-in" style="animation-delay: 1s;">
                        <div class="flex items-center">
                            <input 
                                id="remember_me" 
                                name="remember_me" 
                                type="checkbox" 
                                class="h-5 w-5 border-gray-300 rounded focus:ring-2 transition-colors duration-200"
                                style="accent-color: #1f2937;"
                            >
                            <label for="remember_me" class="ml-2 block text-base font-medium" style="color: #1f2937;">
                                Remember me
                            </label>
                        </div>
                        <div class="text-base">
                            <a href="forgot_password.php" class="font-semibold hover:text-gray-600 transition-colors duration-200 underline decoration-2 underline-offset-4" style="color: #1f2937;">
                                Forgot password?
                            </a>
                        </div>
                    </div>

                    <div class="animate-fade-in" style="animation-delay: 1.2s;">
                        <button
                            type="submit"
                            id="loginButton"
                            class="group relative w-full flex justify-center py-4 px-4 border border-transparent text-base font-semibold rounded-xl text-white transform transition-all duration-300 hover:shadow-lg hover:-translate-y-1 active:scale-95"
                            style="background: linear-gradient(135deg, #1f2937 0%, #34d399 100%); box-shadow: 0 4px 15px rgba(28, 37, 41, 0.3);"
                            onmouseover="this.style.background='linear-gradient(135deg, #111827 0%, #34d399 100%)'"
                            onmouseout="this.style.background='linear-gradient(135deg, #1f2937 0%, #34d399 100%)'"
                        >
                            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                <svg id="loginIcon" class="h-5 w-5 text-gray-300 group-hover:text-white transition-colors duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <svg id="loginSpinner" class="hidden animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </span>
                            <span id="loginText">Sign in to your account</span>
                            <span id="loginLoadingText" class="hidden">Signing in...</span>
                        </button>
                    </div>
                </form>

               

            </div>
        </div>
        
        <!-- Footer -->
        <div class="text-center mt-8 animate-fade-in" style="animation-delay: 1.6s;">
            <p class="text-base font-medium" style="color: #1f2937;">
                © 2025 Health Tracker. Built with ❤️ for better wellness.
            </p>
        </div>
    </div>
</div>

<!-- Popup Notification -->
<div id="notification" class="fixed top-4 right-4 transform translate-x-full transition-all duration-500 ease-in-out z-[9999] max-w-md">
    <div id="notificationContent" class="rounded-xl shadow-2xl border backdrop-blur-lg p-4">
        <div class="flex items-center">
            <div id="notificationIcon" class="flex-shrink-0 mr-3">
                <!-- Icon will be inserted here -->
            </div>
            <div class="flex-1">
                <h3 id="notificationTitle" class="text-sm font-semibold"></h3>
                <p id="notificationMessage" class="text-sm mt-1"></p>
            </div>
            <button onclick="closeNotification()" class="ml-4 text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </button>
        </div>
    </div>
</div>

<!-- Custom animations CSS -->
<style>
@keyframes fade-in {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in {
    animation: fade-in 0.6s ease-out forwards;
    opacity: 1 !important;
}

.demo-btn:hover {
    transform: translateY(-2px);
}

/* Ensure login card stays visible */
.login-card {
    opacity: 1 !important;
    visibility: visible !important;
}
</style>

<script>
// Popup notification functions
function showNotification(type, title, message) {
    const notification = document.getElementById('notification');
    const content = document.getElementById('notificationContent');
    const icon = document.getElementById('notificationIcon');
    const titleEl = document.getElementById('notificationTitle');
    const messageEl = document.getElementById('notificationMessage');
    
    // Set content
    titleEl.textContent = title;
    messageEl.textContent = message;
    
    // Set colors and icon based on type
    if (type === 'success') {
        content.className = 'rounded-xl shadow-2xl border backdrop-blur-lg p-4 bg-green-50/90 border-green-200 text-green-800';
        icon.innerHTML = `
            <div class="w-6 h-6 bg-green-400 rounded-full flex items-center justify-center">
                <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                </svg>
            </div>
        `;
    } else {
        content.className = 'rounded-xl shadow-2xl border backdrop-blur-lg p-4 bg-red-50/90 border-red-200 text-red-800';
        icon.innerHTML = `
            <div class="w-6 h-6 bg-red-400 rounded-full flex items-center justify-center">
                <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </div>
        `;
    }
    
    // Show notification
    notification.classList.remove('translate-x-full');
    notification.classList.add('translate-x-0');
    
    // Auto hide after 5 seconds
    setTimeout(() => {
        closeNotification();
    }, 5000);
}

function closeNotification() {
    const notification = document.getElementById('notification');
    notification.classList.remove('translate-x-0');
    notification.classList.add('translate-x-full');
}

// UI enhancements and error handling
document.addEventListener('DOMContentLoaded', function() {
    // Ensure all elements are visible after animations
    setTimeout(() => {
        const animatedElements = document.querySelectorAll('.animate-fade-in, .login-card');
        animatedElements.forEach(el => {
            el.style.opacity = '1';
            el.style.visibility = 'visible';
        });
    }, 2000);
    
    // Add loading effect to login form
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        const button = document.getElementById('loginButton');
        const icon = document.getElementById('loginIcon');
        const spinner = document.getElementById('loginSpinner');
        const text = document.getElementById('loginText');
        const loadingText = document.getElementById('loginLoadingText');
        
        // Disable button and show loading state
        button.disabled = true;
        button.style.opacity = '0.7';
        button.style.cursor = 'not-allowed';
        
        // Toggle visibility of elements
        icon.classList.add('hidden');
        spinner.classList.remove('hidden');
        text.classList.add('hidden');
        loadingText.classList.remove('hidden');
    });
    
    // Show error notifications if any
    <?php if (!empty($errors)): ?>
    showNotification('error', 'Login Failed', '<?php echo htmlspecialchars(implode('. ', $errors)); ?>');
    <?php endif; ?>
    
    // Check if user came from successful registration
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('registered') === '1') {
        showNotification('success', 'Registration Successful!', '🎉 Welcome to Health Tracker! Please sign in with your new credentials.');
        // Clean up URL
        window.history.replaceState({}, document.title, window.location.pathname);
    }
    
    // Check if password was reset successfully
    if (urlParams.get('reset') === 'success') {
        showNotification('success', 'Password Reset Complete!', '✅ Your password has been successfully reset. Please sign in with your new password.');
        // Clean up URL
        window.history.replaceState({}, document.title, window.location.pathname);
    }
    
    // Password toggle functionality with icon switching
    document.querySelectorAll('[data-toggle-password]').forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            const targetId = button.getAttribute('data-toggle-password');
            const input = document.getElementById(targetId);
            
            if (input) {
                const isPassword = input.getAttribute('type') === 'password';
                const newType = isPassword ? 'text' : 'password';
                input.setAttribute('type', newType);
                
                // Toggle eye icons
                const eyeOpen = button.querySelector('#eyeOpen');
                const eyeClosed = button.querySelector('#eyeClosed');
                
                if (eyeOpen && eyeClosed) {
                    if (isPassword) {
                        // Show text, hide eye open, show eye closed
                        eyeOpen.classList.add('hidden');
                        eyeClosed.classList.remove('hidden');
                        button.setAttribute('aria-label', 'Hide password');
                    } else {
                        // Show password, show eye open, hide eye closed
                        eyeOpen.classList.remove('hidden');
                        eyeClosed.classList.add('hidden');
                        button.setAttribute('aria-label', 'Show password');
                    }
                }
                
                // Add visual feedback
                input.focus();
            }
        });
        
        // Set initial aria-label
        button.setAttribute('aria-label', 'Show password');
    });
});

// Function to fill demo credentials
function fillDemoCredentials(email, password) {
    document.getElementById('email').value = email;
    document.getElementById('password').value = password;
    
    // Add visual feedback
    const emailField = document.getElementById('email');
    const passwordField = document.getElementById('password');
    
    emailField.classList.add('border-green-500');
    passwordField.classList.add('border-green-500');
    
    // Show notification for demo account
    showNotification('success', 'Demo Account Loaded', `Demo credentials for ${email.includes('admin') ? 'Admin' : email.includes('dr') ? 'Doctor' : 'User'} account have been filled in.`);
    
    setTimeout(() => {
        emailField.classList.remove('border-green-500');
        passwordField.classList.remove('border-green-500');
    }, 2000);
}
</script>

<?php require_once __DIR__ . '/../src/views/partials/footer.php'; ?>
