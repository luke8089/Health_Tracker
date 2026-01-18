<?php
/**
 * Profile Page
 * User profile management with modern UI and smooth animations
 */

require_once __DIR__ . '/../src/helpers/Auth.php';
require_once __DIR__ . '/../src/helpers/Database.php';

$auth = new Auth();
$auth->requireLogin();
$currentUser = $auth->getCurrentUser();

$database = new Database();
$db = $database->connect();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $avatarPath = $currentUser['avatar']; // Keep existing avatar by default
        
        // Handle avatar upload
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 5 * 1024 * 1024; // 5MB
            
            $fileType = $_FILES['avatar']['type'];
            $fileSize = $_FILES['avatar']['size'];
            
            if (!in_array($fileType, $allowedTypes)) {
                $_SESSION['flash_message'] = 'Invalid file type. Please upload a JPG, PNG, GIF, or WebP image.';
                $_SESSION['flash_type'] = 'error';
            } elseif ($fileSize > $maxSize) {
                $_SESSION['flash_message'] = 'File is too large. Maximum size is 5MB.';
                $_SESSION['flash_type'] = 'error';
            } else {
                // Create uploads directory if it doesn't exist
                $uploadDir = __DIR__ . '/assets/uploads/avatars/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Generate unique filename
                $extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                $filename = 'avatar_' . $currentUser['id'] . '_' . time() . '.' . $extension;
                $uploadPath = $uploadDir . $filename;
                
                // Delete old avatar if exists
                if (!empty($currentUser['avatar']) && file_exists(__DIR__ . '/' . $currentUser['avatar'])) {
                    unlink(__DIR__ . '/' . $currentUser['avatar']);
                }
                
                // Move uploaded file
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadPath)) {
                    $avatarPath = 'assets/uploads/avatars/' . $filename;
                } else {
                    $_SESSION['flash_message'] = 'Failed to upload avatar';
                    $_SESSION['flash_type'] = 'error';
                }
            }
        }
        
        // Validate inputs
        if (empty($name)) {
            $_SESSION['flash_message'] = 'Name is required';
            $_SESSION['flash_type'] = 'error';
        } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_message'] = 'Valid email is required';
            $_SESSION['flash_type'] = 'error';
        } else {
            // Check if email is already taken by another user
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $currentUser['id']]);
            
            if ($stmt->fetch()) {
                $_SESSION['flash_message'] = 'Email is already taken';
                $_SESSION['flash_type'] = 'error';
            } else {
                // Update user profile
                $stmt = $db->prepare("
                    UPDATE users 
                    SET name = ?, email = ?, phone = ?, bio = ?, avatar = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                
                if ($stmt->execute([$name, $email, $phone, $bio, $avatarPath, $currentUser['id']])) {
                    $_SESSION['flash_message'] = 'Profile updated successfully!';
                    $_SESSION['flash_type'] = 'success';
                    
                    // Update session
                    $_SESSION['user']['name'] = $name;
                    $_SESSION['user']['email'] = $email;
                    $_SESSION['user']['phone'] = $phone;
                    $_SESSION['user']['bio'] = $bio;
                    $_SESSION['user']['avatar'] = $avatarPath;
                    
                    // Refresh current user
                    $currentUser = $auth->getCurrentUser();
                    
                    header('Location: profile.php');
                    exit;
                } else {
                    $_SESSION['flash_message'] = 'Failed to update profile';
                    $_SESSION['flash_type'] = 'error';
                }
            }
        }
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'An error occurred: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
    }
}

