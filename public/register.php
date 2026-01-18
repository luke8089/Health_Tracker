<?php
/**
 * Registration Page
 * Health Tracker Application
 */

require_once __DIR__ . '/../src/helpers/Bootstrap.php';
require_once __DIR__ . '/../src/helpers/Auth.php';
require_once __DIR__ . '/../src/helpers/Utils.php';
require_once __DIR__ . '/../mail/Mailer.php';

$auth = new Auth();
$mailer = new Mailer();

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
    $data = [];
    
    // Sanitize and validate input
    $data['name'] = Utils::sanitizeInput($_POST['name'] ?? '');
    $data['email'] = Utils::sanitizeInput($_POST['email'] ?? '');
    $data['password'] = $_POST['password'] ?? '';
    $password_confirmation = $_POST['password_confirmation'] ?? '';
    $data['role'] = Utils::sanitizeInput($_POST['role'] ?? 'user');
    
    // Validation
    if (empty($data['name'])) {
        $errors[] = 'Full name is required';
    }
    
    if (empty($data['email'])) {
        $errors[] = 'Email is required';
    } elseif (!Utils::validateEmail($data['email'])) {
        $errors[] = 'Please enter a valid email address';
    }
    
    if (empty($data['password'])) {
        $errors[] = 'Password is required';
    } elseif (strlen($data['password']) < 6) {
        $errors[] = 'Password must be at least 6 characters long';
    }
    
    if ($data['password'] !== $password_confirmation) {
        $errors[] = 'Passwords do not match';
    }
    
    // Doctor-specific validation
    if ($data['role'] === 'doctor') {
        $data['specialty'] = Utils::sanitizeInput($_POST['specialization'] ?? '');
        $data['license_number'] = Utils::sanitizeInput($_POST['license_number'] ?? '');
        $data['experience_years'] = Utils::sanitizeInput($_POST['experience_years'] ?? '');
        $data['consultation_fee'] = Utils::sanitizeInput($_POST['consultation_fee'] ?? '');
        
        if (empty($data['specialty'])) {
            $errors[] = 'Specialization is required for doctors';
        }
        
        if (empty($data['license_number'])) {
            $errors[] = 'Medical license number is required for doctors';
        }
    }
    
    // If no errors, attempt registration
    if (empty($errors)) {
        $result = $auth->register($data);
        
        if ($result['success']) {
            // Send welcome email based on role
            try {
                if ($data['role'] === 'doctor') {
                    // Send welcome email to doctor
                    $mailer->sendDoctorRegistrationEmail([
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'specialty' => $data['specialty'] ?? 'General Practice',
                        'license_number' => $data['license_number'] ?? '',
                        'experience_years' => $data['experience_years'] ?? ''
                    ]);
                    
                    // Notify admin about new doctor registration
                    $mailer->notifyAdminNewDoctor([
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'specialty' => $data['specialty'] ?? 'General Practice',
                        'license_number' => $data['license_number'] ?? '',
                        'experience_years' => $data['experience_years'] ?? ''
                    ]);
                } else {
                    // Send regular welcome email to user
                    $mailer->sendWelcomeEmail([
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'role' => $data['role']
                    ]);
                }
            } catch (Exception $e) {
                // Log email error but don't prevent registration
                error_log("Registration email failed: " . $e->getMessage());
            }
            
            Utils::redirect('login.php?registered=1', 'Registration successful! Please check your email and sign in with your new account.', 'success');
        } else {
            $errors[] = $result['message'];
        }
    }
}

$title = "Sign Up - Health Tracker";
require_once __DIR__ . '/../src/views/partials/header.php';
?>

