<?php
/**
 * Forgot Password Page
 * Health Tracker Application
 */

require_once __DIR__ . '/../src/helpers/Bootstrap.php';
require_once __DIR__ . '/../src/helpers/Auth.php';
require_once __DIR__ . '/../src/helpers/Utils.php';
require_once __DIR__ . '/../src/helpers/Database.php';
require_once __DIR__ . '/../mail/Mailer.php';

$auth = new Auth();

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

$successMessage = '';
$errorMessage = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = Utils::sanitizeInput($_POST['email'] ?? '');
    
    if (empty($email)) {
        $errorMessage = 'Email address is required';
    } elseif (!Utils::validateEmail($email)) {
        $errorMessage = 'Please enter a valid email address';
    } else {
        try {
            $db = (new Database())->connect();
            
            // Check if user exists
            $stmt = $db->prepare("SELECT id, name, email FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Generate reset token
                $resetToken = bin2hex(random_bytes(32));
                $resetExpiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Store reset token in database
                $stmt = $db->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
                $stmt->execute([$resetToken, $resetExpiry, $user['id']]);
                
                // Send reset email
                try {
                    $mailer = new Mailer();
                    $result = $mailer->sendPasswordResetEmail($user['email'], $user['name'], $resetToken);
                    
                    if ($result['success']) {
                        $successMessage = 'Password reset instructions have been sent to your email address. Please check your inbox (and spam folder).';
                    } else {
                        $errorMessage = 'Failed to send reset email. Please try again later.';
                        error_log("Password reset email failed: " . $result['message']);
                    }
                } catch (Exception $e) {
                    $errorMessage = 'An error occurred while sending the reset email. Please try again later.';
                    error_log("Password reset email error: " . $e->getMessage());
                }
            } else {
                // Don't reveal if email exists or not for security
                $successMessage = 'If an account exists with this email, password reset instructions have been sent.';
            }
        } catch (Exception $e) {
            $errorMessage = 'An error occurred. Please try again later.';
            error_log("Forgot password error: " . $e->getMessage());
        }
    }
}

$title = "Forgot Password - Health Tracker";
require_once __DIR__ . '/../src/views/partials/header.php';
?>

<div class="min-h-screen pt-24 bg-gradient-to-br from-gray-100 via-gray-200 to-gray-300 flex items-center justify-center px-4 sm:px-6 lg:px-8 relative overflow-hidden">
    <!-- Animated background elements -->
    <div class="absolute inset-0 overflow-hidden">
        <div class="absolute -top-40 -right-32 w-80 h-80 opacity-10 rounded-full animate-pulse" style="background-color: #1f2937;"></div>
        <div class="absolute -bottom-40 -left-32 w-96 h-96 opacity-10 rounded-full animate-bounce" style="background-color: #34d399; animation-duration: 3s;"></div>
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-64 h-64 opacity-5 rounded-full animate-ping" style="background-color: #1f2937; animation-duration: 4s;"></div>
    </div>

    <!-- Forgot Password Card -->
    <div class="relative z-10 w-full max-w-md">
        <div class="bg-white rounded-3xl shadow-2xl border-2 border-gray-300 transition-shadow duration-300 hover:shadow-3xl">
            <!-- Header -->
            <div class="px-8 pt-8 pb-2">
                <div class="flex items-center justify-center mb-8">
                    <div class="w-16 h-16 bg-gradient-to-br from-red-500 to-orange-500 rounded-2xl flex items-center justify-center transform transition-transform duration-300 hover:rotate-12">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                        </svg>
                    </div>
                </div>
                <h1 class="text-3xl font-bold text-center mb-2 text-gray-900">Forgot Password?</h1>
                <p class="text-center text-base text-gray-600 mb-8">
                    No worries! Enter your email and we'll send you reset instructions.
                </p>
            </div>

            <!-- Form -->
            <div class="px-8 pb-8">
                <?php if ($successMessage): ?>
                    <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-400 rounded-lg">
                        <div class="flex items-start">
                            <svg class="w-6 h-6 text-green-400 mt-0.5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <div>
                                <h3 class="text-sm font-semibold text-green-800 mb-1">Email Sent!</h3>
                                <p class="text-sm text-green-700"><?php echo htmlspecialchars($successMessage); ?></p>
                            </div>
                        </div>
                        <div class="mt-4 text-center">
                            <a href="login.php" class="inline-block px-6 py-2 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition">
                                Back to Login
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($errorMessage): ?>
                    <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-400 rounded-lg">
                        <div class="flex items-start">
                            <svg class="w-6 h-6 text-red-400 mt-0.5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            <div>
                                <h3 class="text-sm font-semibold text-red-800 mb-1">Error</h3>
                                <p class="text-sm text-red-700"><?php echo htmlspecialchars($errorMessage); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <form id="forgotPasswordForm" method="POST" action="forgot_password.php" class="space-y-6">
                    <div>
                        <label for="email" class="block text-base font-semibold mb-3 text-gray-900">
                            Email Address
                        </label>
                        <div class="relative">
                            <input 
                                id="email" 
                                name="email" 
                                type="email" 
                                autocomplete="email" 
                                required 
                                class="w-full px-4 py-4 bg-gray-50 border-2 border-gray-300 rounded-xl placeholder-gray-500 focus:outline-none focus:border-gray-900 focus:bg-white transition-all duration-300 text-base font-medium text-gray-900"
                                placeholder="Enter your email address"
                            >
                            <div class="absolute inset-y-0 right-0 pr-4 flex items-center">
                                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div>
                        <button
                            type="submit"
                            id="resetButton"
                            class="group relative w-full flex justify-center py-4 px-4 border border-transparent text-base font-semibold rounded-xl text-white transform transition-all duration-300 hover:shadow-lg hover:-translate-y-1 active:scale-95 bg-gradient-to-r from-red-500 to-orange-500 hover:from-red-600 hover:to-orange-600"
                        >
                            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                <svg id="resetIcon" class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                <svg id="resetSpinner" class="hidden animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </span>
                            <span id="resetText">Send Reset Instructions</span>
                            <span id="resetLoadingText" class="hidden">Sending...</span>
                        </button>
                    </div>
                </form>

                <div class="mt-6 text-center">
                    <a href="login.php" class="text-base font-semibold text-gray-700 hover:text-gray-900 transition-colors duration-200 inline-flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Back to Login
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="text-center mt-8">
            <p class="text-sm text-gray-600">
                © 2025 Health Tracker. All rights reserved.
            </p>
        </div>
    </div>
</div>

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
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add loading effect to form
    document.getElementById('forgotPasswordForm').addEventListener('submit', function(e) {
        const button = document.getElementById('resetButton');
        const icon = document.getElementById('resetIcon');
        const spinner = document.getElementById('resetSpinner');
        const text = document.getElementById('resetText');
        const loadingText = document.getElementById('resetLoadingText');
        
        // Disable button and show loading state
        button.disabled = true;
        button.style.opacity = '0.7';
        button.style.cursor = 'not-allowed';
        button.style.transform = 'none';
        
        // Toggle visibility of elements
        icon.classList.add('hidden');
        spinner.classList.remove('hidden');
        text.classList.add('hidden');
        loadingText.classList.remove('hidden');
    });
});
</script>

<?php require_once __DIR__ . '/../src/views/partials/footer.php'; ?>
