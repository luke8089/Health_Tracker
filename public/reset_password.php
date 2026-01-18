<?php
/**
 * Reset Password Page
 * Health Tracker Application
 */

require_once __DIR__ . '/../src/helpers/Bootstrap.php';
require_once __DIR__ . '/../src/helpers/Auth.php';
require_once __DIR__ . '/../src/helpers/Utils.php';
require_once __DIR__ . '/../src/helpers/Database.php';

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

$token = $_GET['token'] ?? '';
$errorMessage = '';
$successMessage = '';
$validToken = false;
$user = null;

if (empty($token)) {
    $errorMessage = 'Invalid or missing reset token';
} else {
    try {
        $db = (new Database())->connect();
        
        // Verify token and check expiry
        $stmt = $db->prepare("SELECT id, name, email, reset_token_expiry FROM users WHERE reset_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $expiry = strtotime($user['reset_token_expiry']);
            $now = time();
            
            if ($expiry < $now) {
                $errorMessage = 'This reset link has expired. Please request a new one.';
            } else {
                $validToken = true;
            }
        } else {
            $errorMessage = 'Invalid reset token. Please request a new password reset.';
        }
    } catch (Exception $e) {
        $errorMessage = 'An error occurred. Please try again later.';
        error_log("Reset password token verification error: " . $e->getMessage());
    }
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($password)) {
        $errorMessage = 'Password is required';
    } elseif (strlen($password) < 6) {
        $errorMessage = 'Password must be at least 6 characters long';
    } elseif ($password !== $confirmPassword) {
        $errorMessage = 'Passwords do not match';
    } else {
        try {
            $db = (new Database())->connect();
            
            // Hash new password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Update password and clear reset token
            $stmt = $db->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?");
            $success = $stmt->execute([$hashedPassword, $user['id']]);
            
            if ($success) {
                Utils::redirect('login.php?reset=success', 'Password reset successful! Please sign in with your new password.', 'success');
            } else {
                $errorMessage = 'Failed to update password. Please try again.';
                error_log("Password reset failed: Unable to update user record");
            }
        } catch (Exception $e) {
            $errorMessage = 'Failed to reset password. Please try again.';
            error_log("Password reset error: " . $e->getMessage());
        }
    }
}

$title = "Reset Password - Health Tracker";
require_once __DIR__ . '/../src/views/partials/header.php';
?>