<div class="min-h-screen pt-24 bg-gradient-to-br from-gray-100 via-gray-200 to-gray-300 flex items-center justify-center px-4 sm:px-6 lg:px-8 relative overflow-hidden">
    <!-- Animated background elements -->
    <div class="absolute inset-0 overflow-hidden">
        <div class="absolute -top-40 -right-32 w-80 h-80 opacity-10 rounded-full animate-pulse" style="background-color: #1f2937;"></div>
        <div class="absolute -bottom-40 -left-32 w-96 h-96 opacity-10 rounded-full animate-bounce" style="background-color: #34d399; animation-duration: 3s;"></div>
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-64 h-64 opacity-5 rounded-full animate-ping" style="background-color: #1f2937; animation-duration: 4s;"></div>
        <div class="absolute top-20 left-20 w-32 h-32 opacity-8 rounded-full animate-pulse" style="background-color: #1f2937; animation-delay: 1s;"></div>
        <div class="absolute bottom-20 right-20 w-40 h-40 opacity-10 rounded-full animate-bounce" style="background-color: #34d399; animation-duration: 2s; animation-delay: 0.5s;"></div>
    </div>

    <!-- Register Card -->
    <div class="relative z-10 w-full max-w-2xl">
        <div class="register-card bg-white rounded-3xl shadow-2xl border-2 border-gray-300 transition-shadow duration-300 hover:shadow-3xl">
            <!-- Header -->
            <div class="px-8 pt-8 pb-2">
                <div class="flex items-center justify-center mb-8">
                    <div class="w-16 h-16 bg-black rounded-2xl flex items-center justify-center transform transition-transform duration-300 hover:rotate-12">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                    </div>
                </div>
                <h1 class="text-4xl font-bold text-center mb-2 animate-fade-in" style="color: #1f2937;">Join Health Tracker</h1>
                <h2 class="text-2xl font-semibold text-center mb-6 animate-fade-in" style="color: #1f2937; animation-delay: 0.2s;">Start Your Wellness Journey</h2>
                <p class="text-center text-base text-gray-700 mb-8 animate-fade-in" style="animation-delay: 0.4s;">
                    Already have an account?
                    <a href="login.php" class="font-semibold hover:text-gray-600 transition-colors duration-200 underline decoration-2 underline-offset-4" style="color: #1f2937;">
                        Sign in here
                    </a>
                </p>
            </div>

            <!-- Form -->
            <div class="px-8 pb-8">
                <form id="registerForm" method="POST" action="register.php" class="space-y-6">
                    <!-- Basic Information -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="animate-fade-in" style="animation-delay: 0.6s;">
                            <label for="name" class="block text-base font-semibold mb-3" style="color: #1f2937;">
                                Full Name <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                id="name" 
                                name="name" 
                                required 
                                value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                                class="w-full px-4 py-4 bg-gray-50 border-2 border-gray-300 rounded-xl placeholder-gray-500 focus:outline-none focus:border-gray-900 focus:bg-white transition-all duration-300 text-base font-medium"
                                style="color: #1f2937;"
                                placeholder="Enter your full name"
                            >
                        </div>

                        <div class="animate-fade-in" style="animation-delay: 0.7s;">
                            <label for="email" class="block text-base font-semibold mb-3" style="color: #1f2937;">
                                Email Address <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                required 
                                value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                class="w-full px-4 py-4 bg-gray-50 border-2 border-gray-300 rounded-xl placeholder-gray-500 focus:outline-none focus:border-gray-900 focus:bg-white transition-all duration-300 text-base font-medium"
                                style="color: #1f2937;"
                                placeholder="Enter your email address"
                            >
                        </div>
                    </div>

                    <!-- Password Fields -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="animate-fade-in" style="animation-delay: 0.8s;">
                            <label for="password" class="block text-base font-semibold mb-3" style="color: #1f2937;">
                                Password <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input 
                                    type="password" 
                                    id="password" 
                                    name="password" 
                                    required 
                                    class="w-full px-4 py-4 bg-gray-50 border-2 border-gray-300 rounded-xl placeholder-gray-500 focus:outline-none focus:border-gray-900 focus:bg-white transition-all duration-300 text-base font-medium pr-12"
                                    style="color: #1f2937;"
                                    placeholder="Create a strong password"
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

                        <div class="animate-fade-in" style="animation-delay: 0.9s;">
                            <label for="password_confirmation" class="block text-base font-semibold mb-3" style="color: #1f2937;">
                                Confirm Password <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input 
                                    type="password" 
                                    id="password_confirmation" 
                                    name="password_confirmation" 
                                    required 
                                    class="w-full px-4 py-4 bg-gray-50 border-2 border-gray-300 rounded-xl placeholder-gray-500 focus:outline-none focus:border-gray-900 focus:bg-white transition-all duration-300 text-base font-medium pr-12"
                                    style="color: #1f2937;"
                                    placeholder="Confirm your password"
                                >
                                <button 
                                    type="button" 
                                    class="absolute inset-y-0 right-0 pr-4 flex items-center transition-colors duration-200 hover:text-gray-700 focus:outline-none"
                                    data-toggle-password="password_confirmation"
                                >
                                    <svg class="eye-open h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    <svg class="eye-closed h-5 w-5 text-gray-500 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L5.64 5.64m4.242 4.242L5.64 5.64m0 0L5 5m.64.64l12.72 12.72"/>
                                    </svg>
                                </button>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Role Selection -->
                    <div class="animate-fade-in" style="animation-delay: 1s;">
                        <label for="role" class="block text-base font-semibold mb-3" style="color: #1f2937;">
                            Account Type <span class="text-red-500">*</span>
                        </label>
                        <select id="role" name="role" class="w-full px-4 py-4 bg-gray-50 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-gray-900 focus:bg-white transition-all duration-300 text-base font-medium" style="color: #1f2937;">
                            <option value="user" <?php echo (($_POST['role'] ?? 'user') === 'user') ? 'selected' : ''; ?>>Health Tracker User</option>
                            <option value="doctor" <?php echo (($_POST['role'] ?? 'user') === 'doctor') ? 'selected' : ''; ?>>Healthcare Professional</option>
                        </select>
                        <p class="mt-2 text-sm font-medium" style="color: #666;">Choose "Healthcare Professional" if you're a licensed medical practitioner.</p>
                    </div>

                    <!-- Doctor-specific Fields -->
                    <div id="doctorFields" class="<?php echo (($_POST['role'] ?? 'user') === 'doctor') ? 'show' : 'hidden'; ?> space-y-6 p-6 bg-gray-50 rounded-2xl border-2 border-gray-300 animate-fade-in" style="animation-delay: 1.1s;">
                        <h3 class="text-xl font-semibold mb-4" style="color: #1f2937;">Professional Information</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="specialization" class="block text-base font-semibold mb-3" style="color: #1f2937;">
                                    Specialization <span class="text-red-500">*</span>
                                </label>
                                <select id="specialization" name="specialization" class="w-full px-4 py-3 bg-white border-2 border-gray-300 rounded-xl focus:outline-none focus:border-gray-900 transition-all duration-300" style="color: #1f2937;">
                                    <option value="">Select specialization...</option>
                                    <option value="general" <?php echo (($_POST['specialization'] ?? '') === 'general') ? 'selected' : ''; ?>>General Practice</option>
                                    <option value="cardiology" <?php echo (($_POST['specialization'] ?? '') === 'cardiology') ? 'selected' : ''; ?>>Cardiology</option>
                                    <option value="dermatology" <?php echo (($_POST['specialization'] ?? '') === 'dermatology') ? 'selected' : ''; ?>>Dermatology</option>
                                    <option value="endocrinology" <?php echo (($_POST['specialization'] ?? '') === 'endocrinology') ? 'selected' : ''; ?>>Endocrinology</option>
                                    <option value="gastroenterology" <?php echo (($_POST['specialization'] ?? '') === 'gastroenterology') ? 'selected' : ''; ?>>Gastroenterology</option>
                                    <option value="neurology" <?php echo (($_POST['specialization'] ?? '') === 'neurology') ? 'selected' : ''; ?>>Neurology</option>
                                    <option value="orthopedics" <?php echo (($_POST['specialization'] ?? '') === 'orthopedics') ? 'selected' : ''; ?>>Orthopedics</option>
                                    <option value="psychiatry" <?php echo (($_POST['specialization'] ?? '') === 'psychiatry') ? 'selected' : ''; ?>>Psychiatry</option>
                                    <option value="pulmonology" <?php echo (($_POST['specialization'] ?? '') === 'pulmonology') ? 'selected' : ''; ?>>Pulmonology</option>
                                    <option value="other" <?php echo (($_POST['specialization'] ?? '') === 'other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>

                            <div>
                                <label for="license_number" class="block text-sm font-semibold text-black mb-3">
                                    Medical License Number <span class="text-red-500">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    id="license_number" 
                                    name="license_number" 
                                    value="<?php echo htmlspecialchars($_POST['license_number'] ?? ''); ?>"
                                    class="w-full px-4 py-3 bg-white border-2 border-gray-200 rounded-xl text-black placeholder-gray-400 focus:outline-none focus:border-black transition-all duration-300"
                                    placeholder="Enter license number"
                                >
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="experience_years" class="block text-sm font-semibold text-black mb-3">
                                    Years of Experience
                                </label>
                                <select id="experience_years" name="experience_years" class="w-full px-4 py-3 bg-white border-2 border-gray-200 rounded-xl text-black focus:outline-none focus:border-black transition-all duration-300">
                                    <option value="">Select experience...</option>
                                    <option value="0-2">0-2 years</option>
                                    <option value="3-5">3-5 years</option>
                                    <option value="6-10">6-10 years</option>
                                    <option value="11-15">11-15 years</option>
                                    <option value="16-20">16-20 years</option>
                                    <option value="20+">20+ years</option>
                                </select>
                            </div>

                            <div>
                                <label for="consultation_fee" class="block text-sm font-semibold text-black mb-3">
                                    Consultation Fee ($)
                                </label>
                                <input 
                                    type="number" 
                                    id="consultation_fee" 
                                    name="consultation_fee" 
                                    class="w-full px-4 py-3 bg-white border-2 border-gray-200 rounded-xl text-black placeholder-gray-400 focus:outline-none focus:border-black transition-all duration-300"
                                    placeholder="e.g., 75"
                                    min="0"
                                    step="5"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Terms and Conditions -->
                    <div class="flex items-start animate-fade-in" style="animation-delay: 1.2s;">
                        <input 
                            id="terms" 
                            name="terms" 
                            type="checkbox" 
                            required 
                            class="h-4 w-4 mt-1 text-black border-gray-300 rounded focus:ring-black focus:ring-2 transition-colors duration-200"
                        >
                        <label for="terms" class="ml-3 block text-sm text-gray-600 font-medium">
                            I agree to the 
                            <a href="#" class="font-semibold text-black hover:text-gray-700 underline decoration-2 underline-offset-4">Terms of Service</a> 
                            and 
                            <a href="#" class="font-semibold text-black hover:text-gray-700 underline decoration-2 underline-offset-4">Privacy Policy</a>
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <div class="animate-fade-in" style="animation-delay: 1.3s;">
                        <button
                            type="submit"
                            id="registerButton"
                            class="group relative w-full flex justify-center py-4 px-4 border border-transparent text-base font-semibold rounded-xl text-white transform transition-all duration-300 hover:shadow-lg hover:-translate-y-1 active:scale-95"
                            style="background: linear-gradient(135deg, #1f2937 0%, #34d399 100%); box-shadow: 0 4px 15px rgba(28, 37, 41, 0.3);"
                            onmouseover="this.style.background='linear-gradient(135deg, #111827 0%, #34d399 100%)'"
                            onmouseout="this.style.background='linear-gradient(135deg, #1f2937 0%, #34d399 100%)'"
                        >
                            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                <svg id="registerIcon" class="h-5 w-5 text-gray-300 group-hover:text-white transition-colors duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                                <svg id="registerSpinner" class="hidden animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </span>
                            <span id="registerText">Create your account</span>
                            <span id="registerLoadingText" class="hidden">Creating account...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="text-center mt-8 animate-fade-in" style="animation-delay: 1.5s;">
            <p class="text-sm text-gray-500">
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

/* Doctor fields smooth transition */
#doctorFields {
    transition: all 0.3s ease-in-out;
    max-height: 0;
    overflow: hidden;
}

