<?php
/**
 * Doctor Profile Page
 * Health Tracker Application
 */

require_once __DIR__ . '/../src/helpers/Auth.php';
require_once __DIR__ . '/../src/helpers/Database.php';
require_once __DIR__ . '/../src/helpers/Utils.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requireRole('doctor');

$currentUser = $auth->getCurrentUser();
$database = new Database();
$db = $database->connect();

$errors = [];
$success = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Profile update
    if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
        $name = Utils::sanitizeInput($_POST['name'] ?? '');
        $email = Utils::sanitizeInput($_POST['email'] ?? '');
        $phone = Utils::sanitizeInput($_POST['phone'] ?? '');
        $bio = Utils::sanitizeInput($_POST['bio'] ?? '');
        $specialty = Utils::sanitizeInput($_POST['specialty'] ?? '');
        $license_number = Utils::sanitizeInput($_POST['license_number'] ?? '');
        $availability = $_POST['availability'] ?? 'offline';
        
        // Validation
        if (empty($name)) {
            $errors[] = 'Name is required';
        }
        
        if (empty($email)) {
            $errors[] = 'Email is required';
        } elseif (!Utils::validateEmail($email)) {
            $errors[] = 'Invalid email format';
        }
        
        if (empty($specialty)) {
            $errors[] = 'Specialty is required';
        } elseif ($specialty === 'other') {
            // If "Other" is selected, use the custom specialty
            $other_specialty = Utils::sanitizeInput($_POST['other_specialty'] ?? '');
            if (empty($other_specialty)) {
                $errors[] = 'Please specify your specialty';
            } else {
                $specialty = $other_specialty;
            }
        }
        
        if (empty($license_number)) {
            $errors[] = 'License number is required';
        }
        
        // Check if email is already taken by another user
        if (empty($errors)) {
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $currentUser['id']]);
            if ($stmt->fetch()) {
                $errors[] = 'Email is already taken';
            }
        }
        
        // Update if no errors
        if (empty($errors)) {
            try {
                $db->beginTransaction();
                
                // Update users table
                $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, phone = ?, bio = ? WHERE id = ?");
                $stmt->execute([$name, $email, $phone, $bio, $currentUser['id']]);
                
                // Update doctors table
                $stmt = $db->prepare("UPDATE doctors SET specialty = ?, license_number = ?, availability = ? WHERE id = ?");
                $stmt->execute([$specialty, $license_number, $availability, $currentUser['id']]);
                
                $db->commit();
                
                // Update session
                $_SESSION['user']['name'] = $name;
                $_SESSION['user']['email'] = $email;
                $_SESSION['user']['phone'] = $phone;
                $_SESSION['user']['bio'] = $bio;
                $_SESSION['user']['specialty'] = $specialty;
                $_SESSION['user']['license_number'] = $license_number;
                $_SESSION['user']['availability'] = $availability;
                
                $success[] = 'Profile updated successfully!';
                $currentUser = $auth->getCurrentUser();
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = 'Failed to update profile: ' . $e->getMessage();
            }
        }
    }
    
    // Password update
    if (isset($_POST['action']) && $_POST['action'] === 'update_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (empty($current_password)) {
            $errors[] = 'Current password is required';
        }
        
        if (empty($new_password)) {
            $errors[] = 'New password is required';
        } elseif (strlen($new_password) < 6) {
            $errors[] = 'New password must be at least 6 characters';
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = 'Passwords do not match';
        }
        
        // Verify current password
        if (empty($errors)) {
            $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$currentUser['id']]);
            $user = $stmt->fetch();
            
            if (!password_verify($current_password, $user['password_hash'])) {
                $errors[] = 'Current password is incorrect';
            }
        }
        
        // Update password if no errors
        if (empty($errors)) {
            try {
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$password_hash, $currentUser['id']]);
                
                $success[] = 'Password updated successfully!';
            } catch (Exception $e) {
                $errors[] = 'Failed to update password: ' . $e->getMessage();
            }
        }
    }
    
    // Profile image upload
    if (isset($_POST['action']) && $_POST['action'] === 'upload_avatar') {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['avatar'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            // Validate file type
            if (!in_array($file['type'], $allowed_types)) {
                $errors[] = 'Invalid file type. Only JPG, PNG, and GIF are allowed.';
            }
            
            // Validate file size
            if ($file['size'] > $max_size) {
                $errors[] = 'File size must be less than 5MB.';
            }
            
            if (empty($errors)) {
                try {
                    // Create upload directory if it doesn't exist
                    $upload_dir = __DIR__ . '/../public/assets/uploads/avatars/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    // Generate unique filename
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'doctor_' . $currentUser['id'] . '_' . time() . '.' . $extension;
                    $filepath = $upload_dir . $filename;
                    $db_path = 'assets/uploads/avatars/' . $filename;
                    
                    // Delete old avatar if exists
                    if (!empty($currentUser['avatar'])) {
                        $old_file = __DIR__ . '/../public/' . $currentUser['avatar'];
                        if (file_exists($old_file)) {
                            unlink($old_file);
                        }
                    }
                    
                    // Move uploaded file
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        // Update database
                        $stmt = $db->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                        $stmt->execute([$db_path, $currentUser['id']]);
                        
                        // Update session
                        $_SESSION['user']['avatar'] = $db_path;
                        
                        $success[] = 'Profile image updated successfully!';
                        $currentUser = $auth->getCurrentUser();
                    } else {
                        $errors[] = 'Failed to upload file.';
                    }
                } catch (Exception $e) {
                    $errors[] = 'Upload error: ' . $e->getMessage();
                }
            }
        } else {
            $errors[] = 'Please select an image to upload.';
        }
    }
    
    // Delete profile image
    if (isset($_POST['action']) && $_POST['action'] === 'delete_avatar') {
        try {
            if (!empty($currentUser['avatar'])) {
                $old_file = __DIR__ . '/../public/' . $currentUser['avatar'];
                if (file_exists($old_file)) {
                    unlink($old_file);
                }
                
                // Update database
                $stmt = $db->prepare("UPDATE users SET avatar = NULL WHERE id = ?");
                $stmt->execute([$currentUser['id']]);
                
                // Update session
                $_SESSION['user']['avatar'] = null;
                
                $success[] = 'Profile image removed successfully!';
                $currentUser = $auth->getCurrentUser();
            }
        } catch (Exception $e) {
            $errors[] = 'Failed to remove image: ' . $e->getMessage();
        }
    }
}