<div class="min-h-screen pt-24 bg-gradient-to-br from-gray-100 via-gray-200 to-gray-300 flex items-center justify-center px-4 sm:px-6 lg:px-8 relative overflow-hidden">
    <!-- Animated background elements -->
    <div class="absolute inset-0 overflow-hidden">
        <div class="absolute -top-40 -right-32 w-80 h-80 opacity-10 rounded-full animate-pulse" style="background-color: #1f2937;"></div>
        <div class="absolute -bottom-40 -left-32 w-96 h-96 opacity-10 rounded-full animate-bounce" style="background-color: #34d399; animation-duration: 3s;"></div>
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-64 h-64 opacity-5 rounded-full animate-ping" style="background-color: #1f2937; animation-duration: 4s;"></div>
    </div>

    <!-- Reset Password Card -->
    <div class="relative z-10 w-full max-w-md">
        <div class="bg-white rounded-3xl shadow-2xl border-2 border-gray-300 transition-shadow duration-300 hover:shadow-3xl">
            <!-- Header -->
            <div class="px-8 pt-8 pb-2">
                <div class="flex items-center justify-center mb-8">
                    <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-500 rounded-2xl flex items-center justify-center transform transition-transform duration-300 hover:rotate-12">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                    </div>
                </div>
                <h1 class="text-3xl font-bold text-center mb-2 text-gray-900">Reset Your Password</h1>
                <p class="text-center text-base text-gray-600 mb-8">
                    <?php echo $validToken ? 'Enter your new password below' : 'Link verification failed'; ?>
                </p>
            </div>

            <!-- Form or Error Message -->
            <div class="px-8 pb-8">
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
                        <?php if (!$validToken): ?>
                            <div class="mt-4 text-center">
                                <a href="forgot_password.php" class="inline-block px-6 py-2 bg-red-600 text-white font-semibold rounded-lg hover:bg-red-700 transition">
                                    Request New Reset Link
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($validToken): ?>
                    <form id="resetPasswordForm" method="POST" class="space-y-6">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                        
                        <div>
                            <label for="password" class="block text-base font-semibold mb-3 text-gray-900">
                                New Password
                            </label>
                            <div class="relative">
                                <input 
                                    id="password" 
                                    name="password" 
                                    type="password" 
                                    required 
                                    minlength="6"
                                    class="w-full px-4 py-4 bg-gray-50 border-2 border-gray-300 rounded-xl placeholder-gray-500 focus:outline-none focus:border-gray-900 focus:bg-white transition-all duration-300 text-base font-medium text-gray-900 pr-12"
                                    placeholder="Enter new password (min. 6 characters)"
                                >
                                <button 
                                    type="button" 
                                    class="absolute inset-y-0 right-0 pr-4 flex items-center transition-colors duration-200 hover:text-gray-700 focus:outline-none"
                                    data-toggle-password="password"
                                >
                                    <svg class="eye-open h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    <svg class="eye-closed h-5 w-5 text-gray-500 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L5.64 5.64m4.242 4.242L5.64 5.64m0 0L5 5m.64.64l12.72 12.72"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label for="confirm_password" class="block text-base font-semibold mb-3 text-gray-900">
                                Confirm New Password
                            </label>
                            <div class="relative">
                                <input 
                                    id="confirm_password" 
                                    name="confirm_password" 
                                    type="password" 
                                    required 
                                    minlength="6"
                                    class="w-full px-4 py-4 bg-gray-50 border-2 border-gray-300 rounded-xl placeholder-gray-500 focus:outline-none focus:border-gray-900 focus:bg-white transition-all duration-300 text-base font-medium text-gray-900 pr-12"
                                    placeholder="Re-enter new password"
                                >
                                <button 
                                    type="button" 
                                    class="absolute inset-y-0 right-0 pr-4 flex items-center transition-colors duration-200 hover:text-gray-700 focus:outline-none"
                                    data-toggle-password="confirm_password"
                                >
                                    <svg class="eye-open h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    <svg class="eye-closed h-5 w-5 text-gray-500 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L5.64 5.64m4.242 4.242L5.64 5.64m0 0L5 5m.64.64l12.72 12.72"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div>
                            <button
                                type="submit"
                                id="submitButton"
                                class="group relative w-full flex justify-center py-4 px-4 border border-transparent text-base font-semibold rounded-xl text-white transform transition-all duration-300 hover:shadow-lg hover:-translate-y-1 active:scale-95 bg-gradient-to-r from-blue-500 to-purple-500 hover:from-blue-600 hover:to-purple-600"
                            >
                                <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                    <svg id="submitIcon" class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <svg id="submitSpinner" class="hidden animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </span>
                                <span id="submitText">Reset Password</span>
                                <span id="submitLoadingText" class="hidden">Resetting...</span>
                            </button>
                        </div>
                    </form>
                <?php endif; ?>

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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password toggle functionality
    document.querySelectorAll('[data-toggle-password]').forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            const targetId = button.getAttribute('data-toggle-password');
            const input = document.getElementById(targetId);
            
            if (input) {
                const isPassword = input.getAttribute('type') === 'password';
                input.setAttribute('type', isPassword ? 'text' : 'password');
                
                const eyeOpen = button.querySelector('.eye-open');
                const eyeClosed = button.querySelector('.eye-closed');
                
                if (eyeOpen && eyeClosed) {
                    if (isPassword) {
                        eyeOpen.classList.add('hidden');
                        eyeClosed.classList.remove('hidden');
                    } else {
                        eyeOpen.classList.remove('hidden');
                        eyeClosed.classList.add('hidden');
                    }
                }
            }
        });
    });
    
    // Loading effect for form
    const form = document.getElementById('resetPasswordForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const button = document.getElementById('submitButton');
            const icon = document.getElementById('submitIcon');
            const spinner = document.getElementById('submitSpinner');
            const text = document.getElementById('submitText');
            const loadingText = document.getElementById('submitLoadingText');
            
            button.disabled = true;
            button.style.opacity = '0.7';
            button.style.cursor = 'not-allowed';
            button.style.transform = 'none';
            
            icon.classList.add('hidden');
            spinner.classList.remove('hidden');
            text.classList.add('hidden');
            loadingText.classList.remove('hidden');
        });
    }
});
</script>

<?php require_once __DIR__ . '/../src/views/partials/footer.php'; ?>