#doctorFields.show {
    max-height: 1000px;
    padding: 1.5rem;
}

/* Ensure register card stays visible */
.register-card {
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

// Show notifications and handle UI interactions
document.addEventListener('DOMContentLoaded', function() {
    // Ensure all elements are visible after animations
    setTimeout(() => {
        const animatedElements = document.querySelectorAll('.animate-fade-in, .register-card');
        animatedElements.forEach(el => {
            el.style.opacity = '1';
            el.style.visibility = 'visible';
        });
    }, 2000);
    
    // Add loading effect to register form
    document.getElementById('registerForm').addEventListener('submit', function(e) {
        const button = document.getElementById('registerButton');
        const icon = document.getElementById('registerIcon');
        const spinner = document.getElementById('registerSpinner');
        const text = document.getElementById('registerText');
        const loadingText = document.getElementById('registerLoadingText');
        
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
    
    // Show error notifications if any
    <?php if (!empty($errors)): ?>
    showNotification('error', 'Registration Failed', '<?php echo htmlspecialchars(implode('. ', $errors)); ?>');
    <?php endif; ?>
    
    // Show/hide doctor fields based on role selection
    document.getElementById('role').addEventListener('change', function() {
        const doctorFields = document.getElementById('doctorFields');
        const isDoctor = this.value === 'doctor';
        
        if (isDoctor) {
            doctorFields.classList.remove('hidden');
            doctorFields.classList.add('show');
            // Make doctor fields required
            doctorFields.querySelectorAll('input, select').forEach(field => {
                if (['specialization', 'license_number'].includes(field.name)) {
                    field.required = true;
                }
            });
        } else {
            doctorFields.classList.add('hidden');
            doctorFields.classList.remove('show');
            // Make doctor fields not required
            doctorFields.querySelectorAll('input, select').forEach(field => {
                field.required = false;
            });
        }
    });
    
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
                const eyeOpen = button.querySelector('.eye-open');
                const eyeClosed = button.querySelector('.eye-closed');
                
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
</script>

<?php require_once __DIR__ . '/../src/views/partials/footer.php'; ?>
