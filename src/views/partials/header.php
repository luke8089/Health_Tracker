<?php
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Utils.php';
require_once __DIR__ . '/../../helpers/Config.php';

$auth = new Auth();
$currentUser = $auth->getCurrentUser();
// Ensure $currentUser is always an array to prevent array offset warnings
if (!is_array($currentUser)) {
    $currentUser = ['id' => null, 'name' => 'Guest', 'role' => 'guest', 'email' => ''];
}
$flashMessage = Utils::getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ?? 'Health Tracker'; ?></title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '#1f2937',
                        'primary-dark': '#111827',
                        'accent': '#34d399',
                        'accent-light': '#6ee7b7',
                        'success': '#34d399',
                        'warning': '#f59e0b',
                        'danger': '#ef4444'
                    },
                    fontFamily: {
                        'sans': ['Inter', 'system-ui', 'sans-serif']
                    }
                }
            }
        }
    </script>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Pacifico&display=swap" rel="stylesheet">
    
    <!-- Heroicons for icons -->
    <script src="https://unpkg.com/heroicons@2.0.18/24/outline/index.js" type="module"></script>
    
    <style>
        :root {
            --black-color: #101117;
            --white-color: #ffffff;
            --primary: #1f2937;
            --accent: #34d399;
        }
        
        body {
            font-family: 'Inter', system-ui, sans-serif;
            padding-top: 4rem;
        }
        
        /* Advanced Glassmorphism Effect */
        .header-glass {
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            background: rgba(255, 255, 255, 0.92);
            border-bottom: 1px solid rgba(52, 211, 153, 0.2);
            box-shadow: 0 8px 32px rgba(31, 41, 55, 0.1);
        }
        
        .header-transition {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* Modern Navigation Links */
        .nav-link {
            position: relative;
            overflow: visible;
        }
        
        .nav-link::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, #1f2937 0%, #34d399 100%);
            transition: width 0.3s ease, left 0.3s ease;
            transform: translateX(-50%);
            border-radius: 2px;
        }
        
        .nav-link:hover::before,
        .nav-link.active::before {
            width: 70%;
        }
        
        .nav-link::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(52, 211, 153, 0.08) 0%, rgba(31, 41, 55, 0.03) 100%);
            opacity: 0;
            transition: opacity 0.25s ease;
            border-radius: 0.75rem;
            pointer-events: none;
        }
        
        .nav-link:hover::after {
            opacity: 1;
        }
        
        /* Animated Icons */
        .icon-rotate {
            transition: transform 0.3s ease;
        }
        
        .nav-link:hover .icon-rotate {
            transform: rotate(15deg);
        }
        
        .icon-bounce {
            transition: transform 0.3s ease;
        }
        
        .nav-link:hover .icon-bounce {
            transform: translateY(-2px);
        }
        
        .icon-pulse {
            animation: icon-pulse 2s ease-in-out infinite;
        }
        
        @keyframes icon-pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.03); }
        }
        
        /* Logo Animation */
        @keyframes logo-float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            25% { transform: translateY(-3px) rotate(-2deg); }
            75% { transform: translateY(-3px) rotate(2deg); }
        }
        
        .logo-float {
            animation: logo-float 4s ease-in-out infinite;
        }
        
        @keyframes logo-glow {
            0%, 100% { 
                box-shadow: 0 0 10px rgba(52, 211, 153, 0.3),
                           0 0 20px rgba(31, 41, 55, 0.2);
            }
            50% { 
                box-shadow: 0 0 20px rgba(52, 211, 153, 0.6),
                           0 0 30px rgba(31, 41, 55, 0.3),
                           0 0 40px rgba(52, 211, 153, 0.2);
            }
        }
        
        .logo-glow {
            animation: logo-glow 3s ease-in-out infinite;
        }
        
        /* Notification Bell Animation */
        @keyframes bell-ring {
            0%, 100% { transform: rotate(0deg); }
            10%, 30% { transform: rotate(-10deg); }
            20%, 40% { transform: rotate(10deg); }
        }
        
        .bell-ring:hover {
            animation: bell-ring 0.5s ease-in-out;
        }
        
        /* Pulse Dot Animation */
        @keyframes pulse-dot {
            0%, 100% { 
                transform: scale(1);
                opacity: 1;
            }
            50% { 
                transform: scale(1.5);
                opacity: 0.7;
            }
        }
        
        .pulse-dot {
            animation: pulse-dot 2s ease-in-out infinite;
        }
        
        .pulse-dot::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: inherit;
            border-radius: inherit;
            animation: pulse-ring 2s ease-in-out infinite;
        }
        
        @keyframes pulse-ring {
            0% {
                transform: scale(1);
                opacity: 1;
            }
            100% {
                transform: scale(2);
                opacity: 0;
            }
        }
        
        /* Dropdown Animation */
        .dropdown-enter {
            animation: dropdown-slide-in 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        
        @keyframes dropdown-slide-in {
            from {
                opacity: 0;
                transform: translateY(-10px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        /* Profile Avatar */
        .avatar-gradient {
            background: linear-gradient(135deg, #1f2937 0%, #34d399 100%);
            position: relative;
            overflow: hidden;
        }
        
        .avatar-gradient::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transform: rotate(45deg);
            animation: avatar-shine 3s ease-in-out infinite;
        }
        
        @keyframes avatar-shine {
            0%, 100% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            50% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }
        
        /* Hover Effects */
        .hover-lift {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .hover-lift:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(28, 37, 41, 0.15);
        }
        
        /* Smooth Slide Down */
        @keyframes slideInDown {
            from {
                transform: translate3d(0, -100%, 0);
                opacity: 0;
            }
            to {
                transform: translate3d(0, 0, 0);
                opacity: 1;
            }
        }
        
        .slide-in-down {
            animation: slideInDown 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }
        
        /* Gradient Text Animation */
        @keyframes gradient-shift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        
        .gradient-text {
            background: linear-gradient(90deg, #1f2937, #34d399, #1f2937);
            background-size: 200% auto;
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: gradient-shift 3s ease infinite;
        }
        
        /* Ripple Effect */
        .ripple {
            position: relative;
            overflow: hidden;
        }
        
        .ripple::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(52, 211, 153, 0.4);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .ripple:active::after {
            width: 300px;
            height: 300px;
        }
        
        /* Smooth Transitions */
        * {
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    
    <!-- Navigation Header -->
    <nav id="mainNav" class="fixed top-0 left-0 right-0 z-50 bg-white/90 backdrop-blur-md border-b border-white/20 header-transition slide-in-down">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Logo and Brand -->
                <div class="flex items-center">
                    <a href="<?php echo $auth->isLoggedIn() ? '/health-tracker/public/dashboard.php' : '/health-tracker/index.php'; ?>" class="flex items-center space-x-2 sm:space-x-3 group">
                        <div class="w-8 h-8 sm:w-10 sm:h-10 avatar-gradient rounded-xl flex items-center justify-center shadow-lg group-hover:shadow-2xl transition-all duration-500 transform group-hover:scale-110 group-hover:rotate-3 logo-float logo-glow">
                            <svg class="w-5 h-5 sm:w-6 sm:h-6 text-white icon-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                        </div>
                        <span class="text-lg sm:text-xl gradient-text" style="font-family: 'Pacifico', cursive; font-weight: 400;">Health Tracker</span>
                    </a>
                </div>

                <?php if ($auth->isLoggedIn()): ?>
                <!-- Mobile Menu Button -->
                <div class="flex items-center md:hidden">
                    <button id="mobileMenuBtn" class="p-2 rounded-lg text-gray-700 hover:bg-green-400/10 transition-all duration-200">
                        <svg id="menuIcon" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                        <svg id="closeIcon" class="w-6 h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <!-- Main Navigation -->
                <div class="hidden md:flex items-center space-x-2">
                    <?php if ($currentUser['role'] === 'user'): ?>
                        <a href="/health-tracker/public/dashboard.php" class="nav-link flex items-center px-4 py-2 text-gray-700 hover:text-gray-900 rounded-xl transition-all duration-200 hover:bg-green-400/10 <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active bg-gradient-to-r from-gray-800 to-green-400 text-white shadow-lg' : ''; ?>">
                            <svg class="w-4 h-4 mr-2 icon-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                            </svg>
                            Dashboard
                        </a>
                        <a href="/health-tracker/public/assessment.php" class="nav-link flex items-center px-4 py-2 text-gray-700 hover:text-gray-900 rounded-xl transition-all duration-200 hover:bg-green-400/10 <?php echo basename($_SERVER['PHP_SELF']) === 'assessment.php' ? 'active bg-gradient-to-r from-gray-800 to-green-400 text-white shadow-lg' : ''; ?>">
                            <svg class="w-4 h-4 mr-2 icon-rotate" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                            </svg>
                            Assessment
                        </a>
                        <a href="/health-tracker/public/habits.php" class="nav-link flex items-center px-4 py-2 text-gray-700 hover:text-gray-900 rounded-xl transition-all duration-200 hover:bg-green-400/10 <?php echo basename($_SERVER['PHP_SELF']) === 'habits.php' ? 'active bg-gradient-to-r from-gray-800 to-green-400 text-white shadow-lg' : ''; ?>">
                            <svg class="w-4 h-4 mr-2 icon-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Habits
                        </a>
                        
                        <!-- Activities Dropdown -->
                        <div class="relative" id="activitiesDropdownContainer" onmouseenter="showActivitiesDropdown()" onmouseleave="hideActivitiesDropdown()">
                            <button class="nav-link flex items-center px-4 py-2 text-gray-700 hover:text-gray-900 rounded-xl transition-all duration-200 hover:bg-green-400/10 <?php echo basename($_SERVER['PHP_SELF']) === 'activities.php' ? 'active bg-gradient-to-r from-gray-800 to-green-400 text-white shadow-lg' : ''; ?>">
                                <svg class="w-4 h-4 mr-2 icon-rotate" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                Activities
                                <svg id="activitiesDropdownIcon" class="w-3 h-3 ml-1 transition-all duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            
                            <div id="activitiesDropdown" class="hidden absolute left-0 mt-2 w-48 bg-white/95 backdrop-blur-xl rounded-2xl shadow-2xl border border-gray-100 py-2 z-50 dropdown-enter">
                                <a href="/health-tracker/public/physical_assessment.php" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gradient-to-r hover:from-green-50 hover:to-emerald-50 hover:text-green-700 transition-all duration-200 mx-2 rounded-xl">
                                    <svg class="w-4 h-4 mr-3 icon-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Physical Assessment
                                </a>
                                <a href="/health-tracker/public/mental_assessment.php" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gradient-to-r hover:from-purple-50 hover:to-indigo-50 hover:text-purple-700 transition-all duration-200 mx-2 rounded-xl">
                                    <svg class="w-4 h-4 mr-3 icon-rotate" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                    </svg>
                                    Mental Assessment
                                </a>
                                <a href="/health-tracker/public/connect_doctor.php" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gradient-to-r hover:from-blue-50 hover:to-cyan-50 hover:text-blue-700 transition-all duration-200 mx-2 rounded-xl">
                                    <svg class="w-4 h-4 mr-3 icon-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    </svg>
                                    Connect with Doctors
                                </a>
                            </div>
                        </div>
                        
                        <a href="/health-tracker/public/messages.php" class="nav-link flex items-center px-4 py-2 text-gray-700 hover:text-gray-900 rounded-xl transition-all duration-200 hover:bg-green-400/10 <?php echo basename($_SERVER['PHP_SELF']) === 'messages.php' ? 'active bg-gradient-to-r from-gray-800 to-green-400 text-white shadow-lg' : ''; ?>">
                            <svg class="w-4 h-4 mr-2 icon-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                            Messages
                        </a>
                    <?php elseif ($currentUser['role'] === 'doctor'): ?>
                        <a href="/health-tracker/doctor/dashboard.php" class="nav-link flex items-center px-4 py-2 text-gray-700 hover:text-gray-900 rounded-xl transition-all duration-300 transform hover:scale-105 hover:bg-green-400/10 <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active bg-gradient-to-r from-gray-800 to-green-400 text-white shadow-lg' : ''; ?>">
                            <svg class="w-4 h-4 mr-2 transition-transform duration-300 hover:rotate-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"/>
                            </svg>
                            Dashboard
                        </a>
                        <a href="/health-tracker/doctor/verify_habits.php" class="nav-link flex items-center px-4 py-2 text-gray-700 hover:text-gray-900 rounded-xl transition-all duration-300 transform hover:scale-105 hover:bg-green-400/10 <?php echo basename($_SERVER['PHP_SELF']) === 'verify_habits.php' ? 'active bg-gradient-to-r from-gray-800 to-green-400 text-white shadow-lg' : ''; ?>">
                            <svg class="w-4 h-4 mr-2 transition-transform duration-300 hover:rotate-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Verify Habits
                        </a>
                        <a href="/health-tracker/doctor/patients.php" class="nav-link flex items-center px-4 py-2 text-gray-700 hover:text-gray-900 rounded-xl transition-all duration-300 transform hover:scale-105 hover:bg-green-400/10 <?php echo basename($_SERVER['PHP_SELF']) === 'patients.php' ? 'active bg-gradient-to-r from-gray-800 to-green-400 text-white shadow-lg' : ''; ?>">
                            <svg class="w-4 h-4 mr-2 transition-transform duration-300 hover:rotate-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                            </svg>
                            Patients
                        </a>
                        <a href="/health-tracker/doctor/messages.php" class="nav-link flex items-center px-4 py-2 text-gray-700 hover:text-gray-900 rounded-xl transition-all duration-300 transform hover:scale-105 hover:bg-green-400/10 <?php echo basename($_SERVER['PHP_SELF']) === 'messages.php' ? 'active bg-gradient-to-r from-gray-800 to-green-400 text-white shadow-lg' : ''; ?>">
                            <svg class="w-4 h-4 mr-2 transition-transform duration-300 hover:rotate-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                            Messages
                        </a>
                    <?php elseif ($currentUser['role'] === 'admin'): ?>
                        <a href="/health-tracker/admin/dashboard.php" class="nav-link flex items-center px-4 py-2 text-gray-700 hover:text-gray-900 rounded-xl transition-all duration-300 transform hover:scale-105 hover:bg-green-400/10 <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active bg-gradient-to-r from-gray-800 to-green-400 text-white shadow-lg' : ''; ?>">
                            <svg class="w-4 h-4 mr-2 transition-transform duration-300 hover:rotate-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"/>
                            </svg>
                            Dashboard
                        </a>
                        <a href="/health-tracker/admin/users.php" class="nav-link flex items-center px-4 py-2 text-gray-700 hover:text-gray-900 rounded-xl transition-all duration-300 transform hover:scale-105 hover:bg-green-400/10 <?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active bg-gradient-to-r from-gray-800 to-green-400 text-white shadow-lg' : ''; ?>">
                            <svg class="w-4 h-4 mr-2 transition-transform duration-300 hover:rotate-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                            </svg>
                            Users
                        </a>
                        <a href="/health-tracker/admin/assessments.php" class="nav-link flex items-center px-4 py-2 text-gray-700 hover:text-gray-900 rounded-xl transition-all duration-300 transform hover:scale-105 hover:bg-green-400/10 <?php echo basename($_SERVER['PHP_SELF']) === 'assessments.php' ? 'active bg-gradient-to-r from-gray-800 to-green-400 text-white shadow-lg' : ''; ?>">
                            <svg class="w-4 h-4 mr-2 transition-transform duration-300 hover:rotate-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            Assessments
                        </a>
                    <?php endif; ?>
                </div>

                <!-- User Menu -->
                <div class="hidden md:flex items-center space-x-4">
                    <!-- Notifications -->
                    <div class="relative">
                        <button class="p-3 text-gray-600 hover:text-gray-900 transition-all duration-300 transform hover:scale-110 hover:bg-green-400/10 rounded-xl relative bell-ring ripple" id="notificationBtn">
                            <svg class="w-5 h-5 icon-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                            </svg>
                            <span class="absolute -top-1 -right-1 w-3 h-3 rounded-full pulse-dot" style="background: linear-gradient(135deg, #A1D1B1 0%, #1C2529 100%);"></span>
                        </button>
                    </div>

                    <!-- Profile Dropdown -->
                    <div class="relative" id="profileDropdownContainer" onmouseenter="showDropdown()" onmouseleave="hideDropdown()">
                        <button id="profileButton" class="flex items-center space-x-3 p-2 rounded-xl hover:bg-gradient-to-r hover:from-green-400/10 hover:to-gray-800/10 transition-all duration-200 group">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center text-white text-sm font-bold shadow-lg group-hover:shadow-xl transition-all duration-300 overflow-hidden">
                                <?php if (!empty($currentUser['avatar']) && file_exists(__DIR__ . '/../../../public/' . $currentUser['avatar'])): ?>
                                    <img src="/health-tracker/public/<?php echo htmlspecialchars($currentUser['avatar']); ?>" alt="Profile" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="w-full h-full avatar-gradient flex items-center justify-center">
                                        <?php echo strtoupper(substr($currentUser['name'], 0, 2)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <span class="text-sm font-semibold gradient-text hidden sm:block"><?php echo htmlspecialchars($currentUser['name']); ?></span>
                            <svg id="dropdownIcon" class="w-4 h-4 text-gray-600 transition-all duration-300 group-hover:text-gray-900 group-hover:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <div id="profileDropdown" class="hidden absolute right-0 mt-3 w-56 bg-white/95 backdrop-blur-xl rounded-2xl shadow-2xl border border-gray-100 py-3 z-50 dropdown-enter">
                            <a href="/health-tracker/public/profile.php" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gradient-to-r hover:from-green-400/10 hover:to-gray-800/10 hover:text-gray-900 transition-all duration-200 mx-2 rounded-xl">
                                <svg class="w-4 h-4 mr-3 icon-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                My Profile
                            </a>
                            <a href="/health-tracker/public/settings.php" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gradient-to-r hover:from-green-400/10 hover:to-gray-800/10 hover:text-gray-900 transition-all duration-200 mx-2 rounded-xl">
                                <svg class="w-4 h-4 mr-3 icon-rotate" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                Settings
                            </a>
                            <a href="/health-tracker/public/about.php" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gradient-to-r hover:from-green-400/10 hover:to-gray-800/10 hover:text-gray-900 transition-all duration-200 mx-2 rounded-xl">
                                <svg class="w-4 h-4 mr-3 icon-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                About Us
                            </a>
                            <hr class="my-2 border-gray-200/50">
                            <a href="/health-tracker/public/logout.php" class="flex items-center px-4 py-3 text-sm text-red-600 hover:bg-gradient-to-r hover:from-red-50 hover:to-pink-50 hover:text-red-700 transition-all duration-200 mx-2 rounded-xl">
                                <svg class="w-4 h-4 mr-3 icon-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                Sign Out
                            </a>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <!-- Guest Navigation -->
                <div class="flex items-center space-x-2 sm:space-x-4">
                    <a href="/health-tracker/public/login.php" class="text-gray-600 hover:text-gray-900 transition-all duration-300 font-medium px-3 sm:px-4 py-2 rounded-xl hover:bg-green-400/10 transform hover:scale-105 text-sm sm:text-base">Sign In</a>
                    <a href="/health-tracker/public/register.php" class="bg-gradient-to-r from-gray-800 to-green-400 hover:from-primary-light hover:to-accent text-white font-semibold py-2 px-4 sm:px-6 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105 hover:-translate-y-0.5 text-sm sm:text-base">Get Started</a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Mobile Menu -->
        <?php if ($auth->isLoggedIn()): ?>
        <div id="mobileMenu" class="hidden md:hidden bg-white/95 backdrop-blur-xl border-t border-gray-200">
            <div class="px-4 pt-2 pb-4 space-y-2">
                <?php if ($currentUser['role'] === 'user'): ?>
                    <a href="/health-tracker/public/dashboard.php" class="flex items-center px-4 py-3 text-gray-700 hover:bg-green-400/10 rounded-xl transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'bg-gradient-to-r from-gray-800 to-green-400 text-white' : ''; ?>">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                        Dashboard
                    </a>
                    <a href="/health-tracker/public/assessment.php" class="flex items-center px-4 py-3 text-gray-700 hover:bg-green-400/10 rounded-xl transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) === 'assessment.php' ? 'bg-gradient-to-r from-gray-800 to-green-400 text-white' : ''; ?>">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                        </svg>
                        Assessment
                    </a>
                    <a href="/health-tracker/public/habits.php" class="flex items-center px-4 py-3 text-gray-700 hover:bg-green-400/10 rounded-xl transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) === 'habits.php' ? 'bg-gradient-to-r from-gray-800 to-green-400 text-white' : ''; ?>">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Habits
                    </a>
                    
                    <!-- Mobile Activities Submenu -->
                    <div class="pl-4 space-y-2">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider px-4 py-2">Activities</p>
                        <a href="/health-tracker/public/physical_assessment.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-green-50 rounded-xl transition-all duration-200">
                            <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Physical Assessment
                        </a>
                        <a href="/health-tracker/public/mental_assessment.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-purple-50 rounded-xl transition-all duration-200">
                            <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                            </svg>
                            Mental Assessment
                        </a>
                        <a href="/health-tracker/public/connect_doctor.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 rounded-xl transition-all duration-200">
                            <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            Connect with Doctors
                        </a>
                    </div>
                    
                    <a href="/health-tracker/public/messages.php" class="flex items-center px-4 py-3 text-gray-700 hover:bg-green-400/10 rounded-xl transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) === 'messages.php' ? 'bg-gradient-to-r from-gray-800 to-green-400 text-white' : ''; ?>">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                        Messages
                    </a>
                <?php elseif ($currentUser['role'] === 'doctor'): ?>
                    <a href="/health-tracker/doctor/dashboard.php" class="flex items-center px-4 py-3 text-gray-700 hover:bg-green-400/10 rounded-xl transition-all duration-200">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"/>
                        </svg>
                        Dashboard
                    </a>
                    <a href="/health-tracker/doctor/verify_habits.php" class="flex items-center px-4 py-3 text-gray-700 hover:bg-green-400/10 rounded-xl transition-all duration-200">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Verify Habits
                    </a>
                    <a href="/health-tracker/doctor/patients.php" class="flex items-center px-4 py-3 text-gray-700 hover:bg-green-400/10 rounded-xl transition-all duration-200">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                        </svg>
                        Patients
                    </a>
                    <a href="/health-tracker/doctor/messages.php" class="flex items-center px-4 py-3 text-gray-700 hover:bg-green-400/10 rounded-xl transition-all duration-200">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                        Messages
                    </a>
                <?php elseif ($currentUser['role'] === 'admin'): ?>
                    <a href="/health-tracker/admin/dashboard.php" class="flex items-center px-4 py-3 text-gray-700 hover:bg-green-400/10 rounded-xl transition-all duration-200">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"/>
                        </svg>
                        Dashboard
                    </a>
                    <a href="/health-tracker/admin/users.php" class="flex items-center px-4 py-3 text-gray-700 hover:bg-green-400/10 rounded-xl transition-all duration-200">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                        </svg>
                        Users
                    </a>
                    <a href="/health-tracker/admin/assessments.php" class="flex items-center px-4 py-3 text-gray-700 hover:bg-green-400/10 rounded-xl transition-all duration-200">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        Assessments
                    </a>
                <?php endif; ?>
                
                <!-- Mobile Profile Section -->
                <div class="border-t border-gray-200 pt-4 mt-4">
                    <a href="/health-tracker/public/profile.php" class="flex items-center px-4 py-3 text-gray-700 hover:bg-green-400/10 rounded-xl transition-all duration-200">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        My Profile
                    </a>
                    <a href="/health-tracker/public/settings.php" class="flex items-center px-4 py-3 text-gray-700 hover:bg-green-400/10 rounded-xl transition-all duration-200">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Settings
                    </a>
                    <a href="/health-tracker/public/about.php" class="flex items-center px-4 py-3 text-gray-700 hover:bg-green-400/10 rounded-xl transition-all duration-200">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        About Us
                    </a>
                    <a href="/health-tracker/public/logout.php" class="flex items-center px-4 py-3 text-red-600 hover:bg-red-50 rounded-xl transition-all duration-200">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        Sign Out
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </nav>

    <!-- Flash Messages -->
    <?php if ($flashMessage): ?>
    <div id="flashMessage" class="fixed top-4 right-4 max-w-md p-4 rounded-lg shadow-lg z-50 transition-all duration-300 <?php 
        echo $flashMessage['type'] === 'success' ? 'bg-green-600 text-white' : 
            ($flashMessage['type'] === 'error' ? 'bg-red-600 text-white' : 
            ($flashMessage['type'] === 'warning' ? 'bg-yellow-500 text-white' : 'bg-blue-600 text-white')); 
    ?>" style="display: block;">
        <div class="flex items-center justify-between">
            <p class="font-medium"><?php echo htmlspecialchars($flashMessage['message']); ?></p>
            <button onclick="closeFlashMessage()" class="ml-4 text-white hover:text-gray-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Content Container -->
    <main class="flex-1">

    <!-- Alpine.js for dropdown functionality -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <script>
        // Flash message auto-hide and close functionality
        function closeFlashMessage() {
            const flashMessage = document.getElementById('flashMessage');
            if (flashMessage) {
                flashMessage.style.display = 'none';
            }
        }

        // Auto-hide flash messages after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const flashMessage = document.getElementById('flashMessage');
            if (flashMessage) {
                setTimeout(closeFlashMessage, 5000);
            }
            
            // Mobile Menu Toggle
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const mobileMenu = document.getElementById('mobileMenu');
            const menuIcon = document.getElementById('menuIcon');
            const closeIcon = document.getElementById('closeIcon');
            
            if (mobileMenuBtn && mobileMenu) {
                mobileMenuBtn.addEventListener('click', function() {
                    mobileMenu.classList.toggle('hidden');
                    menuIcon.classList.toggle('hidden');
                    closeIcon.classList.toggle('hidden');
                });
                
                // Close mobile menu when clicking on a link
                const mobileLinks = mobileMenu.querySelectorAll('a');
                mobileLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        mobileMenu.classList.add('hidden');
                        menuIcon.classList.remove('hidden');
                        closeIcon.classList.add('hidden');
                    });
                });
            }
            
            // Enhanced sticky header glassmorphism effect on scroll
            const nav = document.getElementById('mainNav');
            let lastScrollY = window.scrollY;
            let ticking = false;
            
            function updateNavbar() {
                const currentScrollY = window.scrollY;
                
                if (currentScrollY > 30) {
                    // Add enhanced glassmorphism effect when scrolled
                    nav.classList.add('header-glass', 'shadow-2xl');
                    nav.classList.remove('border-b', 'border-white/20', 'bg-white/90');
                    nav.style.transform = 'translateY(0)';
                } else {
                    // Remove glassmorphism effect when at top
                    nav.classList.remove('header-glass', 'shadow-2xl');
                    nav.classList.add('border-b', 'border-white/20', 'bg-white/90');
                }
                
                // Hide/show navbar based on scroll direction
                if (currentScrollY > 100) {
                    if (currentScrollY > lastScrollY && currentScrollY > 200) {
                        // Scrolling down
                        nav.style.transform = 'translateY(-100%)';
                    } else {
                        // Scrolling up
                        nav.style.transform = 'translateY(0)';
                    }
                }
                
                lastScrollY = currentScrollY;
                ticking = false;
            }
            
            function requestTick() {
                if (!ticking) {
                    requestAnimationFrame(updateNavbar);
                    ticking = true;
                }
            }
            
            // Listen for scroll events with throttling
            window.addEventListener('scroll', requestTick, { passive: true });
            
            // Initialize on page load
            updateNavbar();
            
            // Add intersection observer for smooth animations
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate-fade-in-up');
                    }
                });
            }, observerOptions);
        });
        
        // Profile dropdown hover functions
        let dropdownTimeout;
        let isDropdownOpen = false;
        
        function showDropdown() {
            clearTimeout(dropdownTimeout);
            const dropdown = document.getElementById('profileDropdown');
            const icon = document.getElementById('dropdownIcon');
            
            if (!dropdown) return;
            
            isDropdownOpen = true;
            dropdown.classList.remove('hidden');
            setTimeout(() => {
                dropdown.style.opacity = '1';
                dropdown.style.transform = 'translateY(0)';
            }, 10);
            if (icon) icon.style.transform = 'rotate(180deg)';
        }
        
        function hideDropdown() {
            dropdownTimeout = setTimeout(() => {
                const dropdown = document.getElementById('profileDropdown');
                const icon = document.getElementById('dropdownIcon');
                
                if (!dropdown) return;
                
                isDropdownOpen = false;
                dropdown.style.opacity = '0';
                dropdown.style.transform = 'translateY(-10px)';
                if (icon) icon.style.transform = 'rotate(0deg)';
                setTimeout(() => {
                    if (!isDropdownOpen) {
                        dropdown.classList.add('hidden');
                    }
                }, 300);
            }, 150);
        }
        
        // Activities dropdown hover functions
        let activitiesDropdownTimeout;
        let isActivitiesDropdownOpen = false;
        
        function showActivitiesDropdown() {
            clearTimeout(activitiesDropdownTimeout);
            const dropdown = document.getElementById('activitiesDropdown');
            const icon = document.getElementById('activitiesDropdownIcon');
            
            if (!dropdown) return;
            
            isActivitiesDropdownOpen = true;
            dropdown.classList.remove('hidden');
            setTimeout(() => {
                dropdown.style.opacity = '1';
                dropdown.style.transform = 'translateY(0)';
            }, 10);
            if (icon) icon.style.transform = 'rotate(180deg)';
        }
        
        function hideActivitiesDropdown() {
            activitiesDropdownTimeout = setTimeout(() => {
                const dropdown = document.getElementById('activitiesDropdown');
                const icon = document.getElementById('activitiesDropdownIcon');
                
                if (!dropdown) return;
                
                isActivitiesDropdownOpen = false;
                dropdown.style.opacity = '0';
                dropdown.style.transform = 'translateY(-10px)';
                if (icon) icon.style.transform = 'rotate(0deg)';
                setTimeout(() => {
                    if (!isActivitiesDropdownOpen) {
                        dropdown.classList.add('hidden');
                    }
                }, 300);
            }, 150);
        }
        
        // Profile dropdown toggle with smooth animation (kept for compatibility)
        function toggleDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            const icon = document.getElementById('dropdownIcon');
            
            if (dropdown.classList.contains('hidden')) {
                dropdown.classList.remove('hidden');
                setTimeout(() => {
                    dropdown.style.opacity = '1';
                    dropdown.style.transform = 'translateY(0)';
                }, 10);
                icon.style.transform = 'rotate(180deg)';
            } else {
                dropdown.style.opacity = '0';
                dropdown.style.transform = 'translateY(-10px)';
                icon.style.transform = 'rotate(0deg)';
                setTimeout(() => {
                    dropdown.classList.add('hidden');
                }, 300);
            }
        }
        
        // Remove click-outside handler for dropdown (now using hover)
        // Keeping ripple effect for all ripple elements
        document.querySelectorAll('.ripple').forEach(element => {
            element.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                ripple.classList.add('ripple-effect');
                
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });
        
        // Initialize dropdown opacity
        const dropdown = document.getElementById('profileDropdown');
        if (dropdown) {
            dropdown.style.opacity = '0';
            dropdown.style.transform = 'translateY(-10px)';
            dropdown.style.transition = 'opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1), transform 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
        }
        
        // Initialize activities dropdown opacity
        const activitiesDropdown = document.getElementById('activitiesDropdown');
        if (activitiesDropdown) {
            activitiesDropdown.style.opacity = '0';
            activitiesDropdown.style.transform = 'translateY(-10px)';
            activitiesDropdown.style.transition = 'opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1), transform 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
        }
    </script>