// Get user statistics
try {
    // Get assessments count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM assessments WHERE user_id = ?");
    $stmt->execute([$currentUser['id']]);
    $assessmentsCount = $stmt->fetch()['count'];
    
    // Get habits count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM habits WHERE user_id = ?");
    $stmt->execute([$currentUser['id']]);
    $habitsCount = $stmt->fetch()['count'];
    
    // Get messages count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM messages WHERE sender_id = ? OR recipient_id = ?");
    $stmt->execute([$currentUser['id'], $currentUser['id']]);
    $messagesCount = $stmt->fetch()['count'];
    
    // Get latest assessment
    $stmt = $db->prepare("SELECT * FROM assessments WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$currentUser['id']]);
    $latestAssessment = $stmt->fetch();
    
    // Calculate days since joined
    $joinedDate = new DateTime($currentUser['created_at']);
    $now = new DateTime();
    $daysSinceJoined = $now->diff($joinedDate)->days;
    
} catch (Exception $e) {
    $assessmentsCount = 0;
    $habitsCount = 0;
    $messagesCount = 0;
    $latestAssessment = null;
    $daysSinceJoined = 0;
}

$pageTitle = 'My Profile';
require_once __DIR__ . '/../src/views/partials/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Health Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '#1f2937',
                        'accent': '#34d399'
                    }
                }
            }
        }
    </script>
    <style>
        /* Smooth Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        .fade-in-up {
            animation: fadeInUp 0.6s ease-out forwards;
            opacity: 0;
        }
        
        .slide-in-left {
            animation: slideInLeft 0.6s ease-out forwards;
            opacity: 0;
        }
        
        .slide-in-right {
            animation: slideInRight 0.6s ease-out forwards;
            opacity: 0;
        }
        
        .scale-in {
            animation: scaleIn 0.5s ease-out forwards;
            opacity: 0;
        }
        
        .delay-100 { animation-delay: 0.1s; }
        .delay-200 { animation-delay: 0.2s; }
        .delay-300 { animation-delay: 0.3s; }
        .delay-400 { animation-delay: 0.4s; }
        .delay-500 { animation-delay: 0.5s; }
        
        /* Glassmorphism */
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(161, 209, 177, 0.2);
            box-shadow: 0 8px 32px rgba(28, 37, 41, 0.1);
        }
        
        /* Smooth Hover */
        .smooth-hover {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .smooth-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(28, 37, 41, 0.15);
        }
        
        /* Gradient Background */
        .gradient-bg {
            background: linear-gradient(135deg, #34d399 0%, #1f2937 100%);
        }
        
        /* Avatar Gradient */
        .avatar-gradient {
            background: linear-gradient(135deg, #1f2937 0%, #34d399 50%, #34d399 100%);
        }
        
        /* Gentle Glow */
        @keyframes gentleGlow {
            0%, 100% {
                box-shadow: 0 0 20px rgba(161, 209, 177, 0.3);
            }
            50% {
                box-shadow: 0 0 40px rgba(161, 209, 177, 0.6);
            }
        }
        
        .gentle-glow {
            animation: gentleGlow 3s ease-in-out infinite;
        }
        
        /* Progress Bar Animation */
        @keyframes progressFill {
            from {
                width: 0%;
            }
        }
        
        .progress-fill {
            animation: progressFill 1.5s ease-out forwards;
        }
        
        /* Input Focus */
        .input-focus {
            transition: all 0.3s ease;
        }
        
        .input-focus:focus {
            border-color: #34d399;
            box-shadow: 0 0 0 3px rgba(161, 209, 177, 0.1);
            transform: translateY(-2px);
        }
        
        /* Badge Pulse */
        @keyframes badgePulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }
        
        .badge-pulse {
            animation: badgePulse 2s ease-in-out infinite;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">

    <div class="container mx-auto px-4 py-8 max-w-7xl">
        
        <!-- Flash Messages -->
        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="mb-6 fade-in-up">
                <div class="<?php echo $_SESSION['flash_type'] === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'; ?> border-2 rounded-2xl p-4 flex items-center gap-3 shadow-lg">
                    <?php if ($_SESSION['flash_type'] === 'success'): ?>
                        <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    <?php else: ?>
                        <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    <?php endif; ?>
                    <p class="font-medium"><?php echo htmlspecialchars($_SESSION['flash_message']); ?></p>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-auto text-current hover:opacity-70 transition-opacity">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
            <?php 
                unset($_SESSION['flash_message']);
                unset($_SESSION['flash_type']);
            ?>
        <?php endif; ?>
        
        <!-- Page Header -->
        <div class="mb-8 fade-in-up">
            <h1 class="text-4xl font-bold text-gray-900 mb-2">My Profile</h1>
            <p class="text-gray-600">Manage your personal information and view your health journey</p>
        </div>

        <div class="grid lg:grid-cols-3 gap-8">
            
            <!-- Left Column - Profile Card & Stats -->
            <div class="lg:col-span-1 space-y-6">
                
                <!-- Profile Card -->
                <div class="glass-card rounded-3xl p-8 text-center smooth-hover slide-in-left">
                    <form id="avatarForm" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="name" value="<?php echo htmlspecialchars($currentUser['name']); ?>">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($currentUser['email']); ?>">
                        <input type="hidden" name="phone" value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>">
                        <input type="hidden" name="bio" value="<?php echo htmlspecialchars($currentUser['bio'] ?? ''); ?>">
                        
                        <div class="relative inline-block mb-6 group cursor-pointer" onclick="document.getElementById('avatarInput').click()">
                            <div class="w-32 h-32 rounded-full flex items-center justify-center text-white text-5xl font-bold shadow-xl gentle-glow mx-auto overflow-hidden relative transition-all duration-300 group-hover:shadow-2xl" id="avatarPreview">
                                <?php if (!empty($currentUser['avatar']) && file_exists(__DIR__ . '/' . $currentUser['avatar'])): ?>
                                    <img src="<?php echo htmlspecialchars($currentUser['avatar']); ?>" alt="Profile" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="w-full h-full avatar-gradient flex items-center justify-center">
                                        <?php echo strtoupper(substr($currentUser['name'], 0, 2)); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Overlay on hover -->
                                <div class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                    <div class="text-center">
                                        <svg class="w-8 h-8 text-white mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        <span class="text-white text-xs font-semibold">Change Photo</span>
                                    </div>
                                </div>
                            </div>
                            <div class="absolute bottom-2 right-2 w-6 h-6 bg-green-500 border-4 border-white rounded-full"></div>
                            
                            <!-- Camera icon badge -->
                            <div class="absolute bottom-0 right-0 w-10 h-10 bg-gradient-to-br from-accent to-primary rounded-full flex items-center justify-center shadow-lg transform transition-transform group-hover:scale-110 border-4 border-white">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            </div>
                        </div>
                        
                        <input 
                            type="file" 
                            id="avatarInput" 
                            name="avatar" 
                            accept="image/jpeg,image/png,image/gif,image/webp" 
                            class="hidden"
                            onchange="previewAndUploadAvatar(this)"
                        >
                    </form>
                    
                    <h2 class="text-2xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($currentUser['name']); ?></h2>
                    <p class="text-green-400 font-semibold mb-4"><?php echo ucfirst($currentUser['role']); ?></p>
                    
                    <?php if (!empty($currentUser['bio'])): ?>
                        <p class="text-gray-600 text-sm leading-relaxed mb-6">
                            <?php echo htmlspecialchars($currentUser['bio']); ?>
                        </p>
                    <?php else: ?>
                        <p class="text-gray-400 text-sm italic mb-6">No bio yet. Add one below!</p>
                    <?php endif; ?>
                    
                    <div class="flex items-center justify-center gap-2 text-sm text-gray-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span>Member for <?php echo $daysSinceJoined; ?> days</span>
                    </div>
                </div>
                
                <!-- Quick Stats -->
                <div class="glass-card rounded-3xl p-6 smooth-hover slide-in-left delay-200">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        Your Activity
                    </h3>
                    
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                </div>
                                <span class="text-gray-700 font-medium">Assessments</span>
                            </div>
                            <span class="text-2xl font-bold text-gray-900 badge-pulse"><?php echo $assessmentsCount; ?></span>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-green-600 rounded-xl flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <span class="text-gray-700 font-medium">Habits</span>
                            </div>
                            <span class="text-2xl font-bold text-gray-900 badge-pulse"><?php echo $habitsCount; ?></span>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                    </svg>
                                </div>
                                <span class="text-gray-700 font-medium">Messages</span>
                            </div>
                            <span class="text-2xl font-bold text-gray-900 badge-pulse"><?php echo $messagesCount; ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Latest Assessment -->
                <?php if ($latestAssessment): ?>
                <div class="glass-card rounded-3xl p-6 smooth-hover slide-in-left delay-300">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                        </svg>
                        Latest Assessment
                    </h3>
                    
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600">Score</span>
                            <span class="text-2xl font-bold text-gray-900"><?php echo $latestAssessment['score']; ?>%</span>
                        </div>
                        
                        <div class="space-y-2">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-600">Status</span>
                                <span class="px-3 py-1 rounded-full text-xs font-semibold <?php 
                                    echo $latestAssessment['severity'] === 'excellent' ? 'bg-green-100 text-green-700' :
                                        ($latestAssessment['severity'] === 'good' ? 'bg-blue-100 text-blue-700' :
                                        ($latestAssessment['severity'] === 'fair' ? 'bg-yellow-100 text-yellow-700' :
                                        ($latestAssessment['severity'] === 'poor' ? 'bg-orange-100 text-orange-700' : 'bg-red-100 text-red-700')));
                                ?>">
                                    <?php echo ucfirst($latestAssessment['severity']); ?>
                                </span>
                            </div>
                            
                            <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                                <div class="progress-fill h-full bg-gradient-to-r from-accent to-primary rounded-full" 
                                     style="width: <?php echo $latestAssessment['score']; ?>%"></div>
                            </div>
                        </div>
                        
                        <p class="text-sm text-gray-500">
                            <?php 
                                $date = new DateTime($latestAssessment['created_at']);
                                echo $date->format('M j, Y');
                            ?>
                        </p>
                        
                        <a href="assessment.php" class="block w-full text-center bg-gradient-to-r from-accent to-primary text-white font-semibold py-2 px-4 rounded-xl hover:shadow-lg transition-all duration-300 transform hover:scale-105">
                            Take New Assessment
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
            </div>
            
            <!-- Right Column - Edit Form -->
            <div class="lg:col-span-2">
                <div class="glass-card rounded-3xl p-8 smooth-hover slide-in-right">
                    <div class="flex items-center justify-between mb-8">
                        <h3 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
                            <svg class="w-7 h-7 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Edit Profile
                        </h3>
                    </div>
                    
                    <form method="POST" class="space-y-6">
                        <!-- Name -->
                        <div class="fade-in-up delay-100">
                            <label for="name" class="block text-sm font-semibold text-gray-700 mb-2">
                                Full Name *
                            </label>
                            <input 
                                type="text" 
                                id="name" 
                                name="name" 
                                value="<?php echo htmlspecialchars($currentUser['name']); ?>"
                                required
                                class="w-full px-4 py-3 bg-white border-2 border-gray-200 rounded-xl focus:outline-none input-focus"
                                placeholder="Enter your full name"
                            >
                        </div>
                        
                        <!-- Email -->
                        <div class="fade-in-up delay-200">
                            <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">
                                Email Address *
                            </label>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                value="<?php echo htmlspecialchars($currentUser['email']); ?>"
                                required
                                class="w-full px-4 py-3 bg-white border-2 border-gray-200 rounded-xl focus:outline-none input-focus"
                                placeholder="your.email@example.com"
                            >
                        </div>
                        
                        <!-- Phone -->
                        <div class="fade-in-up delay-300">
                            <label for="phone" class="block text-sm font-semibold text-gray-700 mb-2">
                                Phone Number
                            </label>
                            <input 
                                type="tel" 
                                id="phone" 
                                name="phone" 
                                value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>"
                                class="w-full px-4 py-3 bg-white border-2 border-gray-200 rounded-xl focus:outline-none input-focus"
                                placeholder="+1 (555) 123-4567"
                            >
                        </div>
                        
                        <!-- Bio -->
                        <div class="fade-in-up delay-400">
                            <label for="bio" class="block text-sm font-semibold text-gray-700 mb-2">
                                Bio
                            </label>
                            <textarea 
                                id="bio" 
                                name="bio" 
                                rows="4"
                                class="w-full px-4 py-3 bg-white border-2 border-gray-200 rounded-xl focus:outline-none input-focus resize-none"
                                placeholder="Tell us a bit about yourself..."
                            ><?php echo htmlspecialchars($currentUser['bio'] ?? ''); ?></textarea>
                            <p class="mt-2 text-sm text-gray-500">Brief description for your profile. Max 500 characters.</p>
                        </div>
                        
                        <!-- Account Info -->
                        <div class="fade-in-up delay-500">
                            <div class="bg-gray-50 rounded-2xl p-6 border border-gray-200">
                                <h4 class="font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Account Information
                                </h4>
                                <div class="grid md:grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <span class="text-gray-600">Account Type:</span>
                                        <span class="ml-2 font-semibold text-gray-900"><?php echo ucfirst($currentUser['role']); ?></span>
                                    </div>
                                    <div>
                                        <span class="text-gray-600">Member Since:</span>
                                        <span class="ml-2 font-semibold text-gray-900">
                                            <?php 
                                                $joinedDate = new DateTime($currentUser['created_at']);
                                                echo $joinedDate->format('M j, Y');
                                            ?>
                                        </span>
                                    </div>
                                    <div>
                                        <span class="text-gray-600">Last Updated:</span>
                                        <span class="ml-2 font-semibold text-gray-900">
                                            <?php 
                                                $updatedDate = new DateTime($currentUser['updated_at']);
                                                echo $updatedDate->format('M j, Y');
                                            ?>
                                        </span>
                                    </div>
                                    <div>
                                        <span class="text-gray-600">User ID:</span>
                                        <span class="ml-2 font-semibold text-gray-900">#<?php echo str_pad($currentUser['id'], 6, '0', STR_PAD_LEFT); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="flex flex-col sm:flex-row gap-4 pt-4 fade-in-up delay-500">
                            <button 
                                type="submit" 
                                class="flex-1 bg-gradient-to-r from-accent to-primary text-white font-semibold py-4 px-6 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105 hover:-translate-y-1 flex items-center justify-center gap-2"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Save Changes
                            </button>
                            <a 
                                href="dashboard.php" 
                                class="flex-1 sm:flex-initial bg-gray-100 text-gray-700 font-semibold py-4 px-6 rounded-xl hover:bg-gray-200 transition-all duration-300 transform hover:scale-105 text-center"
                            >
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- Quick Actions -->
                <div class="grid md:grid-cols-3 gap-4 mt-6">
                    <a href="settings.php" class="glass-card rounded-2xl p-6 text-center smooth-hover scale-in delay-200">
                        <div class="w-12 h-12 bg-gradient-to-br from-accent to-primary rounded-xl flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </div>
                        <h4 class="font-semibold text-gray-900 mb-1">Settings</h4>
                        <p class="text-sm text-gray-600">Manage preferences</p>
                    </a>
                    
                    <a href="assessment.php" class="glass-card rounded-2xl p-6 text-center smooth-hover scale-in delay-300">
                        <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                        </div>
                        <h4 class="font-semibold text-gray-900 mb-1">Assessment</h4>
                        <p class="text-sm text-gray-600">Take health test</p>
                    </a>
                    
                    <a href="messages.php" class="glass-card rounded-2xl p-6 text-center smooth-hover scale-in delay-400">
                        <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                        </div>
                        <h4 class="font-semibold text-gray-900 mb-1">Messages</h4>
                        <p class="text-sm text-gray-600">Contact support</p>
                    </a>
                </div>
            </div>
            
        </div>
    </div>

    <script>
        // Avatar preview and upload
        function previewAndUploadAvatar(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Please upload a valid image file (JPG, PNG, GIF, or WebP)');
                    input.value = '';
                    return;
                }
                
                // Validate file size (5MB max)
                if (file.size > 5 * 1024 * 1024) {
                    alert('File is too large. Maximum size is 5MB.');
                    input.value = '';
                    return;
                }
                
                // Preview the image
                const reader = new FileReader();
                reader.onload = function(e) {
                    const avatarPreview = document.getElementById('avatarPreview');
                    avatarPreview.innerHTML = `
                        <img src="${e.target.result}" alt="Profile Preview" class="w-full h-full object-cover">
                        <div class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                            <div class="text-center">
                                <svg class="w-8 h-8 text-white mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <span class="text-white text-xs font-semibold">Change Photo</span>
                            </div>
                        </div>
                    `;
                    
                    // Show confirmation and submit
                    if (confirm('Upload this photo as your profile picture?')) {
                        // Show loading indicator
                        const avatarPreview = document.getElementById('avatarPreview');
                        const loadingOverlay = document.createElement('div');
                        loadingOverlay.className = 'absolute inset-0 bg-black bg-opacity-70 flex items-center justify-center rounded-full';
                        loadingOverlay.innerHTML = `
                            <div class="text-center">
                                <svg class="animate-spin h-8 w-8 text-white mx-auto mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="text-white text-xs">Uploading...</span>
                            </div>
                        `;
                        avatarPreview.appendChild(loadingOverlay);
                        
                        // Submit the form
                        document.getElementById('avatarForm').submit();
                    } else {
                        // Reset the input and preview
                        input.value = '';
                        location.reload();
                    }
                };
                reader.readAsDataURL(file);
            }
        }
        
        // Form validation for main edit form
        const editForm = document.querySelector('form[method="POST"]:not(#avatarForm)');
        if (editForm) {
            editForm.addEventListener('submit', function(e) {
                const name = document.getElementById('name').value.trim();
                const email = document.getElementById('email').value.trim();
                
                if (!name) {
                    e.preventDefault();
                    alert('Please enter your name');
                    return;
                }
                
                if (!email || !email.includes('@')) {
                    e.preventDefault();
                    alert('Please enter a valid email address');
                    return;
                }
            });
        }
        
        // Character counter for bio
        const bioTextarea = document.getElementById('bio');
        if (bioTextarea) {
            bioTextarea.addEventListener('input', function() {
                if (this.value.length > 500) {
                    this.value = this.value.substring(0, 500);
                }
            });
        }
        
        // Input animations
        const inputs = document.querySelectorAll('input:not([type="file"]), textarea');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                if (this.parentElement) {
                    this.parentElement.style.transform = 'scale(1.01)';
                }
            });
            
            input.addEventListener('blur', function() {
                if (this.parentElement) {
                    this.parentElement.style.transform = 'scale(1)';
                }
            });
        });
    </script>

</body>
</html>

<?php require_once __DIR__ . '/../src/views/partials/footer.php'; ?>