// Get doctor's statistics
try {
    $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as total FROM user_doctor_connections WHERE doctor_id = ?");
    $stmt->execute([$currentUser['id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalPatients = $result ? ($result['total'] ?? 0) : 0;
    
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT a.id) as total 
        FROM assessments a 
        INNER JOIN user_doctor_connections udc ON a.user_id = udc.user_id 
        WHERE udc.doctor_id = ?
    ");
    $stmt->execute([$currentUser['id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalAssessments = $result ? ($result['total'] ?? 0) : 0;
} catch (Exception $e) {
    $totalPatients = 0;
    $totalAssessments = 0;
}

$title = "My Profile - Doctor Dashboard";
require_once __DIR__ . '/includes/header.php';
?>

<style>
    .profile-card {
        transition: all 0.3s ease;
    }
    
    .profile-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }
    
    .avatar-upload {
        position: relative;
        overflow: hidden;
    }
    
    .avatar-upload input[type="file"] {
        position: absolute;
        opacity: 0;
        cursor: pointer;
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
    }
    
    .image-preview {
        transition: all 0.3s ease;
    }
    
    .image-preview:hover {
        opacity: 0.8;
    }
    
    .animate-fade-in {
        animation: fadeIn 0.3s ease-in-out;
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>

<div class="container mx-auto px-4 py-8 max-w-7xl">
    <!-- Page Header -->
    <div class="mb-8">
        <h1 class="text-4xl font-bold text-gray-900 mb-2">My Profile</h1>
        <p class="text-gray-600">Manage your professional information and account settings</p>
    </div>

    <!-- Success/Error Messages -->
    <?php if (!empty($success)): ?>
        <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-lg">
            <div class="flex">
                <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <div>
                    <?php foreach ($success as $msg): ?>
                        <p class="text-green-800 font-medium"><?php echo htmlspecialchars($msg); ?></p>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
            <div class="flex">
                <svg class="w-5 h-5 text-red-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <div>
                    <?php foreach ($errors as $error): ?>
                        <p class="text-red-800"><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Sidebar - Profile Image & Stats -->
        <div class="lg:col-span-1">
            <!-- Profile Image Card -->
            <div class="bg-white rounded-2xl shadow-lg p-6 mb-6 profile-card">
                <div class="text-center">
                    <div class="relative inline-block mb-4">
                        <div class="w-32 h-32 rounded-full overflow-hidden mx-auto ring-4 ring-accent shadow-lg">
                            <?php if (!empty($currentUser['avatar']) && file_exists(__DIR__ . '/../public/' . $currentUser['avatar'])): ?>
                                <img src="../public/<?php echo htmlspecialchars($currentUser['avatar']); ?>" alt="Profile" class="w-full h-full object-cover image-preview">
                            <?php else: ?>
                                <div class="w-full h-full bg-gradient-to-br from-primary to-accent flex items-center justify-center text-white text-4xl font-bold">
                                    <?php echo strtoupper(substr($currentUser['name'], 0, 2)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="absolute bottom-0 right-0 bg-accent text-white rounded-full p-2 shadow-lg">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </div>
                    </div>
                    
                    <h2 class="text-2xl font-bold text-gray-900 mb-1">Dr. <?php echo htmlspecialchars($currentUser['name']); ?></h2>
                    <p class="text-accent font-medium mb-4"><?php echo htmlspecialchars(ucfirst($currentUser['specialty'] ?? 'Specialist')); ?></p>
                    
                    <!-- Upload/Delete Buttons -->
                    <div class="flex flex-col gap-2 mt-4">
                        <form method="POST" enctype="multipart/form-data" id="avatarForm" class="avatar-upload">
                            <input type="hidden" name="action" value="upload_avatar">
                            <label for="avatar" class="block w-full px-4 py-2 bg-gradient-to-r from-primary to-accent text-white rounded-xl font-semibold cursor-pointer hover:shadow-lg transition-all duration-300 text-center">
                                <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                </svg>
                                Upload New Photo
                            </label>
                            <input type="file" id="avatar" name="avatar" accept="image/*" class="hidden" onchange="document.getElementById('avatarForm').submit()">
                        </form>
                        
                        <?php if (!empty($currentUser['avatar'])): ?>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to remove your profile image?')">
                                <input type="hidden" name="action" value="delete_avatar">
                                <button type="submit" class="w-full px-4 py-2 bg-red-500 text-white rounded-xl font-semibold hover:bg-red-600 transition-all duration-300">
                                    <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                    Remove Photo
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    
                    <p class="text-xs text-gray-500 mt-3">JPG, PNG or GIF • Max 5MB</p>
                </div>
            </div>

            <!-- Stats Card -->
            <div class="bg-white rounded-2xl shadow-lg p-6 profile-card">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Statistics</h3>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Total Patients</p>
                                <p class="text-xl font-bold text-gray-900"><?php echo $totalPatients; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Assessments</p>
                                <p class="text-xl font-bold text-gray-900"><?php echo $totalAssessments; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Member Since</p>
                                <p class="text-sm font-semibold text-gray-900"><?php echo date('M Y', strtotime($currentUser['created_at'])); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Content - Forms -->
        <div class="lg:col-span-2">
            <!-- Profile Information Form -->
            <div class="bg-white rounded-2xl shadow-lg p-8 mb-6 profile-card">
                <h3 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                    <svg class="w-6 h-6 text-accent mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    Professional Information
                </h3>
                
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-semibold text-gray-700 mb-2">Full Name *</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($currentUser['name']); ?>" required
                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-accent focus:outline-none transition-colors">
                        </div>
                        
                        <div>
                            <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email Address *</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($currentUser['email']); ?>" required
                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-accent focus:outline-none transition-colors">
                        </div>
                        
                        <div>
                            <label for="phone" class="block text-sm font-semibold text-gray-700 mb-2">Phone Number</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>"
                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-accent focus:outline-none transition-colors">
                        </div>
                        
                        <div>
                            <label for="specialty" class="block text-sm font-semibold text-gray-700 mb-2">Specialty *</label>
                            <select id="specialty" name="specialty" required onchange="toggleOtherSpecialty()"
                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-accent focus:outline-none transition-colors">
                                <option value="">Select Specialty</option>
                                <option value="cardiology" <?php echo ($currentUser['specialty'] ?? '') === 'cardiology' ? 'selected' : ''; ?>>Cardiology</option>
                                <option value="dermatology" <?php echo ($currentUser['specialty'] ?? '') === 'dermatology' ? 'selected' : ''; ?>>Dermatology</option>
                                <option value="endocrinology" <?php echo ($currentUser['specialty'] ?? '') === 'endocrinology' ? 'selected' : ''; ?>>Endocrinology</option>
                                <option value="gastroenterology" <?php echo ($currentUser['specialty'] ?? '') === 'gastroenterology' ? 'selected' : ''; ?>>Gastroenterology</option>
                                <option value="general_practice" <?php echo ($currentUser['specialty'] ?? '') === 'general_practice' ? 'selected' : ''; ?>>General Practice</option>
                                <option value="neurology" <?php echo ($currentUser['specialty'] ?? '') === 'neurology' ? 'selected' : ''; ?>>Neurology</option>
                                <option value="oncology" <?php echo ($currentUser['specialty'] ?? '') === 'oncology' ? 'selected' : ''; ?>>Oncology</option>
                                <option value="orthopedics" <?php echo ($currentUser['specialty'] ?? '') === 'orthopedics' ? 'selected' : ''; ?>>Orthopedics</option>
                                <option value="pediatrics" <?php echo ($currentUser['specialty'] ?? '') === 'pediatrics' ? 'selected' : ''; ?>>Pediatrics</option>
                                <option value="psychiatry" <?php echo ($currentUser['specialty'] ?? '') === 'psychiatry' ? 'selected' : ''; ?>>Psychiatry</option>
                                <option value="other">Other (Please Specify)</option>
                            </select>
                            
                            <!-- Other Specialty Input (Hidden by default) -->
                            <div id="otherSpecialtyContainer" class="mt-3 hidden">
                                <input type="text" id="other_specialty" name="other_specialty" 
                                    value="<?php 
                                        $predefinedSpecialties = ['cardiology', 'dermatology', 'endocrinology', 'gastroenterology', 'general_practice', 'neurology', 'oncology', 'orthopedics', 'pediatrics', 'psychiatry'];
                                        $currentSpecialty = $currentUser['specialty'] ?? '';
                                        echo !in_array($currentSpecialty, $predefinedSpecialties) && !empty($currentSpecialty) ? htmlspecialchars($currentSpecialty) : ''; 
                                    ?>"
                                    placeholder="Please specify your specialty"
                                    class="w-full px-4 py-3 border-2 border-accent rounded-xl focus:border-primary focus:outline-none transition-colors bg-accent/5">
                                <p class="text-xs text-gray-500 mt-1">Enter your specific medical specialty</p>
                            </div>
                        </div>
                        
                        <div>
                            <label for="license_number" class="block text-sm font-semibold text-gray-700 mb-2">License Number *</label>
                            <input type="text" id="license_number" name="license_number" value="<?php echo htmlspecialchars($currentUser['license_number'] ?? ''); ?>" required
                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-accent focus:outline-none transition-colors">
                        </div>
                        
                        <div>
                            <label for="availability" class="block text-sm font-semibold text-gray-700 mb-2">Availability Status</label>
                            <select id="availability" name="availability"
                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-accent focus:outline-none transition-colors">
                                <option value="online" <?php echo ($currentUser['availability'] ?? 'offline') === 'online' ? 'selected' : ''; ?>>Available Online</option>
                                <option value="offline" <?php echo ($currentUser['availability'] ?? 'offline') === 'offline' ? 'selected' : ''; ?>>Offline</option>
                                <option value="busy" <?php echo ($currentUser['availability'] ?? 'offline') === 'busy' ? 'selected' : ''; ?>>Busy</option>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label for="bio" class="block text-sm font-semibold text-gray-700 mb-2">Professional Bio</label>
                        <textarea id="bio" name="bio" rows="4"
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-accent focus:outline-none transition-colors"
                            placeholder="Tell patients about your experience, specializations, and approach to healthcare..."><?php echo htmlspecialchars($currentUser['bio'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" class="px-8 py-3 bg-gradient-to-r from-primary to-accent text-white rounded-xl font-semibold hover:shadow-lg transition-all duration-300 transform hover:scale-105">
                            <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>

            <!-- Change Password Form -->
            <div class="bg-white rounded-2xl shadow-lg p-8 profile-card">
                <h3 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                    <svg class="w-6 h-6 text-accent mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    Change Password
                </h3>
                
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="action" value="update_password">
                    
                    <div>
                        <label for="current_password" class="block text-sm font-semibold text-gray-700 mb-2">Current Password *</label>
                        <input type="password" id="current_password" name="current_password" required
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-accent focus:outline-none transition-colors">
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="new_password" class="block text-sm font-semibold text-gray-700 mb-2">New Password *</label>
                            <input type="password" id="new_password" name="new_password" required minlength="6"
                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-accent focus:outline-none transition-colors">
                            <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
                        </div>
                        
                        <div>
                            <label for="confirm_password" class="block text-sm font-semibold text-gray-700 mb-2">Confirm New Password *</label>
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="6"
                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-accent focus:outline-none transition-colors">
                        </div>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" class="px-8 py-3 bg-gradient-to-r from-red-500 to-pink-500 text-white rounded-xl font-semibold hover:shadow-lg transition-all duration-300 transform hover:scale-105">
                            <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Toggle "Other Specialty" input field
    function toggleOtherSpecialty() {
        const specialtySelect = document.getElementById('specialty');
        const otherContainer = document.getElementById('otherSpecialtyContainer');
        const otherInput = document.getElementById('other_specialty');
        
        if (specialtySelect.value === 'other') {
            otherContainer.classList.remove('hidden');
            otherContainer.classList.add('animate-fade-in');
            otherInput.setAttribute('required', 'required');
            otherInput.focus();
        } else {
            otherContainer.classList.add('hidden');
            otherInput.removeAttribute('required');
            otherInput.value = '';
        }
    }
    
    // Check on page load if "other" was previously selected
    document.addEventListener('DOMContentLoaded', function() {
        const specialtySelect = document.getElementById('specialty');
        const otherInput = document.getElementById('other_specialty');
        
        // If there's a value in the other_specialty input, it means user has a custom specialty
        if (otherInput.value.trim() !== '') {
            specialtySelect.value = 'other';
            toggleOtherSpecialty();
        }
    });
    
    // Form validation before submission
    document.querySelector('form[action=""][method="POST"]').addEventListener('submit', function(e) {
        const specialtySelect = document.getElementById('specialty');
        const otherInput = document.getElementById('other_specialty');
        
        if (specialtySelect.value === 'other' && otherInput.value.trim() === '') {
            e.preventDefault();
            alert('Please specify your specialty when selecting "Other"');
            otherInput.focus();
            return false;
        }
    });
</script>

</main>
</body>
</html>
